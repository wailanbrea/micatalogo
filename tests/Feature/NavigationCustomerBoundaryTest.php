<?php

use App\Models\Customer;
use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('collection only reads and pays existing debt without customer or new credit authority', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create(['business_type' => 'professional_services']);
    $actor = User::factory()->create();
    ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $actor->id, 'is_active' => true,
        'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions' => ['collections']]);
    $customer = Customer::create(['shop_id' => $shop->id, 'name' => 'QA_NAV_ExistingDebt', 'credit_limit' => '500.00', 'balance' => '100.00']);
    $token = $actor->createToken('collections-only', ['customers:read', 'customers:write'])->plainTextToken;
    $base = '/api/v1/shops/'.$shop->public_id.'/customers';
    $this->withToken($token)->getJson($base)->assertOk()->assertJsonPath('customers.0.name', 'QA_NAV_ExistingDebt');
    $this->getJson($base.'/'.$customer->public_id)->assertOk();
    $payment = ['client_transaction_uuid' => (string) Str::uuid(), 'amount' => '25.00', 'payment_method' => 'cash'];
    $this->postJson($base.'/'.$customer->public_id.'/payments', $payment)->assertCreated();
    $this->postJson($base.'/'.$customer->public_id.'/payments', $payment)->assertCreated();
    expect($customer->fresh()->balance)->toBe('75.00');
    $this->postJson($base, [])->assertForbidden();
    $this->postJson($base.'/'.$customer->public_id.'/adjustments', [])->assertForbidden();
    $html = $this->actingAs($actor)->get(route('seller.shops.customers.index', [$shop, 'view' => 'collections']))->assertOk();
    $html->assertSee('Registrar cobro')->assertDontSee('Registrar crédito')->assertDontSee('Nuevo cliente');
});

test('customer definitions permission cannot register credit or collect money', function () {
    $shop = Shop::factory()->create();
    $actor = User::factory()->create();
    ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $actor->id, 'is_active' => true,
        'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions' => ['customers']]);
    $customer = Customer::create(['shop_id' => $shop->id, 'name' => 'QA_NAV_Profile', 'balance' => '100.00']);
    $token = $actor->createToken('customers-only', ['customers:read', 'customers:write'])->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/shops/'.$shop->public_id.'/customers')->assertOk();
    $this->postJson('/api/v1/shops/'.$shop->public_id.'/customers/'.$customer->public_id.'/payments', [])->assertForbidden();
    $this->postJson('/api/v1/shops/'.$shop->public_id.'/customers/'.$customer->public_id.'/adjustments', [])->assertForbidden();
    expect($customer->fresh()->balance)->toBe('100.00');
});

test('selling and collecting permissions do not issue new POS credit and replay revalidates credit permission', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create(['business_type' => 'perfume_store']);
    $actor = User::factory()->create();
    $assignment = ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $actor->id, 'is_active' => true,
        'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions' => ['sales', 'collections']]);
    $customer = Customer::create(['shop_id' => $shop->id, 'name' => 'QA_NAV_POSCredit', 'credit_limit' => '1000.00', 'balance' => '0.00']);
    $product = Product::factory()->for($shop)->create(['price' => '100.00', 'sale_unit' => 'unit']);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 3, 'cost_price' => '20.00']);
    $token = $actor->createToken('credit-boundary', ['pos:write'])->plainTextToken;
    $url = '/api/v1/shops/'.$shop->public_id.'/pos-sales';
    $payload = ['client_sale_uuid' => (string) Str::uuid(), 'payment_status' => 'pending',
        'customer_id' => $customer->public_id, 'credit_amount' => '100.00', 'payments' => [],
        'items' => [['product_id' => $product->public_id, 'quantity' => 1, 'unit_price' => '100.00']]];
    $this->withToken($token)->postJson($url, $payload)->assertForbidden();
    $this->postJson($url, array_replace($payload, ['payment_status' => 'paid', 'credit_amount' => '0.00',
        'payments' => [['method' => 'credit', 'amount' => '100.00']]]))->assertForbidden();
    expect($customer->fresh()->balance)->toBe('0.00')->and($product->inventory->fresh()->stock_quantity)->toBe(3)
        ->and(Invoice::count())->toBe(0);
    $assignment->update(['menu_permissions' => ['sales', 'credit']]);
    $this->postJson($url, $payload)->assertCreated();
    $assignment->update(['menu_permissions' => ['sales', 'collections']]);
    $this->postJson($url, $payload)->assertForbidden();
    $assignment->update(['menu_permissions' => ['sales', 'credit']]);
    $this->postJson($url, $payload)->assertCreated();
    expect($customer->fresh()->balance)->toBe('100.00')->and($product->inventory->fresh()->stock_quantity)->toBe(2)
        ->and(Invoice::count())->toBe(1);
    $assignment->update(['menu_permissions' => ['sales']]);
    $paid = array_replace($payload, ['client_sale_uuid' => (string) Str::uuid(), 'payment_status' => 'paid',
        'credit_amount' => '0.00', 'payments' => [['method' => 'cash', 'amount' => '100.00']]]);
    $this->postJson($url, $paid)->assertCreated();
    expect($customer->fresh()->balance)->toBe('100.00')->and(Invoice::count())->toBe(2);
});

test('web POS rejects new debt when a seller has only sales and collections', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create(['business_type' => 'perfume_store']);
    $actor = User::factory()->create();
    ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $actor->id, 'is_active' => true,
        'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions' => ['sales', 'collections']]);
    $customer = Customer::create(['shop_id' => $shop->id, 'name' => 'QA_NAV_WebCredit', 'credit_limit' => '1000.00', 'balance' => '0.00']);
    $product = Product::factory()->for($shop)->create(['price' => '100.00', 'sale_unit' => 'service']);
    $this->actingAs($actor)->get(route('seller.shops.pos', $shop))->assertOk()
        ->assertViewHas('presentation', fn (array $presentation): bool => $presentation['pos']['show_credit'] === false);
    $this->actingAs($actor)->postJson(route('seller.shops.pos.store', $shop), [
        'client_sale_uuid' => (string) Str::uuid(), 'payment_status' => 'pending', 'customer_id' => $customer->public_id,
        'credit_amount' => '100.00', 'items' => [['product_id' => $product->public_id, 'quantity' => 1, 'unit_price' => '100.00']],
    ])->assertForbidden();
    expect($customer->fresh()->balance)->toBe('0.00')->and(Invoice::count())->toBe(0);
});
