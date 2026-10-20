<?php

use App\Models\DailyClosure;
use App\Models\Shop;
use App\Models\User;
use App\Services\BusinessDashboardService;
use App\Services\DailyCloseService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('replaying a closed day preserves its author count notes timestamp and monetary snapshot', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create();
    $this->mock(BusinessDashboardService::class)->shouldReceive('getSummary')->once()->andReturn([
        'cash_flow' => ['inflows' => ['sales_cash_cents' => 10000], 'outflows' => []],
    ]);
    $service = app(DailyCloseService::class);
    $date = now()->toDateString();
    $first = $service->close($shop, $owner, $date, '95.00', 'Arqueo original');
    $attributes = $first->fresh()->getAttributes();
    $this->travel(1)->hours();
    $retry = $service->close($shop, User::factory()->create(), $date, null, 'Nota diferente');
    expect($retry->getAttributes())->toBe($attributes)
        ->and($retry->counted_cash_cents)->toBe(9500)
        ->and($retry->expected_cash_cents)->toBe(10000)
        ->and(DailyClosure::count())->toBe(1);
});

test('an existing closure without a count cannot be silently changed into a counted closure', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create();
    $service = app(DailyCloseService::class);
    $date = now()->toDateString();
    $first = $service->close($shop, $owner, $date);
    expect(fn () => $service->close($shop, $owner, $date, '0.00'))->toThrow(InvalidArgumentException::class);
    expect($first->fresh()->counted_cash_cents)->toBeNull();
});

test('daily reconciliation permits late idempotent sales and collections while preserving the original snapshot', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-10-19 09:00:00'));
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create(['business_type' => 'perfume_store']);
    $product = \App\Models\Product::factory()->for($shop)->create(['sale_unit' => 'unit', 'price' => '100.00', 'moderation_status' => 'active']);
    \App\Models\ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 10, 'cost_price' => '20.00']);
    $customer = \App\Models\Customer::create(['shop_id' => $shop->id, 'name' => 'QA_Reconciliation_Customer', 'credit_limit' => '1000.00', 'balance' => '0.00']);
    \Laravel\Sanctum\Sanctum::actingAs($owner, ['*']);
    $base = '/api/v1/shops/'.$shop->public_id;
    $sale = ['client_sale_uuid' => (string) \Illuminate\Support\Str::uuid(), 'payment_status' => 'paid', 'credit_amount' => '0.00',
        'tax' => '0.00', 'discount' => '0.00', 'payments' => [['method' => 'cash', 'amount' => '100.00']],
        'items' => [['product_id' => $product->public_id, 'quantity' => 1, 'unit_price' => '100.00']]];
    $this->postJson($base.'/pos-sales', array_replace($sale, ['customer_id' => $customer->public_id, 'payment_status' => 'pending',
        'credit_amount' => '100.00', 'payments' => []]))->assertCreated();
    $sale['client_sale_uuid'] = (string) \Illuminate\Support\Str::uuid();
    $this->postJson($base.'/pos-sales', $sale)->assertCreated();
    $this->travelTo(\Carbon\Carbon::parse('2026-10-19 10:00:00'));
    $date = now()->toDateString();
    $close = ['date' => $date, 'counted_cash' => '95.00', 'notes' => 'QA original reconciliation'];
    $this->postJson($base.'/finance/day-close', $close)->assertOk();
    $closure = DailyClosure::where('shop_id', $shop->id)->firstOrFail();
    $original = $closure->getAttributes();
    expect($closure->expected_cash_cents)->toBe(10000)->and($closure->difference_cents)->toBe(-500);
    $this->travelTo(\Carbon\Carbon::parse('2026-10-19 11:00:00'));
    $sale['client_sale_uuid'] = (string) \Illuminate\Support\Str::uuid();
    $this->postJson($base.'/pos-sales', $sale)->assertCreated();
    $this->postJson($base.'/pos-sales', $sale)->assertCreated();
    $payment = ['client_transaction_uuid' => (string) \Illuminate\Support\Str::uuid(), 'amount' => '25.00', 'payment_method' => 'cash',
        'notes' => 'QA late collection after reconciliation'];
    $this->postJson($base.'/customers/'.$customer->public_id.'/payments', $payment)->assertCreated();
    $this->postJson($base.'/customers/'.$customer->public_id.'/payments', $payment)->assertCreated();
    $current = $this->getJson($base.'/finance/day-close?date='.$date)->assertOk();
    expect(\App\Support\Money::toCents((string) $current->json('expected_cash')))->toBe(22500)
        ->and(\App\Support\Money::toCents((string) $current->json('closure.expected_cash')))->toBe(10000)
        ->and($closure->fresh()->getAttributes())->toBe($original)
        ->and($customer->fresh()->balance)->toBe('75.00')
        ->and($product->inventory->fresh()->stock_quantity)->toBe(7)
        ->and($shop->invoices()->count())->toBe(3);
    $entry = \App\Models\CustomerAccountEntry::where('client_transaction_uuid', $payment['client_transaction_uuid'])->sole();
    expect($entry->user_id)->toBe($owner->id)->and($entry->notes)->toBe($payment['notes'])
        ->and($entry->created_at->greaterThan($closure->closed_at))->toBeTrue();
    $this->postJson($base.'/finance/day-close', array_replace($close, ['notes' => 'QA retry must not rewrite']))->assertOk();
    expect($closure->fresh()->getAttributes())->toBe($original)->and(DailyClosure::where('shop_id', $shop->id)->count())->toBe(1);
});
