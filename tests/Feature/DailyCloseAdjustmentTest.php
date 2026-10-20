<?php

use App\Models\{CashMovement, CustomerAccountEntry, DailyClosureAdjustment, Invoice, Shop, ShopMember, ShopSeller, User};
use App\Services\{BusinessDashboardService, DailyCloseService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['plan' => 'pro']);
    $this->shop = Shop::factory()->for($this->owner)->create(['business_type' => 'perfume_store']);
    $this->cash = 10000;
    $this->mock(BusinessDashboardService::class)->shouldReceive('getSummary')->andReturnUsing(fn () => [
        'cash_flow' => ['inflows' => ['sales_cash_cents' => $this->cash], 'outflows' => []],
    ]);
    $this->closure = app(DailyCloseService::class)->close($this->shop, $this->owner, now()->toDateString(), '95.00', 'QA original');
    $this->original = $this->closure->fresh()->getAttributes();
    $this->cash = 12000;
    $this->url = '/api/v1/shops/'.$this->shop->public_id.'/finance/day-close/'.$this->closure->public_id.'/adjustments';
    $this->payload = ['client_operation_uuid' => (string) Str::uuid(), 'reason' => 'QA nuevo arqueo revisado', 'counted_cash' => '125.00'];
    Sanctum::actingAs($this->owner, ['*']);
});

test('an audited reconciliation adjustment appends actor reason and monetary snapshot without cash movements', function () {
    $response = $this->postJson($this->url, $this->payload)->assertCreated()
        ->assertJsonPath('adjustment.closure_id', $this->closure->public_id)
        ->assertJsonPath('adjustment.user_id', (string) $this->owner->id)
        ->assertJsonPath('adjustment.reason', $this->payload['reason'])
        ->assertJsonPath('adjustment.expected_cash', '120.00')
        ->assertJsonPath('adjustment.counted_cash', '125.00')
        ->assertJsonPath('adjustment.difference', '5.00')
        ->assertJsonPath('adjustment.change_from_original', '20.00');
    expect($response->json('adjustment.created_at'))->not->toBeEmpty();
    expect($this->closure->fresh()->getAttributes())->toBe($this->original)
        ->and(DailyClosureAdjustment::count())->toBe(1)->and(CashMovement::count())->toBe(0)
        ->and(Invoice::count())->toBe(0)->and(CustomerAccountEntry::count())->toBe(0);
});

test('adjustment retries preserve their snapshot and reject changed payload under the same UUID', function () {
    $first = $this->postJson($this->url, $this->payload)->assertCreated()->json('adjustment');
    $this->cash = 20000;
    $this->postJson($this->url, array_replace($this->payload, ['counted_cash' => '125']))->assertCreated()->assertJsonPath('adjustment', $first);
    $this->postJson($this->url, array_replace($this->payload, ['reason' => 'QA different']))->assertConflict();
    expect(DailyClosureAdjustment::count())->toBe(1)->and($this->closure->fresh()->getAttributes())->toBe($this->original);
});

test('no new count remains null and a later audited count keeps the prior adjustment', function () {
    $first = $this->postJson($this->url, array_replace($this->payload, ['counted_cash' => null]))->assertCreated()
        ->assertJsonPath('adjustment.counted_cash', null)->assertJsonPath('adjustment.difference', null)->json('adjustment');
    $second = $this->postJson($this->url, array_replace($this->payload, ['client_operation_uuid' => (string) Str::uuid()]))->assertCreated();
    expect($second->json('adjustment.previous_id'))->toBe($first['id'])->and(DailyClosureAdjustment::count())->toBe(2);
});

test('delegated daily close and accountant roles do not inherit adjustment authority', function () {
    foreach (['seller', 'accountant'] as $role) {
        $actor = User::factory()->create();
        if ($role === 'seller') ShopSeller::create(['shop_id' => $this->shop->id, 'user_id' => $actor->id, 'is_active' => true,
            'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions' => ['day_close']]);
        else ShopMember::create(['shop_id' => $this->shop->id, 'user_id' => $actor->id, 'is_active' => true, 'role' => 'accountant']);
        Sanctum::actingAs($actor, ['*']);
        $this->postJson($this->url, $this->payload)->assertForbidden();
    }
    expect(DailyClosureAdjustment::count())->toBe(0);
});

