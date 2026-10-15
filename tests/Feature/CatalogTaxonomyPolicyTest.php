<?php

use App\Models\{Product, Shop, ShopCategory, ShopMember, ShopSeller, ShopSyncRecord, User};
use App\Services\CatalogTaxonomyAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function closureTaxonomyFixture(): array
{
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create(['business_type' => 'perfume_store']);
    $service = User::factory()->create();
    ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $service->id, 'is_active' => true,
        'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions' => ['services']]);
    $category = $shop->categories()->create(['name' => 'QA_CLOSURE_Service', 'slug' => 'qa-closure-service', 'status' => 'active']);
    ShopSyncRecord::create(['shop_id' => $shop->id, 'kind' => 'category', 'record_uuid' => (string) Str::uuid(),
        'payload' => ['scope' => 'service', 'server_id' => (string) $category->id]]);
    $unitId = (string) Str::uuid();
    ShopSyncRecord::create(['shop_id' => $shop->id, 'kind' => 'unit', 'record_uuid' => $unitId, 'payload' => ['scope' => 'service']]);
    return [$owner, $shop, $service, $category, $unitId];
}

test('service taxonomy faculty never promotes physical catalog mutation or shared editing', function () {
    [$owner, $shop, $actor, $category, $unit] = closureTaxonomyFixture();
    $access = app(CatalogTaxonomyAccessService::class);
    expect($access->canOpen($shop, $actor))->toBeTrue()
        ->and($access->creatableScopes($shop, $actor))->toBe(['service'])
        ->and($access->readableScopes($shop, $actor))->toBe(['shared', 'service'])
        ->and($access->canModifyCategory($category, $actor))->toBeTrue()
        ->and($access->canModifyUnit($shop, $actor, $unit))->toBeTrue()
        ->and(app(\App\Services\CatalogAccessService::class)->canWrite($shop, $actor, 'unit'))->toBeFalse();
    $legacy = $shop->categories()->create(['name' => 'QA_CLOSURE_Shared', 'slug' => 'qa-closure-shared', 'status' => 'active']);
    expect($access->categoryScope($legacy))->toBe('shared')->and($access->canReadCategory($legacy, $actor))->toBeTrue()
        ->and($access->canModifyCategory($legacy, $actor))->toBeFalse();
});

test('physical references including archived products prevent service taxonomy writes', function () {
    [$owner, $shop, $actor, $category, $unit] = closureTaxonomyFixture();
    $physical = Product::factory()->for($shop)->create(['shop_category_id' => $category->id, 'sale_unit' => 'unit']);
    ShopSyncRecord::create(['shop_id' => $shop->id, 'kind' => 'product_unit', 'record_uuid' => $physical->public_id, 'payload' => ['unit_uuid' => $unit]]);
    $physical->delete();
    $access = app(CatalogTaxonomyAccessService::class);
    expect($access->canModifyCategory($category, $actor))->toBeFalse()->and($access->canModifyUnit($shop, $actor, $unit))->toBeFalse();
});

test('physical only manager cannot modify service scope and legacy sellers remain readers', function () {
    [$owner, $shop, $service, $category, $unit] = closureTaxonomyFixture();
    $shop->update(['enabled_menu_keys' => ['products']]);
    $manager = User::factory()->create();
    ShopMember::create(['shop_id' => $shop->id, 'user_id' => $manager->id, 'role' => 'manager', 'is_active' => true]);
    $reader = User::factory()->create();
    ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $reader->id, 'is_active' => true,
        'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions' => ['products']]);
    $access = app(CatalogTaxonomyAccessService::class);
    expect($access->creatableScopes($shop, $manager))->toBe(['product'])
        ->and($access->canModifyCategory($category, $manager))->toBeFalse()
        ->and($access->canModifyUnit($shop, $manager, $unit))->toBeFalse()
        ->and($access->creatableScopes($shop, $reader))->toBe([]);
});

test('owner manager accountant outsider and disabled module preserve their separate taxonomic authority', function () {
    [$owner, $shop, $service, $category, $unit] = closureTaxonomyFixture();
    $manager = User::factory()->create();
    $accountant = User::factory()->create();
    $outsider = User::factory()->create(['role' => 'admin']);
    foreach (['manager' => $manager, 'accountant' => $accountant] as $role => $actor) ShopMember::create([
        'shop_id' => $shop->id, 'user_id' => $actor->id, 'role' => $role, 'is_active' => true,
    ]);
    $access = app(CatalogTaxonomyAccessService::class);
    expect($access->creatableScopes($shop, $owner))->toBe(['shared', 'product', 'service'])
        ->and($access->creatableScopes($shop, $manager))->toBe(['shared', 'product', 'service'])
        ->and($access->canModifyCategory($category, $accountant))->toBeFalse()
        ->and($access->canModifyCategory($category, $outsider))->toBeFalse();
    $shop->update(['enabled_menu_keys' => []]);
    request()->attributes->replace([]);
    expect($access->creatableScopes($shop, $service))->toBe([])->and($access->canModifyCategory($category, $service))->toBeFalse();
});

test('a taxonomy scope is usable only for its own resource type or shared legacy', function () {
    $access = app(CatalogTaxonomyAccessService::class);
    expect($access->compatible('service', 'unit'))->toBeFalse()->and($access->compatible('product', 'service'))->toBeFalse()
        ->and($access->compatible('shared', 'service'))->toBeTrue()->and($access->compatible('service', 'service'))->toBeTrue();
});
