<?php

use App\Enums\UserPlan;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use App\Services\BusinessDashboardService;
use App\Services\CashRegisterService;
use App\Services\ExpenseService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function financialFixture(): array
{
    $user = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create(['shop_id' => $shop->id, 'price' => 500, 'sale_unit' => 'unit']);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 20,
        'cost_price' => 200,
        'sold_quantity' => 0,
        'low_stock_threshold' => 2,
    ]);

    return [$user, $shop, $product];
}

test('split payment validates that paid plus credit equals invoice total in cents', function () {
    [$user, $shop, $product] = financialFixture();
    $paymentService = app(PaymentService::class);

    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $user->id,
        'invoice_number' => 'INV-001',
        'subtotal' => 1000,
        'discount' => 0,
        'tax' => 0,
        'total' => 1000,
        'payment_status' => 'paid',
        'issued_at' => now(),
    ]);

    // Scenario 1: Mismatched total throws exception
    expect(fn () => $paymentService->recordInvoicePayments($invoice, [
        ['payment_method' => 'cash', 'amount' => 500],
        ['payment_method' => 'card', 'amount' => 400],
    ], $user))->toThrow(\InvalidArgumentException::class);

    // Scenario 2: Perfect match: 300 cash + 400 transfer + 300 card = 1000
    $payments = $paymentService->recordInvoicePayments($invoice, [
        ['payment_method' => 'cash', 'amount' => 300],
        ['payment_method' => 'bank_transfer', 'amount' => 400],
        ['payment_method' => 'card', 'amount' => 300],
    ], $user);

    expect($payments)->toHaveCount(3)
        ->and(InvoicePayment::where('invoice_id', $invoice->id)->sum('amount_cents'))->toBe(100000);
});

test('cash sale automatically creates cash movement when cash register session is open', function () {
    [$user, $shop, $product] = financialFixture();
    $cashService = app(CashRegisterService::class);
    $paymentService = app(PaymentService::class);

    // 1. Open cash session with RD$ 1,000
    $session = $cashService->openSession($shop, $user, 1000, 'Apertura de turno');
    expect($session->opening_amount_cents)->toBe(100000);

    // 2. Register sale of RD$ 500 in cash
    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $user->id,
        'invoice_number' => 'INV-CASH',
        'subtotal' => 500,
        'total' => 500,
        'payment_status' => 'paid',
        'issued_at' => now(),
    ]);

    $paymentService->recordInvoicePayments($invoice, [
        ['payment_method' => 'cash', 'amount' => 500],
    ], $user);

    // Verify cash movement was recorded
    $movement = CashMovement::where('cash_register_session_id', $session->id)->first();
    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe('sale')
        ->and($movement->amount_cents)->toBe(50000);

    // Verify expected closing balance: 1000 + 500 = 1500
    expect($session->calculateExpectedBalance())->toBe(150000);
});

test('credit sale increases receivable and profit without increasing cash flow', function () {
    [$user, $shop, $product] = financialFixture();
    $paymentService = app(PaymentService::class);
    $dashboardService = app(BusinessDashboardService::class);

    $customer = Customer::create([
        'shop_id' => $shop->id,
        'name' => 'Juan Perez',
        'credit_limit' => 5000,
        'balance' => 0,
        'is_active' => true,
    ]);

    // Sale of RD$ 500 on credit
    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-CREDIT',
        'subtotal' => 500,
        'total' => 500,
        'payment_status' => 'pending',
        'issued_at' => now(),
    ]);

    // Add item with cost 200 to test gross profit
    $invoice->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => 500,
        'line_total' => 500,
        'total_cost_cents' => 20000,
    ]);

    $paymentService->recordInvoicePayments($invoice, [
        ['payment_method' => 'credit', 'amount' => 500],
    ], $user);

    expect((float) $customer->fresh()->balance)->toBe(500.0);

    $summary = $dashboardService->getSummary($shop);

    // Profit is 500 - 200 = 300
    expect($summary['period']['net_sales'])->toBe(500.0)
        ->and($summary['period']['gross_profit'])->toBe(300.0);

    // Cash flow inflow is 0 because credit generates no cash inflow
    expect($summary['cash_flow']['inflows']['sales_cash'])->toBe(0.0)
        ->and($summary['cash_flow']['net_cash_flow'])->toBe(0.0)
        ->and($summary['current_state']['receivable_total'])->toBe(500.0);
});

