<?php

use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function navigationCatalogActor(array $keys, bool $ownerOnly = false): array
{
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create(['business_type' => 'perfume_store', 'enabled_menu_keys' => $ownerOnly ? $keys : null]);
    $actor = $ownerOnly ? $owner : User::factory()->create();
    if (! $ownerOnly) {
        ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $actor->id, 'is_active' => true,
            'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions' => $keys]);
    }
    $physical = Product::factory()->for($shop)->create(['name' => 'QA_NAV_PHYSICAL_SECRET', 'price' => '900.00', 'sale_unit' => 'bottle', 'volume_ml' => 100]);
    ProductInventory::create(['product_id' => $physical->id, 'track_inventory' => true, 'stock_quantity' => 3, 'cost_price' => '240.00']);
    $service = Product::factory()->for($shop)->create(['name' => 'QA_NAV_SERVICE_ALLOWED', 'price' => '150.00', 'sale_unit' => 'service']);
    ProductInventory::create(['product_id' => $service->id, 'track_inventory' => false, 'stock_quantity' => 0, 'cost_price' => '32.17']);

    return [$shop, $actor, $physical, $service];
}

function navigationServiceForm(array $overrides = []): array
{
    return $overrides + ['name' => 'QA_NAV_NewService', 'price' => '150.00', 'sale_unit' => 'service',
        'availability_status' => 'available', 'moderation_status' => 'active'];
}

test('products only owner cannot forge service create edit or archive', function () {
    [$shop, $owner, $physical, $service] = navigationCatalogActor(['products'], true);
    $this->actingAs($owner)->postJson(route('seller.shops.products.store', $shop), navigationServiceForm())->assertForbidden();
    $this->get(route('seller.shops.products.edit', [$shop, $service]))->assertForbidden();
    $this->putJson(route('seller.shops.products.update', [$shop, $service]), navigationServiceForm())->assertForbidden();
    $this->deleteJson(route('seller.shops.products.destroy', [$shop, $service]))->assertForbidden();
    expect($shop->products()->count())->toBe(2)->and($service->fresh()->trashed())->toBeFalse();
});

test('services only owner cannot forge physical create or change a physical product into a service', function () {
    [$shop, $owner, $physical] = navigationCatalogActor(['services'], true);
    $this->actingAs($owner)->postJson(route('seller.shops.products.store', $shop), navigationServiceForm(['sale_unit' => 'unit']))->assertForbidden();
    $this->putJson(route('seller.shops.products.update', [$shop, $physical]), navigationServiceForm())->assertForbidden();
    expect($physical->fresh()->sale_unit)->toBe('bottle')->and($physical->inventory->fresh()->stock_quantity)->toBe(3);
});

test('services only can create and edit a service without seeing physical source data or changing stock', function () {
    [$shop, $seller, $physical, $service] = navigationCatalogActor(['services']);
    $this->actingAs($seller)->get(route('seller.shops.products.create', [$shop, 'sale_unit' => 'service']))->assertOk()
        ->assertDontSee('QA_NAV_PHYSICAL_SECRET')->assertDontSee('240.00');
    $this->post(route('seller.shops.products.store', $shop), navigationServiceForm())->assertRedirect();
    $this->put(route('seller.shops.products.update', [$shop, $service]), navigationServiceForm(['name' => 'QA_NAV_ServiceEdited']))->assertRedirect();
    expect($service->fresh()->name)->toBe('QA_NAV_ServiceEdited')
        ->and($service->inventory->fresh()->cost_price)->toBe('32.17')
        ->and($physical->inventory->fresh()->stock_quantity)->toBe(3);
});

test('catalog snapshot with services only does not return physical products and products only does not return services', function (string $permission, string $expectedName, string $forbiddenName) {
    [$shop, $seller] = navigationCatalogActor([$permission]);
    $token = $seller->createToken('nav-catalog-read', ['catalog:read'])->plainTextToken;
    $response = $this->withToken($token)->getJson('/api/v1/shops/'.$shop->public_id.'/catalog')->assertOk();
    expect(collect($response->json('products'))->pluck('name')->all())->toBe([$expectedName]);
    expect(json_encode($response->json('products'), JSON_THROW_ON_ERROR))->not->toContain($forbiddenName);
})->with([['services', 'QA_NAV_SERVICE_ALLOWED', 'QA_NAV_PHYSICAL_SECRET'], ['products', 'QA_NAV_PHYSICAL_SECRET', 'QA_NAV_SERVICE_ALLOWED']]);

test('service delegation permits mobile service metadata but never a physical operation or a hidden cost change', function () {
    [$shop, $seller, $physical, $service] = navigationCatalogActor(['services']);
    Sanctum::actingAs($seller, ['pos:write']);
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $this->postJson($url, ['type' => 'product_upsert', 'client_operation_uuid' => (string) Str::uuid(),
        'product_id' => $service->public_id, 'name' => 'QA_NAV_MobileServiceEdited', 'price' => '150.00', 'expected_price' => '150.00', 'sale_unit' => 'service'])->assertCreated();
    $this->postJson($url, ['type' => 'product_upsert', 'client_operation_uuid' => (string) Str::uuid(),
        'product_id' => $physical->public_id, 'name' => 'QA_NAV_Forbidden', 'sale_unit' => 'service'])->assertForbidden();
    $this->postJson($url, ['type' => 'product_upsert', 'client_operation_uuid' => (string) Str::uuid(),
        'product_id' => $service->public_id, 'sale_unit' => 'service', 'cost_price' => '99.00'])->assertForbidden();
    expect($physical->inventory->fresh()->stock_quantity)->toBe(3)->and($service->inventory->fresh()->cost_price)->toBe('32.17');
});

test('legacy products delegation does not silently gain mutation authority', function () {
    [$shop, $seller, $physical] = navigationCatalogActor(['sales', 'products', 'printers']);
    Sanctum::actingAs($seller, ['pos:write']);
    $this->postJson('/api/v1/shops/'.$shop->public_id.'/mobile-operations', ['type' => 'product_archive',
        'client_operation_uuid' => (string) Str::uuid(), 'product_id' => $physical->public_id])->assertForbidden();
    expect($physical->fresh()->trashed())->toBeFalse();
});

test('product definitions cannot erase existing physical stock by converting into services', function () {
    [$shop, $owner, $physical] = navigationCatalogActor(['products', 'services'], true);
    $this->actingAs($owner)->putJson(route('seller.shops.products.update', [$shop, $physical]), navigationServiceForm())->assertUnprocessable();
    expect($physical->fresh()->sale_unit)->toBe('bottle')->and($physical->inventory->fresh()->stock_quantity)->toBe(3)
        ->and($physical->inventory->fresh()->track_inventory)->toBeTrue();
});

test('read only catalog users do not see financial cards or mutation links', function () {
    [$shop, $seller] = navigationCatalogActor(['products']);
    $this->actingAs($seller)->get(route('seller.shops.products.index', $shop))->assertOk()
        ->assertDontSee('Capital al costo')->assertDontSee('Margen promedio')->assertDontSee('720.00')
        ->assertDontSee('Nuevo combo')->assertDontSee('>Editar<', false);
});
