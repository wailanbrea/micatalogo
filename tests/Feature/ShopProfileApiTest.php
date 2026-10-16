<?php

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('api returns exact business_type, archetype, and capabilities without fallback to general_retail', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create([
        'business_type' => 'appliance_store',
        'business_capability_overrides' => [
            'wholesale' => false,
        ],
        'business_profile_version' => 1,
    ]);

    $token = $owner->createToken('test', ['*'])->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/shops')
        ->assertOk();

    $response->assertJsonPath('0.id', $shop->public_id);
    $response->assertJsonPath('0.business_type', 'appliance_store');
    $response->assertJsonPath('0.business_type_label', 'Electrodomésticos');
    $response->assertJsonPath('0.presentation.archetype', 'appliances');
    $response->assertJsonPath('0.capabilities.wholesale', 'disabled');
});

test('api isolates profiles for multiple shops with different business types under same owner', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $applianceShop = Shop::factory()->for($owner)->create([
        'name' => 'Electro Tienda',
        'business_type' => 'appliance_store',
    ]);
    $perfumeShop = Shop::factory()->for($owner)->create([
        'name' => 'Perfumes VIP',
        'business_type' => 'perfume_store',
    ]);

    $token = $owner->createToken('test', ['*'])->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/shops')
        ->assertOk();

    $shops = $response->json();
    $byType = collect($shops)->keyBy('business_type');

    expect($byType)->toHaveKey('appliance_store')
        ->and($byType)->toHaveKey('perfume_store');

    expect($byType['appliance_store']['presentation']['archetype'])->toBe('appliances');
    expect($byType['perfume_store']['presentation']['archetype'])->toBe('fragrance');
    expect($byType['perfume_store']['presentation']['product_form']['default_sale_unit'])->toBe('bottle');
});
