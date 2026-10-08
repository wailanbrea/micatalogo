<?php

use App\Enums\UserPlan;
use App\Models\CashMovement;
use App\Models\DailyClosure;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\DailyCloseService;
use App\Services\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createIdempotencyHardenedFixture(): array
{
    $proOwner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->create(['user_id' => $proOwner->id]);

    $sellerUser = User::factory()->create(['plan' => UserPlan::Free]);
    ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $sellerUser->id,
        'role' => 'seller',
        'commission_type' => 'percentage',
        'commission_value' => 0,
        'is_active' => true,
    ]);

    $category = ExpenseCategory::create([
        'shop_id' => $shop->id,
        'name' => 'Suministros',
        'is_active' => true,
    ]);

    return [$proOwner, $sellerUser, $shop, $category];
}

test('expense and expense payment client_operation_uuid are valid standard UUIDs without invalid suffixes', function () {
    [$proOwner, $sellerUser, $shop, $category] = createIdempotencyHardenedFixture();
    $expenseService = app(ExpenseService::class);

    $expenseUuid = (string) Str::uuid();
    $expense = $expenseService->recordExpense($shop, $proOwner, [
        'expense_category_id' => $category->id,
        'description' => 'Compra de papel térmico',
        'amount' => '1000.00',
        'paid_amount' => '400.00',
        'payment_status' => 'partial',
        'payment_method' => 'cash',
        'client_operation_uuid' => $expenseUuid,
    ]);

    expect(Str::isUuid($expense->client_operation_uuid))->toBeTrue()
        ->and($expense->client_operation_uuid)->toBe($expenseUuid);

    // Initial partial payment created with expense
    $initialPayment = $expense->payments()->first();
    expect($initialPayment)->not->toBeNull()
        ->and(Str::isUuid($initialPayment->client_operation_uuid))->toBeTrue()
        ->and($initialPayment->client_operation_uuid)->not->toContain('-pay');

    // Subsequent partial payment with its own client UUID
    $subsequentUuid = (string) Str::uuid();
    $subsequentPayment = $expenseService->recordExpensePayment($shop, $expense, $proOwner, [
        'amount' => '300.00',
        'payment_method' => 'cash',
        'client_operation_uuid' => $subsequentUuid,
    ]);

    expect(Str::isUuid($subsequentPayment->client_operation_uuid))->toBeTrue()
        ->and($subsequentPayment->client_operation_uuid)->toBe($subsequentUuid)
        ->and($subsequentPayment->client_operation_uuid)->not->toContain('-pay');
});

test('expense payment replay with same UUID is idempotent and returns original payment', function () {
    [$proOwner, $sellerUser, $shop, $category] = createIdempotencyHardenedFixture();
    $expenseService = app(ExpenseService::class);

    $expense = $expenseService->recordExpense($shop, $proOwner, [
        'expense_category_id' => $category->id,
        'description' => 'Servicio de Internet',
        'amount' => '2000.00',
        'paid_amount' => '0.00',
        'payment_status' => 'pending',
        'payment_method' => 'bank_transfer',
    ]);

    $paymentUuid = (string) Str::uuid();
    $payload = [
        'amount' => '500.00',
        'payment_method' => 'bank_transfer',
        'client_operation_uuid' => $paymentUuid,
    ];
    $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

    // First attempt
    $payment1 = $expenseService->recordExpensePayment($shop, $expense, $proOwner, $payload, $paymentUuid, $payloadHash);

    // Replay attempt with same payload and hash
    $payment2 = $expenseService->recordExpensePayment($shop, $expense, $proOwner, $payload, $paymentUuid, $payloadHash);

    expect($payment1->id)->toBe($payment2->id)
        ->and($expense->fresh()->payments()->count())->toBe(1)
        ->and((float) $expense->fresh()->amount_paid)->toBe(500.00);
});

