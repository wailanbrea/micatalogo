<?php

use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner can disable a store menu from web without losing the protected control path', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->put(route('seller.shops.menus.update', $shop), [
            'enabled_menu_keys' => ['products', 'customers'],
        ])
        ->assertRedirect(route('seller.shops.menus.edit', $shop))
        ->assertSessionHasNoErrors();

    expect($shop->fresh()->enabled_menu_keys)
        ->toContain('products')
        ->toContain('customers')
        ->toContain('shop_settings')
        ->toContain('sellers')
        ->not->toContain('sales');

    $this->actingAs($owner)
        ->get(route('seller.shops.menus.edit', $shop))
        ->assertOk()
        ->assertSee('Menús visibles')
        ->assertSee('Productos')
        ->assertSee('Protegido para el owner');

    $this->actingAs($owner)
        ->get(route('seller.shops.edit', $shop))
        ->assertOk();
});

test('shop menu visibility applies to sellers and rejects their direct web access', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $seller = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create(['enabled_menu_keys' => ['products', 'shop_settings', 'sellers']]);
    ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'commission_type' => 'percentage',
        'commission_value' => 5,
        'is_active' => true,
        'menu_permissions' => ['sales', 'products'],
    ]);

    $this->actingAs($seller)->get(route('seller.shops.products.index', $shop))->assertOk();
    $this->get(route('seller.shops.pos', $shop))->assertForbidden();
    $this->get(route('seller.shops.feature', [$shop, 'feature' => 'sales']))->assertForbidden();
});

test('owner can update store menus through the mobile API and sellers receive the same visibility', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $seller = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create();
    $assignment = ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'commission_type' => 'percentage',
        'commission_value' => 5,
        'is_active' => true,
        'menu_permissions' => ['sales', 'products'],
    ]);
    $ownerToken = $owner->createToken('menu-owner', ['*'])->plainTextToken;
    $sellerToken = $seller->createToken('menu-seller', ['*'])->plainTextToken;

    $this->withToken($ownerToken)
        ->putJson('/api/v1/shops/'.$shop->public_id.'/menu-visibility', ['enabled_menu_keys' => ['products']])
        ->assertOk()
        ->assertJsonPath('enabled_menu_keys.0', 'products');

    app('auth')->forgetGuards();
    $this->withToken($sellerToken)
        ->getJson('/api/v1/shops')
        ->assertOk()
        ->assertJsonPath('0.enabled_menu_keys', fn (array $keys): bool => in_array('products', $keys, true) && ! in_array('sales', $keys, true))
        ->assertJsonPath('0.menu_permissions', fn (array $keys): bool => in_array('products', $keys, true) && ! in_array('sales', $keys, true));

    app('auth')->forgetGuards();
    $this->withToken($sellerToken)
        ->putJson('/api/v1/shops/'.$shop->public_id.'/menu-visibility', ['enabled_menu_keys' => ['sales']])
        ->assertForbidden();

    expect($assignment->fresh()->menu_permissions)->toBe(['sales', 'products']);
});
