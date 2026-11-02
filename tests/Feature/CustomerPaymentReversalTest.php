<?php

use App\Models\{CashMovement, Customer, CustomerAccountEntry, DailyClosureAdjustment, Invoice, InvoicePayment, Shop, ShopSeller, User};
use App\Services\{BusinessDashboardService, CashRegisterService, CustomerAccountService, CustomerPaymentReversalService, DailyCloseService, PaymentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function reversalFixture(string $method = 'cash', string $amount = '300.00'): array
{
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $customer = Customer::create(['shop_id' => $shop->id, 'name' => 'Cliente QA', 'credit_limit' => '10000.00', 'balance' => '0.00']);
    $invoice = Invoice::create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'invoice_number' => 'QA-'.Str::random(8),
        'channel' => 'pos', 'currency' => 'DOP', 'status' => 'pending', 'subtotal' => '1000.00', 'total' => '1000.00', 'issued_at' => now(), 'due_date' => now()->addDay()]);
    app(CustomerAccountService::class)->recordInvoiceCharge($customer, $invoice, '1000.00', $owner->id);
    $session = app(CashRegisterService::class)->openSession($shop, $owner, '100.00');
    $payment = app(PaymentService::class)->recordCustomerDebtPayment($shop, $customer, $owner, $amount, $method);
    return [$owner, $shop, $customer, $invoice, $session, $payment];
}

test('reversal restores debt invoices cash and reports while retaining original evidence', function () {
    [$owner,$shop,$customer,$invoice,$session,$paid] = reversalFixture();
    $original = InvoicePayment::sole()->getAttributes();
    $entry = app(CustomerPaymentReversalService::class)->reverse($shop,$customer,$paid['payment_id'],$owner,(string) Str::uuid(),'Monto registrado por error');
    expect($customer->fresh()->balance)->toBe('1000.00')->and($invoice->fresh()->status)->toBe('pending')
        ->and($invoice->payments()->sum('amount_cents'))->toBe(0)->and($entry->type)->toBe('payment_reversal')
        ->and($entry->reverses_entry_id)->toBe($paid['payment_id'])->and($entry->amount)->toBe('300.00');
    $stored = InvoicePayment::withoutGlobalScope('applied')->sole();
    expect((float) $stored->amount)->toBe((float) $original['amount'])->and($stored->amount_cents)->toBe($original['amount_cents'])->and($stored->reversed_at)->not->toBeNull()
        ->and(CashMovement::count())->toBe(2)->and($session->fresh()->calculateExpectedBalance())->toBe(10000);
    $summary = app(BusinessDashboardService::class)->getSummary($shop);
    expect((float) $summary['cash_flow']['inflows']['debt_collections'])->toBe(0.0)
        ->and((float) app(CashRegisterService::class)->getSessionSummary($session->fresh())['collections_cash'])->toBe(0.0);
});

test('reversing one payment retains later valid payments and supports several invoice allocations', function () {
    [$owner,$shop,$customer,$invoice,$session,$paid] = reversalFixture('card','1000.00');
    $second = Invoice::create(['shop_id'=>$shop->id,'customer_id'=>$customer->id,'invoice_number'=>'QA-2','status'=>'pending','channel'=>'pos','currency'=>'DOP','subtotal'=>'500.00','total'=>'500.00','issued_at'=>now(),'due_date'=>now()->addDays(2)]);
    app(CustomerAccountService::class)->recordInvoiceCharge($customer,$second,'500.00',$owner->id);
    app(CustomerPaymentReversalService::class)->reverse($shop,$customer,$paid['payment_id'],$owner,(string) Str::uuid(),'Error');
    $multi = app(PaymentService::class)->recordCustomerDebtPayment($shop,$customer,$owner,'1200.00','bank_transfer');
    expect($multi['allocations'])->toHaveCount(2);
    app(PaymentService::class)->recordCustomerDebtPayment($shop,$customer,$owner,'100.00','card');
    app(CustomerPaymentReversalService::class)->reverse($shop,$customer,$multi['payment_id'],$owner,(string) Str::uuid(),'Transferencia errónea');
    expect($customer->fresh()->balance)->toBe('1400.00')->and($invoice->fresh()->status)->toBe('pending')
        ->and($second->fresh()->status)->toBe('partial')->and($second->payments()->sum('amount_cents'))->toBe(10000)
        ->and(CashMovement::count())->toBe(0);
});

test('reversal of a closed day appends an audited reconciliation without rewriting original closes', function () {
    [$owner,$shop,$customer,$invoice,$session,$paid] = reversalFixture();
    app(CashRegisterService::class)->closeSession($session,$owner,'400.00');
    $sessionOriginal = $session->fresh()->getAttributes();
    $closure = app(DailyCloseService::class)->close($shop,$owner,now()->toDateString(),'300.00');
    $closureOriginal = $closure->fresh()->getAttributes();
    app(CustomerPaymentReversalService::class)->reverse($shop,$customer,$paid['payment_id'],$owner,(string) Str::uuid(),'Abono inexistente');
    expect($closure->fresh()->getAttributes())->toBe($closureOriginal)->and($session->fresh()->getAttributes())->toBe($sessionOriginal)
        ->and(DailyClosureAdjustment::count())->toBe(1)->and(DailyClosureAdjustment::sole()->expected_cash_cents)->toBe(0)
        ->and($session->fresh()->calculateExpectedBalance())->toBe(10000);
});

