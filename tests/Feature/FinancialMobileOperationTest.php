<?php

use App\Enums\UserPlan;
use App\Models\CashRegisterSession;
use App\Models\MobileOperation;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function financialEnvelope(string $type, array $request, ?string $resource = null): array
{
    $id = (string) Str::uuid();
    return ['type' => $type, 'client_operation_uuid' => $id, 'request' => $request + ['client_operation_uuid' => $id]]
        + ($resource ? ['resource_id' => $resource] : []);
}

beforeEach(function () {
    $this->owner = User::factory()->create(['plan' => UserPlan::Premium]);
    $this->shop = Shop::factory()->create(['user_id' => $this->owner->id]);
    $this->other = Shop::factory()->create(['user_id' => $this->owner->id]);
    $this->url = '/api/v1/shops/'.$this->shop->public_id.'/mobile-operations';
    Sanctum::actingAs($this->owner, ['pos:write']);
});

test('financial envelopes open move and close cash exactly once with authoritative receipts', function () {
    $open = financialEnvelope('cash_open', ['opening_amount' => '100.00']);
    $response = $this->postJson($this->url, $open)->assertCreated()->assertJsonPath('client_operation_uuid', $open['client_operation_uuid']);
    $session = $response->json('session.id');
    $this->postJson($this->url, $open)->assertCreated()->assertJsonPath('session.id', $session);
    $movement = financialEnvelope('cash_movement', ['type' => 'cash_in', 'amount' => '20.00', 'notes' => 'Sólo QA'], $session);
    $this->postJson($this->url, $movement)->assertCreated();
    $this->postJson($this->url, $movement)->assertCreated();
    expect($this->shop->cashRegisterSessions()->count())->toBe(1);
    $close = financialEnvelope('cash_close', ['counted_amount' => '120.00'], $session);
    $this->postJson($this->url, $close)->assertCreated()->assertJsonPath('session.status', 'closed');
    $this->postJson($this->url, $close)->assertCreated();
    expect(MobileOperation::count())->toBe(3)->and(CashRegisterSession::first()->expected_closing_amount_cents)->toBe(12000);
});

test('a pending expense and its payment replay once and remain in the expense read model', function () {
    $expense = financialEnvelope('expense_create', ['description' => 'Gasto QA', 'category_name' => 'Operación QA', 'amount' => '10.00', 'payment_status' => 'pending', 'paid_amount' => '0.00']);
    $id = $this->postJson($this->url, $expense)->assertCreated()->json('expense.id');
    $this->postJson($this->url, $expense)->assertCreated();
    $payment = financialEnvelope('expense_payment', ['amount' => '10.00', 'payment_method' => 'bank_transfer'], $id);
    $this->postJson($this->url, $payment)->assertCreated();
    $this->postJson($this->url, $payment)->assertCreated();
    $this->getJson('/api/v1/shops/'.$this->shop->public_id.'/expenses')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.payment_status', 'paid');
    expect($this->shop->expenses()->count())->toBe(1)->and(MobileOperation::count())->toBe(2);
});

test('financial validation conflict and cross shop requests never commit a false acknowledgement', function () {
    $invalid = financialEnvelope('expense_create', ['description' => 'Inválido', 'amount' => '-1.00']);
    $this->postJson($this->url, $invalid)->assertUnprocessable();
    expect(MobileOperation::count())->toBe(0)->and($this->shop->expenses()->count())->toBe(0);
    $open = financialEnvelope('cash_open', ['opening_amount' => '10.00']);
    $session = $this->postJson($this->url, $open)->assertCreated()->json('session.id');
    $changed = $open; $changed['request']['opening_amount'] = '20.00';
    $this->postJson($this->url, $changed)->assertConflict();
    $move = financialEnvelope('cash_movement', ['type' => 'cash_in', 'amount' => '1.00', 'notes' => 'QA'], $session);
    $this->postJson('/api/v1/shops/'.$this->other->public_id.'/mobile-operations', $move)->assertNotFound();
    expect(MobileOperation::count())->toBe(1);
    $move['request']['client_operation_uuid'] = (string) Str::uuid();
    $this->postJson($this->url, $move)->assertUnprocessable();
    expect(MobileOperation::count())->toBe(1);
});
