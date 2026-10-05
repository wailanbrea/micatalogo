<?php

use App\Enums\UserPlan;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use App\Services\BusinessDashboardService;
use App\Services\CashRegisterService;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createCustomerAllocationFixture(): array
{
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'price' => 1000.00,
        'sale_unit' => 'unit',
    ]);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 100,
        'sold_quantity' => 0,
    ]);

    $customer = Customer::create([
        'shop_id' => $shop->id,
        'name' => 'Comercial Dominicana',
        'credit_limit' => '10000.00',
        'balance' => '0.00',
    ]);

    return [$owner, $shop, $customer, $product];
}

function createUnpaidInvoice(Shop $shop, Customer $customer, Product $product, float $total, Carbon $dueDate, string $status = 'pending'): Invoice
{
    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-'.strtoupper(Str::random(8)),
        'channel' => 'pos',
        'status' => $status,
        'currency' => 'DOP',
        'subtotal' => Money::toDecimal(Money::toCents($total)),
        'discount' => '0.00',
        'tax' => '0.00',
        'total' => Money::toDecimal(Money::toCents($total)),
        'credit_amount' => Money::toDecimal(Money::toCents($total)),
        'due_date' => $dueDate,
        'issued_at' => $dueDate->copy()->subDays(15),
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => Money::toDecimal(Money::toCents($total)),
        'line_total' => Money::toDecimal(Money::toCents($total)),
        'discount' => '0.00',
        'tax' => '0.00',
    ]);

    $customer->increment('balance', $total);

    CustomerAccountEntry::create([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'invoice_id' => $invoice->id,
        'type' => 'charge',
        'amount' => Money::toDecimal(Money::toCents($total)),
        'balance_after' => Money::toDecimal(Money::toCents($customer->fresh()->balance)),
        'reference' => 'Factura inicial',
        'occurred_at' => $invoice->issued_at,
    ]);

    return $invoice;
}

test('single customer debt payment amortizes invoice, creates InvoicePayment link, and reduces aging', function () {
    [$owner, $shop, $customer, $product] = createCustomerAllocationFixture();
    $paymentService = app(PaymentService::class);
    $dashboardService = app(BusinessDashboardService::class);

    $invoice = createUnpaidInvoice($shop, $customer, $product, 1000.00, now()->subDays(10));

    expect((float) $customer->fresh()->balance)->toBe(1000.00);

    $entry = $paymentService->recordCustomerDebtPayment($shop, $customer, $owner, 400.00, 'cash', 'Abono parcial');

    expect((float) $customer->fresh()->balance)->toBe(600.00)
        ->and($invoice->fresh()->status)->toBe('partial')
        ->and($invoice->payments()->count())->toBe(1);

    $invPayment = $invoice->payments()->first();
    expect((float) $invPayment->amount)->toBe(400.00)
        ->and($invPayment->customer_account_entry_id)->toBe($entry['payment_id']);

    $summary = $dashboardService->getSummary($shop, now()->startOfMonth()->toDateString(), now()->toDateString());
    $aging = $summary['current_state']['aging'];

    expect((float) $aging['days_0_30'])->toBe(600.00)
        ->and((float) $aging['invoices_total'])->toBe(600.00)
        ->and((float) $aging['total_receivable'])->toBe(600.00)
        ->and((float) $aging['reconciliation_difference'])->toBe(0.00);
});

test('full customer debt payment marks invoice as paid and clears aging balance', function () {
    [$owner, $shop, $customer, $product] = createCustomerAllocationFixture();
    $paymentService = app(PaymentService::class);
    $dashboardService = app(BusinessDashboardService::class);

    $invoice = createUnpaidInvoice($shop, $customer, $product, 1000.00, now()->subDays(10));

    $paymentService->recordCustomerDebtPayment($shop, $customer, $owner, 1000.00, 'cash', 'Pago total');

    expect((float) $customer->fresh()->balance)->toBe(0.00)
        ->and($invoice->fresh()->status)->toBe('paid');

    $summary = $dashboardService->getSummary($shop, now()->startOfMonth()->toDateString(), now()->toDateString());
    $aging = $summary['current_state']['aging'];

    expect((float) $aging['days_0_30'])->toBe(0.00)
        ->and((float) $aging['invoices_total'])->toBe(0.00)
        ->and((float) $aging['total_receivable'])->toBe(0.00)
        ->and((float) $aging['reconciliation_difference'])->toBe(0.00);
});

