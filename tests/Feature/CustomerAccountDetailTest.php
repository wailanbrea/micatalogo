<?php

use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Services\CustomerAccountService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('customer detail explains an outstanding order invoice and payment allocations without changing data', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $customer = $shop->customers()->create(['name' => 'Cliente historial', 'credit_limit' => '1000.00']);
    $invoice = Invoice::create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'invoice_number' => 'DETAIL-600',
        'status' => 'pending', 'channel' => 'whatsapp', 'subtotal' => '600.00', 'total' => '600.00', 'issued_at' => now()]);
    $order = $shop->orders()->create(['order_number' => 'ORDER-DETAIL', 'customer_name' => $customer->name,
        'delivery_type' => 'pickup', 'currency' => 'DOP', 'subtotal' => '600.00', 'total' => '600.00',
        'status' => 'confirmed', 'invoice_id' => $invoice->id]);
    app(CustomerAccountService::class)->recordInvoiceCharge($customer, $invoice, '600.00', $owner->id);
    app(PaymentService::class)->recordCustomerDebtPayment($shop, $customer->fresh(), $owner, '200.00',
        'bank_transfer', (string) Str::uuid(), 'detail-read-test');
    $token = $owner->createToken('detail-qa', ['customers:read'])->plainTextToken;
    $before = [$customer->fresh()->balance, CustomerAccountEntry::count(), $invoice->fresh()->status];

    $this->withToken($token)->getJson("/api/v1/shops/{$shop->public_id}/customers/{$customer->public_id}")->assertOk()
        ->assertJsonPath('customer.balance', '400.00')
        ->assertJsonPath('debt_origins.0.invoice_number', 'DETAIL-600')
        ->assertJsonPath('debt_origins.0.order_number', $order->order_number)
        ->assertJsonPath('debt_origins.0.paid_amount', '200.00')
        ->assertJsonPath('debt_origins.0.outstanding_amount', '400.00')
        ->assertJsonPath('unallocated_balance', '0.00')
        ->assertJsonPath('entries.0.type', 'payment')
        ->assertJsonPath('entries.0.amount', '-200.00')
        ->assertJsonPath('entries.0.allocations.0.invoice_number', 'DETAIL-600')
        ->assertJsonPath('entries.0.allocations.0.amount', '200.00')
        ->assertJsonPath('entries_has_more', false);
    expect([$customer->fresh()->balance, CustomerAccountEntry::count(), $invoice->fresh()->status])->toBe($before);
});

test('customer detail reports unallocated manual debt and preserves inactive customers', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create();
    $customer = $shop->customers()->create(['name' => 'Cliente inactivo', 'balance' => '75.00', 'is_active' => false]);
    $this->withToken($owner->createToken('detail', ['customers:read'])->plainTextToken)
        ->getJson("/api/v1/shops/{$shop->public_id}/customers/{$customer->public_id}")->assertOk()
        ->assertJsonPath('customer.is_active', false)->assertJsonPath('unallocated_balance', '75.00')
        ->assertJsonCount(0, 'debt_origins')->assertJsonCount(0, 'entries');
});

test('debt attribution never exceeds the authoritative balance and history truncation is explicit', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create();
    $customer = $shop->customers()->create(['name' => 'Historial antiguo', 'balance' => '30.00']);
    Invoice::create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'invoice_number' => 'OLD-100',
        'status' => 'pending', 'channel' => 'web', 'subtotal' => '100.00', 'total' => '100.00', 'issued_at' => now()]);
    foreach (range(1, 101) as $index) {
        $customer->accountEntries()->create(['shop_id' => $shop->id, 'type' => 'adjustment', 'amount' => '1.00',
            'balance_after' => '30.00', 'notes' => "Histórico $index"]);
    }
    $this->withToken($owner->createToken('detail', ['customers:read'])->plainTextToken)
        ->getJson("/api/v1/shops/{$shop->public_id}/customers/{$customer->public_id}")->assertOk()
        ->assertJsonPath('debt_origins.0.outstanding_amount', '30.00')
        ->assertJsonPath('entries_has_more', true)->assertJsonCount(100, 'entries');
});

test('customer detail cannot disclose another owned shops movements', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $a = Shop::factory()->for($owner)->create();
    $b = Shop::factory()->for($owner)->create();
    $customer = $b->customers()->create(['name' => 'Sólo B', 'balance' => '90.00']);
    $this->withToken($owner->createToken('detail', ['customers:read'])->plainTextToken)
        ->getJson("/api/v1/shops/{$a->public_id}/customers/{$customer->public_id}")->assertNotFound();
    expect($customer->fresh()->balance)->toBe('90.00');
});
