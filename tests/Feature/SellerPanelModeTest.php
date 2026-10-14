<?php

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner can switch to store mode and hide administrative menus on desktop and mobile', function () {
    $owner = User::factory()->admin()->create();
    $shop = Shop::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->get(route('seller.shops.summary', $shop))
        ->assertOk()
        ->assertSee('Menú Administrativo')
        ->assertSee('Activar modo tienda');

    $this->actingAs($owner)
        ->from(route('seller.shops.summary', $shop))
        ->post(route('seller.panel-mode.update'), ['mode' => 'store'])
        ->assertRedirect(route('seller.shops.summary', $shop))
        ->assertSessionHas('owner_store_mode', true);

    $this->actingAs($owner)
        ->get(route('seller.shops.summary', $shop))
        ->assertOk()
        ->assertDontSee('aria-label="Menú Administrativo"', false)
        ->assertDontSee('Administración Global')
        ->assertSee('Modo tienda activo')
        ->assertSee('Mostrar menú administrativo');
});

test('owner can leave store mode and direct administrative access remains available', function () {
    $owner = User::factory()->admin()->create();
    $shop = Shop::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->withSession(['owner_store_mode' => true])
        ->from(route('seller.shops.summary', $shop))
        ->post(route('seller.panel-mode.update'), ['mode' => 'admin'])
        ->assertRedirect(route('seller.shops.summary', $shop))
        ->assertSessionHas('owner_store_mode', false);

    $this->actingAs($owner)
        ->withSession(['owner_store_mode' => false])
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('non owners cannot change the panel mode', function () {
    $seller = User::factory()->create();

    $this->actingAs($seller)
        ->post(route('seller.panel-mode.update'), ['mode' => 'store'])
        ->assertForbidden();
});
