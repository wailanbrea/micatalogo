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

test('mobile shop collection preserves each tenant business profile, version and overrides', function () {
    $owner = User::factory()->create();
    $appliances = Shop::factory()->for($owner)->create([
        'business_type' => 'appliance_store',
        'business_profile_version' => 7,
        'business_capability_overrides' => ['inventory' => false],
    ]);
    $perfumes = Shop::factory()->for($owner)->create(['business_type' => 'perfume_store']);
    $foreignShop = Shop::factory()->create(['business_type' => 'footwear']);
    $token = $owner->createToken('business-profile-regression')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/shops')->assertOk();
    $shops = collect($response->json());
    $appliancePayload = $shops->firstWhere('id', $appliances->public_id);
    $perfumePayload = $shops->firstWhere('id', $perfumes->public_id);

    expect($appliancePayload)
        ->not->toBeNull()
        ->and($appliancePayload['business_type'])->toBe('appliance_store')
        ->and($appliancePayload['profile_version'])->toBe(7)
        ->and($appliancePayload['presentation']['archetype'])->toBe('appliances')
        ->and($appliancePayload['capabilities']['inventory'])->toBe('disabled')
        ->and($perfumePayload['business_type'])->toBe('perfume_store')
        ->and($perfumePayload['presentation']['archetype'])->toBe('fragrance')
        ->and($shops->pluck('id'))->not->toContain($foreignShop->public_id);
});

test('adaptive product form contract matches business and item capabilities without granting access', function () {
    $appliance = Shop::factory()->create(['business_type' => 'appliance_store']);
    $perfume = Shop::factory()->create(['business_type' => 'perfume_store']);
    $barber = Shop::factory()->create(['business_type' => 'barbershop']);

    $applianceForm = app(BusinessPresentationService::class)->resolve($appliance)['product_form'];
    $perfumeForm = app(BusinessPresentationService::class)->resolve($perfume)['product_form'];
    $barberForm = app(BusinessPresentationService::class)->resolve($barber)['product_form'];

    expect($applianceForm['schema_version'])->toBe(1)
        ->and($applianceForm['default_sale_unit'])->toBe('unit')
        ->and($applianceForm['available_sale_units'])->toContain('unit')
        ->and($applianceForm['available_sale_units'])->not->toContain('bottle', 'ml')
        ->and($applianceForm['inventory_unit_uuids']['unit'])->toMatch('/^[0-9a-f-]{36}$/')
        ->and($applianceForm['feature_states']['serial_tracking'])->toBe('unsupported')
        ->and($applianceForm['supported_fields'])->not->toContain('model')
        ->and($applianceForm['feature_states']['model'])->toBe('unsupported')
        ->and($applianceForm['feature_states']['brand'])->toBe('web_only')
        ->and($applianceForm['feature_states']['barcode'])->toBe('web_only')
        ->and($applianceForm['feature_states']['cost_price'])->toBe('permission_gated')
        ->and($applianceForm['web_only_fields'])->toContain('brand', 'barcode')
        ->and($applianceForm['supported_fields'])->not->toContain('cost_price', 'wholesale_price')
        ->and($perfumeForm['default_sale_unit'])->toBe('bottle')
        ->and($perfumeForm['available_sale_units'])->toContain('unit', 'bottle', 'ml')
        ->and($perfumeForm['sale_unit_to_inventory_unit']['bottle'])->toBe('bottle')
        ->and($perfumeForm['inventory_unit_uuids']['bottle'])->toMatch('/^[0-9a-f-]{36}$/')
        ->and($barberForm['available_sale_units'])->toContain('service')
        ->and($barberForm['default_sale_unit'])->toBe('service')
        ->and($barberForm['available_sale_units'])->not->toContain('bottle', 'ml');
});

test('adaptive product form is rendered for appliance and fragrance profiles with automatic sale-unit defaults', function () {
    $applianceOwner = User::factory()->create();
    $applianceShop = Shop::factory()->for($applianceOwner)->create(['business_type' => 'appliance_store']);
    $applianceResponse = $this->actingAs($applianceOwner)->get(route('seller.shops.products.create', $applianceShop));

    $applianceResponse->assertOk()
        ->assertSee('Nuevo electrodoméstico')
        ->assertSee('Inventario contado en')
        ->assertDontSee('<option value="bottle">', false)
        ->assertDontSee('<option value="ml">', false);

    $perfumeOwner = User::factory()->create();
    $perfumeShop = Shop::factory()->for($perfumeOwner)->create(['business_type' => 'perfume_store']);
    $perfumeResponse = $this->actingAs($perfumeOwner)->get(route('seller.shops.products.create', $perfumeShop));

    $perfumeResponse->assertOk()
        ->assertSee('Nuevo perfume')
        ->assertSee('Botella completa')
        ->assertSee('name="unit_uuid"', false);
});

test('SHOP-004/005/010 every configured business type stays coherent across free basic and pro plans', function () {
    $types = app(BusinessProfileService::class)->types();
    // The live config currently contains 23 types (the master plan listed 21
    // before vapes, pastry and related registration options were added).
    expect($types)->toHaveCount(23);

    foreach ($types as $type => $definition) {
        foreach ([UserPlan::Free, UserPlan::Premium, UserPlan::Pro, UserPlan::Custom] as $plan) {
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
                    ->toBe('enabled');
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
