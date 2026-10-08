<?php

use App\Enums\UserPlan;
use App\Models\BusinessPartner;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PartnerTransaction;
use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\PaymentService;
use App\Services\SellerMenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('cash canonical contract reconciles real movements and closing without losing web aliases', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $service = app(CashRegisterService::class);
    $session = $service->openSession($shop, $owner, '5000.00');
    foreach (['sale' => '10000.00', 'customer_payment' => '2000.00', 'expense' => '1500.00', 'cash_out' => '500.00'] as $type => $amount) {
        $service->recordMovement($session, $owner, $type, $amount);
    }
    $token = $owner->createToken('contract', ['*'])->plainTextToken;
    $response = $this->withToken($token)->getJson("/api/v1/shops/{$shop->public_id}/cash-sessions/current")->assertOk();
    $fixture = json_decode(file_get_contents(base_path('docs/api-contracts/cash-current-session.json')), true, 512, JSON_THROW_ON_ERROR);
    foreach ($fixture['session']['summary'] as $key => $value) {
        $response->assertJsonPath("session.summary.{$key}", $value);
    }
    $closed = $service->closeSession($session, $owner, '15000.00');
    expect((int) $closed->difference_cents)->toBe(0);
});

test('partial expense ACK refreshes authoritative balances and replay never pays twice', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $token = $owner->createToken('contract', ['*'])->plainTextToken;
    $base = "/api/v1/shops/{$shop->public_id}";
    $created = $this->withToken($token)->postJson("{$base}/expenses", [
        'description' => 'Electricidad', 'amount' => '10000.00', 'paid_amount' => '4000.00',
        'payment_method' => 'cash', 'category_name' => 'Servicios', 'client_operation_uuid' => (string) Str::uuid(),
    ])->assertCreated()->assertJsonPath('expense.amount_paid', 4000)->assertJsonPath('expense.unpaid_amount', 6000);
    $id = $created->json('expense.id');
    $summary = $this->withToken($token)->getJson("{$base}/finance/summary")->assertOk()
        ->assertJsonPath('period.operating_expenses', 10000)
        ->assertJsonPath('cash_flow.outflows.expenses_paid', 4000);
    $fixture = json_decode(file_get_contents(base_path('docs/api-contracts/finance-summary.json')), true, 512, JSON_THROW_ON_ERROR);
    foreach (['period', 'current_state', 'income_statement'] as $section) {
        expect(array_diff(array_keys($fixture[$section]), array_keys($summary->json($section))))->toBe([]);
    }
    $payload = ['amount' => '3000.00', 'payment_method' => 'cash', 'client_operation_uuid' => (string) Str::uuid()];
    $first = $this->withToken($token)->postJson("{$base}/expenses/{$id}/payments", $payload)
        ->assertCreated()->assertJsonPath('expense.amount_paid', 7000)->assertJsonPath('expense.unpaid_amount', 3000)
        ->assertJsonPath('client_operation_uuid', $payload['client_operation_uuid']);
    $this->withToken($token)->postJson("{$base}/expenses/{$id}/payments", $payload)->assertCreated()->assertExactJson($first->json());
    $this->withToken($token)->postJson("{$base}/expenses/{$id}/payments", array_replace($payload, ['amount' => '2000.00']))->assertConflict();
});

test('expense categories API seeds defaults once and remains tenant-scoped', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $otherOwner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $otherShop = Shop::factory()->create(['user_id' => $otherOwner->id]);
    $token = $owner->createToken('categories-contract', ['*'])->plainTextToken;
    $otherToken = $otherOwner->createToken('categories-contract', ['*'])->plainTextToken;
    $url = "/api/v1/shops/{$shop->public_id}/expense-categories";

    $first = $this->withToken($token)->getJson($url)
        ->assertOk()
        ->assertJsonFragment(['name' => 'Alquiler', 'is_active' => true]);

    $second = $this->withToken($token)->getJson($url)->assertOk();

    expect($first->json())->toHaveCount(count($second->json()))
        ->and($shop->expenseCategories()->count())->toBe(count($first->json()))
        ->and($otherShop->expenseCategories()->count())->toBe(0);

    auth()->forgetGuards();
    $otherResponse = $this->withToken($otherToken)->getJson($url);
    expect($otherResponse->status())->toBeIn([403, 404]);
});