test('fifo multi-invoice allocation amortizes older invoices first and matches aging buckets', function () {
    [$owner, $shop, $customer, $product] = createCustomerAllocationFixture();
    $paymentService = app(PaymentService::class);
    $dashboardService = app(BusinessDashboardService::class);

    // Invoice A: 1,000 due 45 days ago (31-60 days bucket)
    $invoiceA = createUnpaidInvoice($shop, $customer, $product, 1000.00, now()->subDays(45));
    // Invoice B: 2,000 due 10 days ago (0-30 days bucket)
    $invoiceB = createUnpaidInvoice($shop, $customer, $product, 2000.00, now()->subDays(10));

    expect((float) $customer->fresh()->balance)->toBe(3000.00);

    // Pay 1,500: should pay 1,000 to Invoice A (full) and 500 to Invoice B (leaving 1,500 pending)
    $paymentService->recordCustomerDebtPayment($shop, $customer, $owner, 1500.00, 'bank_transfer', 'Transferencia FIFO');

    expect((float) $customer->fresh()->balance)->toBe(1500.00)
        ->and($invoiceA->fresh()->status)->toBe('paid')
        ->and($invoiceB->fresh()->status)->toBe('partial');

    expect($invoiceA->payments()->count())->toBe(1)
        ->and((float) $invoiceA->payments()->first()->amount)->toBe(1000.00);

    expect($invoiceB->payments()->count())->toBe(1)
        ->and((float) $invoiceB->payments()->first()->amount)->toBe(500.00);

    $summary = $dashboardService->getSummary($shop, now()->startOfMonth()->toDateString(), now()->toDateString());
    $aging = $summary['current_state']['aging'];

    expect((float) $aging['days_31_60'])->toBe(0.00)
        ->and((float) $aging['days_0_30'])->toBe(1500.00)
        ->and((float) $aging['invoices_total'])->toBe(1500.00)
        ->and((float) $aging['total_receivable'])->toBe(1500.00)
        ->and((float) $aging['reconciliation_difference'])->toBe(0.00);
});

test('cash debt payment creates a cash movement in open cash register while bank transfer does not', function () {
    [$owner, $shop, $customer, $product] = createCustomerAllocationFixture();
    $cashService = app(CashRegisterService::class);
    $paymentService = app(PaymentService::class);

    $session = $cashService->openSession($shop, $owner, '500.00');

    $invoice1 = createUnpaidInvoice($shop, $customer, $product, 1000.00, now()->subDays(5));

    // 1. Pay 300 via bank transfer -> should NOT create cash movement
    $paymentService->recordCustomerDebtPayment($shop, $customer, $owner, 300.00, 'bank_transfer', 'Transferencia');
    expect($session->movements()->count())->toBe(0);

    // 2. Pay 400 in cash -> MUST create exactly 1 cash movement in open register
    $paymentService->recordCustomerDebtPayment($shop, $customer, $owner, 400.00, 'cash', 'Efectivo');
    expect($session->movements()->count())->toBe(1);

    $movement = $session->movements()->first();
    expect($movement->type)->toBe('customer_payment')
        ->and((float) $movement->amount)->toBe(400.00);

    // Session expected balance: 500 opening + 400 = 900
    expect((float) $session->calculateExpectedBalance() / 100)->toBe(900.00);
});

test('api customer debt payment endpoint executes fifo allocation and returns response', function () {
    [$owner, $shop, $customer, $product] = createCustomerAllocationFixture();
    createUnpaidInvoice($shop, $customer, $product, 1000.00, now()->subDays(5));

    $token = $owner->createToken('BSPOS', ['customers:write'])->plainTextToken;

    $response = $this->withToken($token)
        ->postJson("/api/v1/shops/{$shop->public_id}/customers/{$customer->public_id}/payments", [
            'amount' => '450.00',
            'payment_method' => 'card',
            'client_transaction_uuid' => (string) Str::uuid(),
            'reference' => 'VOUCHER-9988',
        ])
        ->assertCreated()
        ->assertJsonPath('amount', '450.00')
        ->assertJsonPath('customer_balance', '550.00')
        ->assertJsonPath('payment_method', 'card');

    expect($response->json('allocations'))->toHaveCount(1)
        ->and($response->json('allocations.0.allocated_amount'))->toBe('450.00')
        ->and($response->json('allocations.0.remaining_invoice_balance'))->toBe('550.00')
        ->and($response->json('allocations.0.invoice_status'))->toBe('partial');
});

test('web customer debt payment executes fifo allocation and updates balance', function () {
    [$owner, $shop, $customer, $product] = createCustomerAllocationFixture();
    $invoice = createUnpaidInvoice($shop, $customer, $product, 500.00, now()->subDays(5));

    $this->actingAs($owner)
        ->post(route('seller.shops.customers.payment', [$shop, $customer]), [
            'amount' => '500.00',
            'payment_method' => 'cash',
            'reference' => 'RECIBO-001',
        ])
        ->assertRedirect();

    expect((float) $customer->fresh()->balance)->toBe(0.00)
        ->and($invoice->fresh()->status)->toBe('paid');
});
