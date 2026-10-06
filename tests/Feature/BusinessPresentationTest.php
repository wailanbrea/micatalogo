<?php

use App\Models\Shop;
use App\Models\User;
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