test('partners API requires an open cash session and links transactions to cash', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $token = $owner->createToken('partners-contract', ['*'])->plainTextToken;
    $base = "/api/v1/shops/{$shop->public_id}";

    $created = $this->withToken($token)->postJson("{$base}/partners", [
        'name' => 'Socio API',
        'ownership_percent' => '50.0000',
    ])->assertCreated()->assertJsonPath('partner.name', 'Socio API');

    $partnerId = $created->json('partner.id');
    expect(BusinessPartner::where('shop_id', $shop->id)->count())->toBe(1);

    $this->withToken($token)->postJson("{$base}/partners/{$partnerId}/transactions", [
        'type' => 'contribution',
        'amount' => '1000.00',
    ])->assertUnprocessable();
    expect(PartnerTransaction::where('shop_id', $shop->id)->count())->toBe(0);

    app(CashRegisterService::class)->openSession($shop, $owner, '0.00');
    $this->withToken($token)->postJson("{$base}/partners/{$partnerId}/transactions", [
        'type' => 'contribution',
        'amount' => '1000.00',
        'notes' => 'Capital inicial',
    ])->assertOk();

    $transaction = PartnerTransaction::where('shop_id', $shop->id)->sole();
    expect($transaction->partner_id)->toBe(BusinessPartner::where('shop_id', $shop->id)->sole()->id)
        ->and($transaction->cash_movement_id)->not->toBeNull()
        ->and((float) $shop->cashMovements()->where('type', 'owner_contribution')->sum('amount'))->toBe(1000.0);
});

test('financial menus enforce explicit independent delegation and legacy null never grants finance', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $seller = User::factory()->create();
    $assignment = ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $seller->id,
        'commission_type' => 'percentage', 'commission_value' => 0, 'is_active' => true, 'menu_permissions' => []]);
    $token = $seller->createToken('contract', ['*'])->plainTextToken;
    $base = "/api/v1/shops/{$shop->public_id}";
    foreach (['finance/summary', 'expenses', 'cash-sessions/current'] as $path) {
        $this->withToken($token)->getJson("{$base}/{$path}")->assertForbidden();
    }
    $assignment->update(['menu_permissions' => ['finance']]);
    $this->withToken($token)->getJson("{$base}/finance/summary")->assertOk();
    $this->withToken($token)->getJson("{$base}/expenses")->assertForbidden();
    expect(app(SellerMenuService::class)->normalize(null))->not->toContain('finance');
});

test('debt collection ACK allocates oldest invoices and affects cash exactly once', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $customer = Customer::create(['shop_id' => $shop->id, 'name' => 'Contrato', 'credit_limit' => '10000.00']);
    $cash = app(CashRegisterService::class);
    $session = $cash->openSession($shop, $owner, '0.00');
    foreach (['1000.00', '2000.00'] as $index => $total) {
        $invoice = Invoice::create(['shop_id' => $shop->id, 'user_id' => $owner->id, 'customer_id' => $customer->id,
            'invoice_number' => 'FAC-00'.($index + 1), 'subtotal' => $total, 'discount' => 0, 'tax' => 0,
            'total' => $total, 'status' => 'pending', 'issued_at' => now()->subDays(2 - $index)]);
        app(PaymentService::class)->processInvoicePayments($shop, $invoice, $owner, [], $total, $customer);
    }
    $token = $owner->createToken('contract', ['*'])->plainTextToken;
    $payload = ['client_transaction_uuid' => (string) Str::uuid(), 'amount' => '1500.00', 'payment_method' => 'cash'];
    $url = "/api/v1/shops/{$shop->public_id}/customers/{$customer->public_id}/payments";
    $first = $this->withToken($token)->postJson($url, $payload)->assertCreated()
        ->assertJsonPath('customer_balance', '1500.00')
        ->assertJsonPath('allocations.0.allocated_cents', 100000)
        ->assertJsonPath('allocations.0.remaining_invoice_balance', '0.00')
        ->assertJsonPath('allocations.1.allocated_cents', 50000)
        ->assertJsonPath('allocations.1.remaining_invoice_balance', '1500.00')
        ->assertJsonPath('cash_register_affected', true);
    $this->withToken($token)->postJson($url, $payload)->assertCreated()->assertExactJson($first->json());
    expect($cash->getSessionSummary($session)['debt_collections_cash'])->toBe(1500.0);
    expect($session->movements()->count())->toBe(1);
});
