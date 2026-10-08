<?php

use App\Models\CommercialQuote;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\PurchaseDocument;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('E2E-03 compra cotiza convierte vende a credito cobra gasta y cierra sin descuadre', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $product = Product::factory()->for($shop)->create([
        'name' => 'Producto E2E-03',
        'price' => '250.00',
        'sale_unit' => 'unit',
    ]);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 0,
        'sold_quantity' => 0,
        'cost_price' => '0.00',
    ]);

    $token = $owner->createToken('qa-e2e-003', ['*'])->plainTextToken;
    $base = "/api/v1/shops/{$shop->public_id}";

    $this->withToken($token)
        ->postJson("{$base}/cash-sessions/open", ['opening_amount' => '0.00'])
        ->assertCreated()
        ->assertJsonPath('session.status', 'open');

    $draft = $this->withToken($token)
        ->postJson("{$base}/purchases", [
            'type' => 'container',
            'document_number' => 'E2E-003-COMPRA',
            'mode' => 'draft',
            'items' => [[
                'product_id' => $product->public_id,
                'quantity' => 2,
                'unit_cost' => '100.00',
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('document.status', 'draft')
        ->json('document');

    $this->withToken($token)
        ->postJson("{$base}/purchases/{$draft['id']}/receive")
        ->assertOk()
        ->assertJsonPath('document.status', 'received');

    expect(PurchaseDocument::query()->where('shop_id', $shop->id)->count())->toBe(1)
        ->and($product->fresh()->inventory->stock_quantity)->toBe(2)
        ->and($product->fresh()->inventoryLots()->count())->toBe(1);

    $quote = $this->withToken($token)
        ->postJson("{$base}/quotes", [
            'customer_name' => 'Cliente E2E-003',
            'items' => [[
                'product_id' => $product->public_id,
                'quantity' => 1,
                'unit_price' => '250.00',
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('quote.status', 'draft')
        ->assertJsonPath('quote.total', '250.00')
        ->json('quote');

    $this->withToken($token)
        ->postJson("{$base}/quotes/{$quote['id']}/convert")
        ->assertOk()
        ->assertJsonPath('message', 'Cotización convertida en venta.');

    expect(CommercialQuote::query()->where('shop_id', $shop->id)->sole()->status)->toBe('converted')
        ->and(Invoice::query()->where('shop_id', $shop->id)->count())->toBe(1)
        ->and($product->fresh()->inventory->stock_quantity)->toBe(1);

    $customer = $this->withToken($token)
        ->postJson("{$base}/customers", [
            'client_customer_uuid' => (string) Str::uuid(),
            'name' => 'Cliente Crédito E2E-003',
            'credit_limit' => '500.00',
        ])
        ->assertCreated()
        ->json();

    $this->withToken($token)
        ->postJson("{$base}/pos-sales", [
            'client_sale_uuid' => (string) Str::uuid(),
            'payment_status' => 'pending',
            'customer_id' => $customer['id'],
            'credit_amount' => '250.00',
            'items' => [[
                'product_id' => $product->public_id,
                'quantity' => 1,
                'unit_price' => '250.00',
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('total', '250.00');

    expect(Invoice::query()->where('shop_id', $shop->id)->count())->toBe(2)
        ->and(Customer::query()->where('shop_id', $shop->id)->where('public_id', $customer['id'])->exists())->toBeTrue();

    $customerModel = Customer::query()->where('shop_id', $shop->id)->sole();
    expect($customerModel->balance)->toBe('250.00')
        ->and($product->fresh()->inventory->stock_quantity)->toBe(0)
        ->and(InventoryMovement::query()->where('product_id', $product->id)->where('type', 'sale')->count())->toBe(2);

    $this->withToken($token)
        ->postJson("{$base}/customers/{$customerModel->public_id}/payments", [
            'client_transaction_uuid' => (string) Str::uuid(),
            'amount' => '100.00',
            'payment_method' => 'cash',
            'notes' => 'Abono E2E-003',
        ])
        ->assertCreated()
        ->assertJsonPath('customer.balance', '150.00')
        ->assertJsonPath('entry.amount', '-100.00');

    $this->withToken($token)
        ->postJson("{$base}/expenses", [
            'client_operation_uuid' => (string) Str::uuid(),
            'category_name' => 'E2E-003',
            'description' => 'Gasto operativo E2E-003',
            'amount' => '30.00',
            'paid_amount' => '30.00',
            'payment_method' => 'cash',
            'occurred_at' => now()->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('expense.amount', 30)
        ->assertJsonPath('expense.amount_paid', 30);

    $summary = $this->withToken($token)
        ->getJson("{$base}/finance/summary")
        ->assertOk()
        ->json();

    expect((float) $summary['period']['net_sales'])->toBe(500.0)
        ->and((float) $summary['period']['fifo_cogs'])->toBe(200.0)
        ->and((float) $summary['period']['gross_profit'])->toBe(300.0)
        ->and((float) $summary['current_state']['receivable_total'])->toBe(150.0)
        ->and((float) $summary['cash_flow']['inflows']['sales_cash'])->toBe(250.0)
        ->and((float) $summary['cash_flow']['inflows']['debt_collections_cash'])->toBe(100.0)
        ->and((float) $summary['cash_flow']['outflows']['expenses_paid_cash'])->toBe(30.0);

    $closure = $this->withToken($token)
        ->postJson("{$base}/finance/day-close", [
            'date' => now()->toDateString(),
            'counted_cash' => '320.00',
        ])
        ->assertOk()
        ->json('closure');

    expect((float) $closure['expected_cash'])->toBe(320.0)
        ->and((int) $closure['difference'])->toBe(0);
});
