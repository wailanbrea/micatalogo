<?php

use App\Models\CashRegisterSession;
use App\Models\MobileOperation;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('disabled modules reject direct web mutations before validating or writing', function (string $routeName) {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create(['enabled_menu_keys' => []]);
    $this->actingAs($owner)->postJson(route($routeName, $shop), [])->assertForbidden();
    expect($shop->suppliers()->count())->toBe(0)
        ->and($shop->quotes()->count())->toBe(0)
        ->and($shop->products()->count())->toBe(0);
})->with([
    'seller.shops.quotes.store', 'seller.shops.suppliers.store', 'seller.shops.partners.store',
    'seller.shops.categories.store', 'seller.shops.products.bulk.store', 'seller.shops.products.import.preview',
    'seller.shops.products.import.store', 'seller.shops.pricing.bulk-rule',
]);

test('direct import API and daily close cannot bypass disabled menus through owner status', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create(['enabled_menu_keys' => []]);
    $this->withToken($owner->createToken('nav-boundary')->plainTextToken)
        ->postJson('/api/v1/shops/'.$shop->public_id.'/inventory-import', [])->assertForbidden();
    $this->postJson('/api/v1/shops/'.$shop->public_id.'/finance/day-close', [])->assertForbidden();
});

test('bulk import cannot attach a category owned by another shop', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $other = Shop::factory()->create();
    $category = $other->categories()->create(['name' => 'QA_NAV_Foreign', 'slug' => 'qa-nav-foreign', 'status' => 'active']);
    $this->actingAs($owner)->postJson(route('seller.shops.products.bulk.store', $shop), ['products' => [[
        'name' => 'QA_NAV_InvalidCategory', 'price' => '100.00', 'shop_category_id' => $category->id,
    ]]])->assertUnprocessable()->assertJsonValidationErrors('products.0.shop_category_id');
    expect(Product::where('shop_id', $shop->id)->count())->toBe(0);
});

test('allowed supplier mutation still succeeds without enabling a sibling module', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create(['enabled_menu_keys' => ['suppliers']]);
    $this->actingAs($owner)->post(route('seller.shops.suppliers.store', $shop), ['name' => 'QA_NAV_AllowedSupplier'])->assertRedirect();
    expect($shop->suppliers()->count())->toBe(1);
    $this->postJson(route('seller.shops.quotes.store', $shop), [])->assertForbidden();
});

test('revoked cash permission cannot recover a cached mobile acknowledgement', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    Sanctum::actingAs($owner, ['pos:write']);
    $id = (string) Str::uuid();
    $payload = ['type' => 'cash_open', 'client_operation_uuid' => $id,
        'request' => ['opening_amount' => '10.00', 'client_operation_uuid' => $id]];
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $this->postJson($url, $payload)->assertCreated();
    $shop->update(['enabled_menu_keys' => []]);
    $this->postJson($url, $payload)->assertForbidden();
    expect(MobileOperation::count())->toBe(1)->and(CashRegisterSession::count())->toBe(1);
    $shop->update(['enabled_menu_keys' => ['cash']]);
    $this->postJson($url, $payload)->assertCreated();
    expect(MobileOperation::count())->toBe(1)->and(CashRegisterSession::count())->toBe(1);
});

test('manager cannot replay an owner only administration acknowledgement', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $manager = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create();
    ShopMember::create(['shop_id' => $shop->id, 'user_id' => $manager->id, 'role' => 'manager', 'is_active' => true]);
    $id = (string) Str::uuid();
    $payload = ['type' => 'shop_menu_visibility_update', 'client_operation_uuid' => $id,
        'request' => ['enabled_menu_keys' => ['products'], 'client_operation_uuid' => $id]];
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    Sanctum::actingAs($owner, ['pos:write']);
    $this->postJson($url, $payload)->assertCreated();
    Sanctum::actingAs($manager, ['pos:write']);
    $this->postJson($url, $payload)->assertForbidden();
    expect(MobileOperation::count())->toBe(1);
});

test('day close delegation can read its operational totals without exposing finance', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create(['enabled_menu_keys' => ['day_close']]);
    $this->withToken($owner->createToken('day-close-only')->plainTextToken)
        ->getJson('/api/v1/shops/'.$shop->public_id.'/finance/day-close?date=2026-10-14')->assertOk()
        ->assertJsonMissingPath('income_statement')->assertJsonMissingPath('profitability');
    $this->getJson('/api/v1/shops/'.$shop->public_id.'/finance/summary')->assertForbidden();
});

test('cash permission does not permit replaying another actors cash receipt', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $manager = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create();
    ShopMember::create(['shop_id' => $shop->id, 'user_id' => $manager->id, 'role' => 'manager', 'is_active' => true]);
    $id = (string) Str::uuid();
    $payload = ['type' => 'cash_open', 'client_operation_uuid' => $id,
        'request' => ['opening_amount' => '10.00', 'client_operation_uuid' => $id]];
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    Sanctum::actingAs($owner, ['pos:write']);
    $this->postJson($url, $payload)->assertCreated();
    Sanctum::actingAs($manager, ['pos:write']);
    $this->postJson($url, $payload)->assertForbidden();
    expect(CashRegisterSession::count())->toBe(1)->and(MobileOperation::count())->toBe(1);
});
