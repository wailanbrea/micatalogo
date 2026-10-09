<?php

use App\Enums\UserPlan;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\BusinessProfileService;
use App\Services\BusinessPresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('presentation profiles map business types without changing capabilities', function () {
    $user = User::factory()->create();
    $fragrance = Shop::factory()->for($user)->create(['business_type' => 'perfume_store']);
    $service = app(BusinessPresentationService::class);
    $profile = $service->resolve($fragrance);

    expect($profile['archetype'])->toBe('fragrance')
        ->and($profile['terminology']['products'])->toBe('Perfumes')
        ->and($profile['terminology']['new_product'])->toBe('Nuevo perfume')
        ->and($profile['pos']['search_placeholder'])->toContain('perfume')
        ->and($profile['pos']['show_wholesale'])->toBeFalse();
});

test('unsupported inventory and credit widgets are removed from presentation', function () {
    $shop = Shop::factory()->create(['business_type' => 'food_restaurant']);
    $profile = app(BusinessPresentationService::class)->resolve($shop);

    expect($profile['archetype'])->toBe('food')
        ->and($profile['catalog']['show_stock'])->toBeFalse()
        ->and($profile['pos']['show_inventory'])->toBeFalse()
        ->and($profile['inventory']['enabled'])->toBeFalse()
        ->and($profile['dashboard']['widgets'])->not->toContain('low_stock')
        ->and($profile['dashboard']['widgets'])->not->toContain('receivables');
});

test('mobile shops and catalog endpoints expose the additive presentation contract', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->for($user)->create(['business_type' => 'hardware']);
    $token = $user->createToken('presentation')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/shops')
        ->assertOk()
        ->assertJsonPath('0.presentation.archetype', 'hardware')
        ->assertJsonPath('0.presentation.terminology.products', 'Artículos')
        ->assertJsonPath('0.presentation.catalog.show_stock', true);

    $this->withToken($token)
        ->getJson("/api/v1/shops/{$shop->public_id}/catalog")
        ->assertOk()
        ->assertJsonPath('shop.presentation.archetype', 'hardware')
        ->assertJsonPath('shop.presentation.pos.search_placeholder', 'Buscar artículo, marca o código');
});

test('SHOP-004/005/010 every configured business type stays coherent across free basic and pro plans', function () {
    $types = app(BusinessProfileService::class)->types();
    // The live config currently contains 23 types (the master plan listed 21
    // before vapes, pastry and related registration options were added).
    expect($types)->toHaveCount(23);

    foreach ($types as $type => $definition) {
        foreach ([UserPlan::Free, UserPlan::Premium, UserPlan::Pro] as $plan) {
            $user = User::factory()->create([
                'plan' => $plan,
                'email' => "matrix-{$type}-{$plan->value}@example.com",
            ]);
            $shop = Shop::factory()->for($user)->create(['business_type' => $type]);
            $profile = app(BusinessProfileService::class)->profile($shop);
            $presentation = app(BusinessPresentationService::class)->resolve($shop);

            expect($profile['business_type'])->toBe($type)
                ->and($profile['business_type_label'])->toBe($definition['label'])
                ->and($profile['categories'])->not->toBeEmpty()
                ->and($profile['product_fields'])->not->toBeEmpty()
                ->and($profile['capabilities'])->toHaveKeys(config('business-types.implemented'))
                ->and($presentation['archetype'])->toBe(config("business-types.type_archetypes.{$type}", 'general_retail'))
                ->and($presentation['pos']['show_credit'])->toBe(($profile['capabilities']['credit'] ?? 'disabled') === 'enabled')
                ->and($presentation['pos']['show_wholesale'])->toBe(($profile['capabilities']['wholesale'] ?? 'disabled') === 'enabled')
                ->and($presentation['inventory']['enabled'])->toBe(($profile['capabilities']['inventory'] ?? 'disabled') === 'enabled');

            if (in_array($type, ['perfume_store', 'general_retail', 'other'], true)) {
                expect($profile['capabilities']['decants'])
                    ->toBe($plan === UserPlan::Pro ? 'enabled' : 'disabled');
            } else {
                expect($profile['capabilities']['decants'])->not->toBe('enabled');
            }

            if (in_array($type, ['food_restaurant', 'pastry', 'professional_services', 'tattoo_studio'], true)) {
                expect($presentation['pos']['show_inventory'])->toBeFalse()
                    ->and($presentation['catalog']['show_stock'])->toBeFalse();
            }
        }
    }
});

test('SHOP-003/006/010 one pro owner keeps multi-shop verticals and catalogs isolated', function () {
    $owner = User::factory()->create([
        'plan' => UserPlan::Pro,
        'email' => 'multi-shop-qa@example.com',
    ]);
    $perfumeShop = Shop::factory()->for($owner)->create([
        'name' => 'Perfumería Multi-Shop',
        'business_type' => 'perfume_store',
    ]);
    $clothingShop = Shop::factory()->for($owner)->create([
        'name' => 'Ropa Multi-Shop',
        'business_type' => 'clothing',
    ]);
    $perfume = Product::factory()->for($perfumeShop)->create(['name' => 'Perfume exclusivo A']);
    $clothing = Product::factory()->for($clothingShop)->create(['name' => 'Camisa exclusiva B']);
    $token = $owner->createToken('multi-shop-qa')->plainTextToken;

    expect(app(\App\Services\PlanLimitsService::class)->activeShopLimit($owner))->toBe(3);

    $shops = $this->withToken($token)
        ->getJson('/api/v1/shops')
        ->assertOk()
        ->json();

    expect(collect($shops)->pluck('id')->all())
        ->toContain($perfumeShop->public_id, $clothingShop->public_id);

    $perfumeCatalog = $this->withToken($token)
        ->getJson("/api/v1/shops/{$perfumeShop->public_id}/catalog")
        ->assertOk()
        ->assertJsonPath('shop.presentation.archetype', 'fragrance')
        ->json('products');
    $clothingCatalog = $this->withToken($token)
        ->getJson("/api/v1/shops/{$clothingShop->public_id}/catalog")
        ->assertOk()
        ->assertJsonPath('shop.presentation.archetype', 'fashion')
        ->json('products');

    expect(collect($perfumeCatalog)->pluck('id')->all())
        ->toContain($perfume->public_id)
        ->not->toContain($clothing->public_id);
    expect(collect($clothingCatalog)->pluck('id')->all())
        ->toContain($clothing->public_id)
        ->not->toContain($perfume->public_id);
});