test('adjustment replay revalidates permission and never resolves a closure through another tenant', function () {
    $this->postJson($this->url, $this->payload)->assertCreated();
    $this->shop->update(['enabled_menu_keys' => ['finance']]);
    $this->postJson($this->url, $this->payload)->assertForbidden();
    $other = Shop::factory()->for($this->owner)->create();
    $this->postJson('/api/v1/shops/'.$other->public_id.'/finance/day-close/'.$this->closure->public_id.'/adjustments', $this->payload)->assertNotFound();
    expect(DailyClosureAdjustment::count())->toBe(1)->and($this->closure->fresh()->getAttributes())->toBe($this->original);
});

test('a reason is mandatory and malformed counts do not create adjustments', function () {
    $this->postJson($this->url, array_replace($this->payload, ['reason' => ' ']))->assertUnprocessable()->assertJsonValidationErrors('reason');
    foreach (['-1.00', '1..2', '1.001'] as $amount) {
        $this->postJson($this->url, array_replace($this->payload, ['counted_cash' => $amount]))->assertUnprocessable()->assertJsonValidationErrors('counted_cash');
    }
    expect(DailyClosureAdjustment::count())->toBe(0);
});

test('mobile adjustment acknowledgements revalidate author permission and payload without appending twice', function () {
    $id = (string) Str::uuid();
    $body = ['type' => 'day_close_adjustment', 'client_operation_uuid' => $id, 'resource_id' => $this->closure->public_id,
        'request' => $this->payload + ['client_operation_uuid' => $id]];
    $body['request']['client_operation_uuid'] = $id;
    $url = '/api/v1/shops/'.$this->shop->public_id.'/mobile-operations';
    $first = $this->postJson($url, $body)->assertCreated()->json('adjustment');
    $this->postJson($url, $body)->assertCreated()->assertJsonPath('adjustment.id', $first['id']);
    $manager = User::factory()->create();
    ShopMember::create(['shop_id' => $this->shop->id, 'user_id' => $manager->id, 'role' => 'manager', 'is_active' => true]);
    Sanctum::actingAs($manager, ['*']);
    $this->postJson($url, $body)->assertForbidden();
    Sanctum::actingAs($this->owner, ['*']);
    $this->shop->update(['enabled_menu_keys' => ['finance']]);
    $this->postJson($url, $body)->assertForbidden();
    expect(DailyClosureAdjustment::count())->toBe(1);
});

test('audited records are append only and the web form uses the same guarded service', function () {
    $this->actingAs($this->owner)->post(route('seller.shops.daily-close.adjust', [$this->shop, $this->closure->public_id]), $this->payload)->assertRedirect();
    $row = DailyClosureAdjustment::sole();
    expect(fn () => $row->update(['reason' => 'overwrite']))->toThrow(LogicException::class);
    expect(fn () => $row->delete())->toThrow(LogicException::class);
    expect($row->fresh()->reason)->toBe($this->payload['reason'])->and($this->closure->fresh()->getAttributes())->toBe($this->original);
    $timezone = app(\App\Services\ShopOperationalSettingsService::class)->forShop($this->shop)['timezone'];
    $this->get(route('seller.shops.feature', [$this->shop, 'feature' => 'day_close']))->assertOk()
        ->assertSee('Registrar ajuste auditado')->assertSee($this->payload['reason'])
        ->assertSee($row->created_at->timezone($timezone)->format('d/m/Y H:i'))
        ->assertSee($this->closure->closed_at->timezone($timezone)->format('d/m/Y H:i'))
        ->assertDontSee('T00:00:00.000000Z');
});

test('schema rollback refuses to discard existing reconciliation audit history', function () {
    $this->postJson($this->url, $this->payload)->assertCreated();
    $migration = require database_path('migrations/2026_10_19_180000_create_daily_closure_adjustments_table.php');
    expect(fn () => $migration->down())->toThrow(LogicException::class);
    expect(DailyClosureAdjustment::count())->toBe(1)->and($this->closure->fresh()->getAttributes())->toBe($this->original);
});