test('operating expense is deducted from gross profit to determine operating profit', function () {
    [$user, $shop, $product] = financialFixture();
    $expenseService = app(ExpenseService::class);
    $dashboardService = app(BusinessDashboardService::class);

    // 1. Generate Net Sales = 1,000, Cost = 400 -> Gross Profit = 600
    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $user->id,
        'invoice_number' => 'INV-EXP-TEST',
        'subtotal' => 1000,
        'total' => 1000,
        'payment_status' => 'paid',
        'issued_at' => now(),
    ]);
    $invoice->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 2,
        'unit_price' => 500,
        'line_total' => 1000,
        'total_cost_cents' => 40000,
    ]);

    // 2. Record Operating Expense = RD$ 150 (Electricity)
    $cat = ExpenseCategory::create(['shop_id' => $shop->id, 'name' => 'Electricidad', 'is_active' => true]);
    $expenseService->recordExpense($shop, $user, [
        'expense_category_id' => $cat->id,
        'description' => 'Pago de luz',
        'amount' => 150,
        'payment_method' => 'bank_transfer',
        'occurred_at' => now()->toDateString(),
    ]);

    $summary = $dashboardService->getSummary($shop);

    // Gross profit = 1000 - 400 = 600
    expect($summary['period']['gross_profit'])->toBe(600.0);
    // Operating expenses = 150
    expect($summary['period']['operating_expenses'])->toBe(150.0);
    // Operating profit = 600 - 150 = 450
    expect($summary['period']['operating_profit'])->toBe(450.0);
});

test('cash register closing correctly computes difference when shortage exists', function () {
    [$user, $shop, $product] = financialFixture();
    $cashService = app(CashRegisterService::class);

    // Open with RD$ 2,000
    $session = $cashService->openSession($shop, $user, 2000, 'Turno tarde');

    // Register cash in of RD$ 500
    $cashService->recordMovement($session, $user, 'cash_in', 500, 'Cambio extra');

    // Register expense of RD$ 200
    $cashService->recordMovement($session, $user, 'cash_out', 200, 'Pago delivery');

    // Expected: 2000 + 500 - 200 = 2300 (230,000 cents)
    expect($session->calculateExpectedBalance())->toBe(230000);

    // Physically counted: RD$ 2,250 (Faltante de RD$ 50)
    $closed = $cashService->closeSession($session, $user, 2250, 'Faltan RD$ 50 por cambio errado');

    expect($closed->status)->toBe('closed')
        ->and($closed->expected_closing_amount_cents)->toBe(230000)
        ->and($closed->counted_closing_amount_cents)->toBe(225000)
        ->and($closed->difference_cents)->toBe(-5000);
});

test('owner withdrawal decreases cash flow and cash register but does not decrease operating profit', function () {
    [$user, $shop, $product] = financialFixture();
    $cashService = app(CashRegisterService::class);
    $dashboardService = app(BusinessDashboardService::class);

    $session = $cashService->openSession($shop, $user, 5000, 'Apertura');

    // Sale: Revenue 1,000, Cost 400 -> Gross Profit 600
    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $user->id,
        'invoice_number' => 'INV-OWNER-TEST',
        'subtotal' => 1000,
        'total' => 1000,
        'payment_status' => 'paid',
        'issued_at' => now(),
    ]);
    $invoice->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 2,
        'unit_price' => 500,
        'line_total' => 1000,
        'total_cost_cents' => 40000,
    ]);
    app(PaymentService::class)->recordInvoicePayments($invoice, [
        ['payment_method' => 'cash', 'amount' => 1000],
    ], $user);

    // Owner withdraws RD$ 1,500
    $cashService->recordMovement($session, $user, 'owner_withdrawal', 1500, 'Retiro personal');

    $summary = $dashboardService->getSummary($shop);

    // Operating profit remains unaffected: 600
    expect($summary['period']['operating_profit'])->toBe(600.0);

    // Cash flow accounts for withdrawal
    expect($summary['cash_flow']['outflows']['cash_out'])->toBe(1500.0);
});