test('expense payment with same UUID but conflicting payload throws 409 Conflict', function () {
    [$proOwner, $sellerUser, $shop, $category] = createIdempotencyHardenedFixture();
    $expenseService = app(ExpenseService::class);

    $expense = $expenseService->recordExpense($shop, $proOwner, [
        'expense_category_id' => $category->id,
        'description' => 'Electricidad',
        'amount' => '3000.00',
        'paid_amount' => '0.00',
        'payment_status' => 'pending',
        'payment_method' => 'bank_transfer',
    ]);

    $paymentUuid = (string) Str::uuid();
    $payloadA = [
        'amount' => '1000.00',
        'payment_method' => 'bank_transfer',
        'client_operation_uuid' => $paymentUuid,
    ];
    $hashA = hash('sha256', json_encode($payloadA, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

    $expenseService->recordExpensePayment($shop, $expense, $proOwner, $payloadA, $paymentUuid, $hashA);

    // Conflicting replay with different amount
    $payloadB = [
        'amount' => '1500.00',
        'payment_method' => 'bank_transfer',
        'client_operation_uuid' => $paymentUuid,
    ];
    $hashB = hash('sha256', json_encode($payloadB, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

    expect(fn () => $expenseService->recordExpensePayment($shop, $expense, $proOwner, $payloadB, $paymentUuid, $hashB))
        ->toThrow(InvalidArgumentException::class);
});

test('closing cash register session is idempotent upon exact replay but rejects different counted amount with 409', function () {
    [$proOwner, $sellerUser, $shop] = createIdempotencyHardenedFixture();
    $cashService = app(CashRegisterService::class);

    $session = $cashService->openSession($shop, $proOwner, '1000.00');

    $closeUuid = (string) Str::uuid();
    $closePayloadA = [
        'counted_amount' => '1000.00',
        'notes' => 'Cierre cuadre perfecto',
        'client_operation_uuid' => $closeUuid,
    ];
    $hashA = hash('sha256', json_encode($closePayloadA, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

    // 1. Close session
    $closedSession = $cashService->closeSession($session, $proOwner, '1000.00', 'Cierre cuadre perfecto', $closeUuid, $hashA);
    expect($closedSession->status)->toBe('closed');

    // 2. Replay close with same counted amount and hash -> returns closed session idempotently
    $replaySession = $cashService->closeSession($closedSession, $proOwner, '1000.00', 'Cierre cuadre perfecto', $closeUuid, $hashA);
    expect($replaySession->id)->toBe($closedSession->id);

    // 3. Attempt to close with DIFFERENT counted amount -> throws 409 Conflict
    $closePayloadB = [
        'counted_amount' => '1200.00',
        'notes' => 'Intento diferente',
        'client_operation_uuid' => $closeUuid,
    ];
    $hashB = hash('sha256', json_encode($closePayloadB, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

    expect(fn () => $cashService->closeSession($closedSession, $proOwner, '1200.00', 'Intento diferente', $closeUuid, $hashB))
        ->toThrow(InvalidArgumentException::class);
});

test('cash movement replay is idempotent after the session lock is acquired', function () {
    [$proOwner, , $shop] = createIdempotencyHardenedFixture();
    $cashService = app(CashRegisterService::class);
    $session = $cashService->openSession($shop, $proOwner, '100.00');
    $uuid = (string) Str::uuid();
    $hash = hash('sha256', 'qa-cash-movement-replay');

    $first = $cashService->recordManualMovement(
        $session,
        $proOwner,
        'cash_in',
        '50.00',
        'Entrada repetible',
        $uuid,
        $hash,
    );
    $replay = $cashService->recordManualMovement(
        $session,
        $proOwner,
        'cash_in',
        '50.00',
        'Entrada repetible',
        $uuid,
        $hash,
    );

    expect($replay->id)->toBe($first->id)
        ->and(CashMovement::query()->where('cash_register_session_id', $session->id)->count())->toBe(1)
        ->and((int) CashMovement::query()->whereKey($first->id)->value('amount_cents'))->toBe(5000);
});

test('daily close replay is serialized by shop and rejects a conflicting count', function () {
    [$proOwner, , $shop] = createIdempotencyHardenedFixture();
    $service = app(DailyCloseService::class);
    $date = now()->toDateString();

    $first = $service->close($shop, $proOwner, $date, '0.00', 'Cierre QA');
    $replay = $service->close($shop, $proOwner, $date, '0.00', 'Cierre QA');

    expect($replay->id)->toBe($first->id)
        ->and(DailyClosure::query()->where('shop_id', $shop->id)->whereDate('business_date', $date)->count())->toBe(1);

    expect(fn () => $service->close($shop, $proOwner, $date, '1.00', 'Conflicto QA'))
        ->toThrow(InvalidArgumentException::class);
});

test('seller without shop owner or membership permissions cannot view business financial dashboard', function () {
    [$proOwner, $sellerUser, $shop] = createIdempotencyHardenedFixture();

    // Regular seller should be blocked by can:viewFinance policy
    $this->actingAs($sellerUser)
        ->get(route('seller.shops.business', $shop))
        ->assertForbidden();

    // Owner can access financial dashboard
    $this->actingAs($proOwner)
        ->get(route('seller.shops.business', $shop))
        ->assertOk();
});

test('shops on free, basic and pro plans can access the expense module', function () {
    $freeOwner = User::factory()->create(['plan' => UserPlan::Free]);
    $freeShop = Shop::factory()->create(['user_id' => $freeOwner->id]);

    $basicOwner = User::factory()->create(['plan' => UserPlan::Premium]);
    $basicShop = Shop::factory()->create(['user_id' => $basicOwner->id]);

    $proOwner = User::factory()->create(['plan' => UserPlan::Pro]);
    $proShop = Shop::factory()->create(['user_id' => $proOwner->id]);

    // Free plan accessing expenses web index is allowed.
    $this->actingAs($freeOwner)
        ->get(route('seller.shops.expenses.index', $freeShop))
        ->assertOk();

    // Free plan accessing expenses API index is also allowed.
    $freeToken = $freeOwner->createToken('test', ['*'])->plainTextToken;
    $this->withToken($freeToken)
        ->getJson("/api/v1/shops/{$freeShop->public_id}/expenses")
        ->assertOk();

    // Basic plan can access expenses through both web and API.
    $this->actingAs($basicOwner)
        ->get(route('seller.shops.expenses.index', $basicShop))
        ->assertOk();
    $basicToken = $basicOwner->createToken('test', ['*'])->plainTextToken;
    $this->withToken($basicToken)
        ->getJson("/api/v1/shops/{$basicShop->public_id}/expenses")
        ->assertOk();

    // Pro plan accessing expenses web index is allowed
    $this->actingAs($proOwner)
        ->get(route('seller.shops.expenses.index', $proShop))
        ->assertOk();
});

test('expense page safely renders payment method config with nested labels', function () {
    [$proOwner, , $shop] = createIdempotencyHardenedFixture();
    ExpenseCategory::create([
        'shop_id' => $shop->id,
        'name' => 'Pruebas de configuración',
        'is_active' => true,
    ]);
    config()->set('catalog.payment_methods', [
        'cash' => ['label' => 'Efectivo'],
        'card' => ['label' => ['configuración inválida']],
    ]);

    $this->actingAs($proOwner)
        ->get(route('seller.shops.expenses.index', $shop))
        ->assertOk()
        ->assertSee('Efectivo')
        ->assertSee('Card');
});
