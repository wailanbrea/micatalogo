<?php

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('platform owner can list every shop and filter by shop or owner email', function () {
    $owner = User::factory()->admin()->create([
        'email' => 'wailandkey@gmail.com',
    ]);
    $otherOwner = User::factory()->create([
        'name' => 'Casa Nativa Owner',
        'email' => 'casa@example.com',
    ]);
    $ownerShop = Shop::factory()->for($owner)->create(['name' => 'BSolutions Principal']);
    Shop::factory()->for($otherOwner)->create(['name' => 'Casa Nativa']);

    $this->actingAs($owner)
        ->get(route('admin.menus.index'))
        ->assertOk()
        ->assertSee('Menús por tienda')
        ->assertSee('BSolutions Principal')
        ->assertSee('Casa Nativa');

    $this->get(route('admin.menus.index', ['q' => 'casa@example.com']))
        ->assertOk()
        ->assertSee('Casa Nativa')
        ->assertDontSee(route('admin.menus.edit', $ownerShop));
});

test('platform owner can configure menus for any shop from the global editor', function () {
    $owner = User::factory()->admin()->create([
        'email' => 'wailandkey@gmail.com',
    ]);
    $shopOwner = User::factory()->create(['email' => 'shop-owner@example.com']);
    $shop = Shop::factory()->for($shopOwner)->create();

    $this->actingAs($owner)
        ->get(route('admin.menus.edit', $shop))
        ->assertOk()
        ->assertSee($shop->name)
        ->assertSee('Web y Android');

    $this->put(route('admin.menus.update', $shop), [
        'enabled_menu_keys' => ['sales', 'decants'],
    ])->assertRedirect(route('admin.menus.edit', $shop));

    expect($shop->fresh()->enabled_menu_keys)
        ->toContain('sales')
        ->toContain('decants')
        ->toContain('settings')
        ->toContain('shop_settings')
        ->toContain('sellers')
        ->not->toContain('finance');
});

test('global menu editor is restricted to the configured platform owner', function () {
    $shop = Shop::factory()->create();
    $otherAdmin = User::factory()->admin()->create(['email' => 'other-admin@example.com']);
    $seller = User::factory()->create();

    $this->actingAs($otherAdmin)
        ->get(route('admin.menus.index'))
        ->assertForbidden();

    $this->actingAs($seller)
        ->get(route('admin.menus.index'))
        ->assertForbidden();

    $this->actingAs($otherAdmin)
        ->put(route('admin.menus.update', $shop), ['enabled_menu_keys' => ['sales']])
        ->assertForbidden();
});

test('only the platform owner sees the global menu editor link', function () {
    $owner = User::factory()->admin()->create(['email' => 'wailandkey@gmail.com']);
    $admin = User::factory()->admin()->create(['email' => 'another-admin@example.com']);
    Shop::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Menús de todas las tiendas');

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('Menús de todas las tiendas');
});
