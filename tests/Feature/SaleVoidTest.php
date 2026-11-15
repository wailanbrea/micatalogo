<?php

use App\Models\{CashMovement, Customer, Invoice, InvoiceItem, InvoicePayment, Product, ProductInventory, Shop, ShopSeller, User};
use App\Services\{CashRegisterService, CustomerAccountService, PaymentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('sales history displays void button and omits unnecessary note', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'invoice_number' => 'FAC-1001',
        'channel' => 'pos',
        'currency' => 'DOP',
        'status' => 'paid',
        'subtotal' => '500.00',
        'total' => '500.00',
        'issued_at' => now(),
    ]);

    $response = $this->actingAs($owner)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'sales']));

    $response->assertOk()
        ->assertSee('FAC-1001')
        ->assertSee('Anular')
        ->assertSee('Devolver artículos al inventario')
        ->assertSee(route('seller.shops.sales.void', [$shop, $invoice]), false)
        ->assertDontSee('Las ventas confirmadas alimentan inventario');
});

test('owner can void unpaid credit sale from sales history', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $product = Product::factory()->for($shop)->create(['price' => 250]);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 8]);
    $customer = Customer::create(['shop_id' => $shop->id, 'name' => 'Cliente Credito', 'credit_limit' => '5000.00', 'balance' => '0.00']);

    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'user_id' => $owner->id,
        'invoice_number' => 'FAC-CR-1',
        'channel' => 'pos',
        'currency' => 'DOP',
        'status' => 'pending',
        'subtotal' => '500.00',
        'total' => '500.00',
        'issued_at' => now(),
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 2,
        'unit_price' => '250.00',
        'line_total' => '500.00',
        'total_cost_cents' => 20000,
    ]);
    app(CustomerAccountService::class)->recordInvoiceCharge($customer, $invoice, '500.00', $owner->id);

    $this->actingAs($owner)
        ->post(route('seller.shops.sales.void', [$shop, $invoice]), ['reason' => 'Venta duplicada'])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($invoice->fresh()->status)->toBe('void')
        ->and($customer->fresh()->balance)->toBe('0.00')
        ->and((int) $product->inventory->fresh()->stock_quantity)->toBe(10);
});

test('owner can void direct paid cash sale and reverse cash movement', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $product = Product::factory()->for($shop)->create(['price' => 300]);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 5]);
    $session = app(CashRegisterService::class)->openSession($shop, $owner, '100.00');

    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'invoice_number' => 'FAC-CASH-1',
        'channel' => 'pos',
        'currency' => 'DOP',
        'status' => 'paid',
        'subtotal' => '300.00',
        'total' => '300.00',
        'issued_at' => now(),
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => '300.00',
        'line_total' => '300.00',
        'total_cost_cents' => 15000,
    ]);

    app(PaymentService::class)->recordInvoicePayments($invoice, [
        ['payment_method' => 'cash', 'amount' => '300.00'],
    ], $owner);

    $this->actingAs($owner)
        ->post(route('seller.shops.sales.void', [$shop, $invoice]), ['reason' => 'Cobrada dos veces'])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($invoice->fresh()->status)->toBe('void')
        ->and((int) $product->inventory->fresh()->stock_quantity)->toBe(6)
        ->and(InvoicePayment::withoutGlobalScope('applied')->where('invoice_id', $invoice->id)->first()?->reversed_at)->not->toBeNull()
        ->and(CashMovement::where('shop_id', $shop->id)->where('type', 'sale_reversal')->count())->toBe(1);
});

test('regular seller cannot void a sale', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $seller = User::factory()->create();
    ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $seller->id, 'commission_type' => 'percentage', 'commission_value' => 0, 'is_active' => true]);

    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'invoice_number' => 'FAC-SEL-1',
        'channel' => 'pos',
        'currency' => 'DOP',
        'status' => 'paid',
        'subtotal' => '100.00',
        'total' => '100.00',
        'issued_at' => now(),
    ]);

    $this->actingAs($seller)
        ->post(route('seller.shops.sales.void', [$shop, $invoice]), ['reason' => 'Intento'])
        ->assertForbidden();
});

test('api voidSaleApi voids sale by invoice id and returns json', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $token = $owner->createToken('test', ['pos:write'])->plainTextToken;
    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'invoice_number' => 'FAC-API-1',
        'channel' => 'pos',
        'currency' => 'DOP',
        'status' => 'paid',
        'subtotal' => '200.00',
        'total' => '200.00',
        'issued_at' => now(),
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/shops/{$shop->public_id}/sales/{$invoice->id}/void", ['reason' => 'Error desde app'])
        ->assertOk()
        ->assertJson(['id' => (string) $invoice->id, 'status' => 'void']);

    expect($invoice->fresh()->status)->toBe('void');
});

test('api voidSaleApi respects restock false and leaves inventory untouched', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $product = Product::factory()->for($shop)->create(['price' => 200]);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 5]);
    $token = $owner->createToken('test', ['pos:write'])->plainTextToken;
    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'invoice_number' => 'FAC-API-NORESTOCK',
        'channel' => 'pos',
        'currency' => 'DOP',
        'status' => 'paid',
        'subtotal' => '200.00',
        'total' => '200.00',
        'issued_at' => now(),
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 3,
        'unit_price' => '200.00',
        'line_total' => '600.00',
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/shops/{$shop->public_id}/sales/{$invoice->id}/void", [
            'reason' => 'Merma o daño sin reposición',
            'restock' => false,
        ])
        ->assertOk()
        ->assertJson(['id' => (string) $invoice->id, 'status' => 'void']);

    expect($invoice->fresh()->status)->toBe('void')
        ->and((int) $product->inventory()->first()->stock_quantity)->toBe(5);
});
