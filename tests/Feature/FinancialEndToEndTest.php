<?php

use App\Models\CashMovement;
use App\Models\Customer;
use App\Models\DailyClosure;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use App\Services\BusinessDashboardService;
use App\Services\CashRegisterService;
use App\Services\DailyCloseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('FIN-001 mixed sale collection expense and close reconcile exact business totals', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $product = Product::factory()->for($shop)->create([
        'name' => 'Producto FIN-001',
        'price' => 250,
        'sale_unit' => 'unit',
    ]);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 3,
        'cost_price' => 100,
        'sold_quantity' => 0,
        'low_stock_threshold' => 1,
    ]);
    $customer = Customer::create([
        'shop_id' => $shop->id,
        'name' => 'Cliente FIN-001',
        'credit_limit' => 1000,
        'balance' => 0,
        'is_active' => true,
    ]);

    app(CashRegisterService::class)->openSession($shop, $owner, '0.00', 'FIN-001');

    $token = $owner->createToken('qa-fin-001', [
        'pos:write',
        'customers:write',
    ])->plainTextToken;
    $sale = $this->withToken($token)->postJson("/api/v1/shops/{$shop->public_id}/pos-sales", [
        'client_sale_uuid' => (string) Str::uuid(),
        'payment_status' => 'partial',
        'customer_id' => $customer->public_id,
        'credit_amount' => '200.00',
        'payments' => [
            ['method' => 'cash', 'amount' => '400.00'],
            ['method' => 'card', 'amount' => '150.00'],
        ],
        'items' => [[
            'product_id' => $product->public_id,
            'quantity' => 3,
            'unit_price' => '250.00',
        ]],
    ])->assertCreated()
        ->assertJsonPath('total', '750.00')
        ->assertJsonPath('status', 'partial');

    $invoice = Invoice::query()->with('items')->sole();
    expect($invoice->status)->toBe('partial')
        ->and($invoice->items->sole()->total_cost_cents)->toBe(30000)
        ->and($product->fresh()->inventory->stock_quantity)->toBe(0)
        ->and($customer->fresh()->balance)->toBe('200.00');

    $payment = $this->withToken($token)->postJson("/api/v1/shops/{$shop->public_id}/customers/{$customer->public_id}/payments", [
        'client_transaction_uuid' => (string) Str::uuid(),
        'amount' => '120.00',
        'payment_method' => 'cash',
        'notes' => 'FIN-001 abono',
    ])->assertCreated()
        ->assertJsonPath('customer_balance', '80.00');

    $expense = $this->withToken($token)->postJson("/api/v1/shops/{$shop->public_id}/expenses", [
        'client_operation_uuid' => (string) Str::uuid(),
        'category_name' => 'FIN-001',
        'description' => 'Gasto operativo FIN-001',
        'amount' => '90.00',
        'paid_amount' => '40.00',
        'payment_method' => 'cash',
        'occurred_at' => now()->toDateString(),
    ])->assertCreated()
        ->assertJsonPath('expense.amount', 90)
        ->assertJsonPath('expense.amount_paid', 40)
        ->assertJsonPath('expense.payment_status', 'partial');

    $summary = app(BusinessDashboardService::class)->getSummary(
        $shop->fresh(),
        now()->startOfDay()->toDateString(),
        now()->toDateString(),
    );
    $close = app(DailyCloseService::class)->calculate($shop->fresh(), now()->toDateString());

    expect($summary['period']['net_sales'])->toBe(750.0)
        ->and($summary['period']['fifo_cogs'])->toBe(300.0)
        ->and($summary['period']['gross_profit'])->toBe(450.0)
        ->and($summary['period']['operating_expenses'])->toBe(90.0)
        ->and($summary['period']['operating_profit'])->toBe(360.0)
        ->and($summary['current_state']['receivable_total'])->toBe(80.0)
        ->and($summary['cash_flow']['inflows']['sales_cash'])->toBe(400.0)
        ->and($summary['cash_flow']['inflows']['debt_collections_cash'])->toBe(120.0)
        ->and($summary['cash_flow']['outflows']['expenses_paid_cash'])->toBe(40.0)
        // `net_cash_flow` includes the RD$150 card inflow; the physical cash
        // expected by the daily close is asserted separately below.
        ->and($summary['cash_flow']['net_cash_flow'])->toBe(630.0)
        ->and($close['expected_cash'])->toBe(480.0);

    $closure = app(DailyCloseService::class)->close($shop->fresh(), $owner, now()->toDateString(), '480.00', 'FIN-001');

    expect($closure->expected_cash_cents)->toBe(48000)
        ->and($closure->counted_cash_cents)->toBe(48000)
        ->and($closure->difference_cents)->toBe(0)
        ->and(DailyClosure::query()->where('shop_id', $shop->id)->count())->toBe(1)
        ->and(Expense::query()->sole()->unpaidAmountCents())->toBe(5000)
        ->and((int) CashMovement::query()->whereNotNull('cash_register_session_id')->sum('amount_cents'))->toBe(48000);
});