test('reversal retries are idempotent and duplicate or changed requests cannot restore debt twice', function () {
    [$owner,$shop,$customer,$invoice,$session,$paid] = reversalFixture();
    Sanctum::actingAs($owner,['*']);
    $url = "/api/v1/shops/{$shop->public_id}/customers/{$customer->public_id}/payments/{$paid['payment_id']}/reverse";
    $data = ['client_transaction_uuid'=>(string) Str::uuid(),'reason'=>'Duplicado'];
    $first = $this->postJson($url,$data)->assertOk()->assertJsonPath('payment.can_reverse',false)->assertJsonPath('customer.balance','1000.00')->json();
    $this->postJson($url,$data)->assertOk()->assertExactJson($first);
    $this->postJson($url,array_replace($data,['reason'=>'Otro']))->assertConflict();
    $this->postJson($url,array_replace($data,['client_transaction_uuid'=>(string) Str::uuid()]))->assertConflict();
    expect($customer->fresh()->balance)->toBe('1000.00')->and(CustomerAccountEntry::where('type','payment_reversal')->count())->toBe(1)->and(CashMovement::count())->toBe(2);
});

test('web history exposes authorized reversal and validates motive', function () {
    [$owner,$shop,$customer,$invoice,$session,$paid] = reversalFixture();
    $url = route('seller.shops.customers.payments.reverse',[$shop,$customer,'paymentId'=>$paid['payment_id']]);
    $this->actingAs($owner)->get(route('seller.shops.customers.payments',$shop))->assertOk()->assertSee('Anular abono');
    $this->post($url,['client_transaction_uuid'=>(string) Str::uuid(),'reason'=>' '])->assertSessionHasErrors('reason');
    $this->post($url,['client_transaction_uuid'=>(string) Str::uuid(),'reason'=>'Cliente equivocado'])->assertRedirect()->assertSessionHasNoErrors();
    $this->get(route('seller.shops.customers.payments',$shop))->assertOk()->assertSee('Anulado')->assertSee('Cliente equivocado');
    expect($customer->fresh()->balance)->toBe('1000.00');
});

test('history pagination includes reversals from other devices and never leaks another shop', function () {
    [$owner,$shop,$customer,$invoice,$session,$paid] = reversalFixture('bank_transfer');
    app(CustomerPaymentReversalService::class)->reverse($shop,$customer,$paid['payment_id'],$owner,(string) Str::uuid(),'Error');
    Sanctum::actingAs($owner,['customers:read']);
    $this->getJson("/api/v1/shops/{$shop->public_id}/customer-payments")->assertOk()->assertJsonCount(1,'payments')
        ->assertJsonPath('payments.0.reversal.reason','Error')->assertJsonPath('payments.0.can_reverse',false)->assertJsonPath('payments.0.payment_method','bank_transfer');
    $other = Shop::factory()->create();
    $this->getJson("/api/v1/shops/{$other->public_id}/customer-payments")->assertNotFound();
});

test('sellers read receipts but cannot reverse and another owner cannot target a foreign payment', function () {
    [$owner,$shop,$customer,$invoice,$session,$paid] = reversalFixture();
    $seller = User::factory()->create();
    ShopSeller::create(['shop_id'=>$shop->id,'user_id'=>$seller->id,'is_active'=>true,'commission_type'=>'percentage','commission_value'=>0,'menu_permissions'=>['collections']]);
    Sanctum::actingAs($seller,['*']);
    $url = "/api/v1/shops/{$shop->public_id}/customers/{$customer->public_id}/payments/{$paid['payment_id']}/reverse";
    $this->postJson($url,['client_transaction_uuid'=>(string) Str::uuid(),'reason'=>'Error'])->assertForbidden();
    [$otherOwner,$otherShop,$otherCustomer] = reversalFixture();
    Sanctum::actingAs($otherOwner,['*']);
    $this->postJson("/api/v1/shops/{$otherShop->public_id}/customers/{$otherCustomer->public_id}/payments/{$paid['payment_id']}/reverse",['client_transaction_uuid'=>(string) Str::uuid(),'reason'=>'Error'])->assertNotFound();
    expect($customer->fresh()->balance)->toBe('700.00');
});

test('restoring mistaken debt ignores the current credit limit and inactive status', function () {
    [$owner,$shop,$customer,$invoice,$session,$paid] = reversalFixture('card');
    $customer->forceFill(['credit_limit'=>'700.00','is_active'=>false])->save();
    app(CustomerPaymentReversalService::class)->reverse($shop,$customer,$paid['payment_id'],$owner,(string) Str::uuid(),'Error');
    expect($customer->fresh()->balance)->toBe('1000.00');
});
