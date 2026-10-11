<?php

use App\Enums\UserPlan;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenantOwner = User::factory()->create(['plan' => UserPlan::Pro]);
    $this->tenantA = Shop::factory()->for($this->tenantOwner)->create(['name' => 'QA A']);
    $this->tenantB = Shop::factory()->for($this->tenantOwner)->create(['name' => 'QA B']);
    $this->withToken($this->tenantOwner->createToken('qa-both-shops', ['*'])->plainTextToken);
});

test('an owner of both shops cannot read or collect the other shop customer through this shop', function () {
    $customer = Customer::create(['shop_id' => $this->tenantB->id, 'name' => 'Sólo B', 'balance' => '100.00', 'credit_limit' => '200.00']);
    $base = "/api/v1/shops/{$this->tenantA->public_id}/customers/{$customer->public_id}";

    $this->getJson($base)->assertNotFound();
    $this->postJson("{$base}/payments", [
        'client_transaction_uuid' => (string) Str::uuid(),
        'amount' => '1.00',
        'payment_method' => 'cash',
    ])->assertNotFound();

    expect($customer->fresh()->balance)->toBe('100.00')
        ->and(CustomerAccountEntry::query()->count())->toBe(0)
        ->and(CashMovement::query()->count())->toBe(0);
    $this->getJson("/api/v1/shops/{$this->tenantA->public_id}/customers")->assertOk()->assertJsonCount(0, 'customers');
    $this->getJson("/api/v1/shops/{$this->tenantB->public_id}/customers")->assertOk()->assertJsonCount(1, 'customers');
});

test('cross shop POS product and customer identifiers cannot change invoices stock or balances', function () {
    $productA = Product::factory()->for($this->tenantA)->create(['price' => '10.00', 'sale_unit' => 'unit']);
    $productB = Product::factory()->for($this->tenantB)->create(['price' => '10.00', 'sale_unit' => 'unit']);
    foreach ([$productA, $productB] as $product) {
        ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 5, 'sold_quantity' => 0]);
    }
    $customerB = Customer::create(['shop_id' => $this->tenantB->id, 'name' => 'Crédito B', 'credit_limit' => '100.00']);
    $base = "/api/v1/shops/{$this->tenantA->public_id}/pos-sales";
    $body = [
        'client_sale_uuid' => (string) Str::uuid(),
        'payment_status' => 'paid',
        'items' => [['product_id' => $productB->public_id, 'quantity' => 1, 'unit_price' => '10.00']],
    ];
    $this->postJson($base, $body)->assertUnprocessable();
    $body['client_sale_uuid'] = (string) Str::uuid();
    $body['items'][0]['product_id'] = $productA->public_id;
    $body['customer_id'] = $customerB->public_id;
    $body['credit_amount'] = '10.00';
    $this->postJson($base, $body)->assertUnprocessable();

    expect(Invoice::query()->count())->toBe(0)
        ->and(InventoryMovement::query()->count())->toBe(0)
        ->and(CustomerAccountEntry::query()->count())->toBe(0)
        ->and($productA->fresh()->inventory->stock_quantity)->toBe(5)
        ->and($productB->fresh()->inventory->stock_quantity)->toBe(5)
        ->and($customerB->fresh()->balance)->toBe('0.00');
});

test('a cash session from the other owned shop cannot be closed or receive a movement', function () {
    $session = CashRegisterSession::create([
        'shop_id' => $this->tenantB->id, 'user_id' => $this->tenantOwner->id,
        'opened_at' => now(), 'opening_amount' => '25.00', 'opening_amount_cents' => 2500,
        'status' => 'open', 'is_open_flag' => 1,
    ]);
    $base = "/api/v1/shops/{$this->tenantA->public_id}/cash-sessions/{$session->public_id}";
    $this->postJson("{$base}/movements", [
        'client_operation_uuid' => (string) Str::uuid(), 'type' => 'cash_in', 'amount' => '1.00', 'notes' => 'Intento cruzado',
    ])->assertNotFound();
    $this->postJson("{$base}/close", [
        'client_operation_uuid' => (string) Str::uuid(), 'counted_amount' => '25.00',
    ])->assertNotFound();
    expect($session->fresh()->status)->toBe('open')
        ->and($session->fresh()->opening_amount_cents)->toBe(2500)
        ->and(CashMovement::query()->count())->toBe(0);
});

test('customer idempotency keys are scoped per shop even for the same owner', function () {
    $body = ['client_customer_uuid' => (string) Str::uuid(), 'name' => 'Cliente QA', 'phone' => '', 'credit_limit' => '100.00'];
    $first = $this->postJson("/api/v1/shops/{$this->tenantA->public_id}/customers", $body)->assertCreated()->json();
    $second = $this->postJson("/api/v1/shops/{$this->tenantB->public_id}/customers", $body)->assertCreated()->json();
    expect($first['id'])->not->toBe($second['id']);
    $this->postJson("/api/v1/shops/{$this->tenantA->public_id}/customers", $body)->assertCreated()->assertExactJson($first);
    $this->postJson("/api/v1/shops/{$this->tenantB->public_id}/customers", $body)->assertCreated()->assertExactJson($second);
    expect(Customer::query()->count())->toBe(2);
});

test('mobile edits use a scoped remote identity and explicit inactive customers remain visible', function () {
    $customerA = Customer::create(['shop_id' => $this->tenantA->id, 'name' => 'Cliente web A', 'credit_limit' => '100.00']);
    $customerB = Customer::create(['shop_id' => $this->tenantB->id, 'name' => 'Cliente web B', 'credit_limit' => '100.00']);
    $body = [
        'client_operation_uuid' => (string) Str::uuid(), 'type' => 'customer_upsert',
        'client_customer_uuid' => (string) Str::uuid(), 'customer_id' => $customerA->public_id,
        'name' => 'Cliente A inactivo', 'credit_limit' => '100.00', 'is_active' => false, 'is_deleted' => false,
    ];
    $url = "/api/v1/shops/{$this->tenantA->public_id}/mobile-operations";
    $first = $this->postJson($url, $body)->assertCreated()->assertJsonPath('customer_id', $customerA->public_id)->json();
    $this->postJson($url, $body)->assertCreated()->assertExactJson($first);
    expect($customerA->fresh()->is_active)->toBeFalse()
        ->and($customerA->fresh()->deleted_at)->toBeNull()
        ->and(Customer::withTrashed()->count())->toBe(2);
    $this->getJson("/api/v1/shops/{$this->tenantA->public_id}/customers")->assertOk()
        ->assertJsonCount(1, 'customers')->assertJsonPath('customers.0.is_active', false);
    $body['client_operation_uuid'] = (string) Str::uuid();
    $body['customer_id'] = $customerB->public_id;
    $this->postJson($url, $body)->assertNotFound();
    expect($customerB->fresh()->name)->toBe('Cliente web B')
        ->and(\App\Models\MobileOperation::query()->count())->toBe(1);
});
