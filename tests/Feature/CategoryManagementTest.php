<?php

use App\Models\GlobalCategory;
use App\Models\Shop;
use App\Models\ShopCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a seller manages categories only within their own shop', function () {
    $seller = User::factory()->create();
    $shop = Shop::factory()->for($seller)->create();
    $otherShop = Shop::factory()->create();
    $category = ShopCategory::factory()->for($otherShop)->create();

    $this->actingAs($seller)->post(route('seller.shops.categories.store', $shop), ['name' => 'Ofertas', 'status' => 'active'])
        ->assertRedirect();

    $this->assertDatabaseHas('shop_categories', ['shop_id' => $shop->id, 'slug' => 'ofertas']);
    $this->actingAs($seller)->delete(route('seller.shops.categories.destroy', [$shop, $category]))->assertNotFound();
});

test('only an administrator manages global categories and hierarchy has two levels', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $root = GlobalCategory::factory()->create(['parent_id' => null]);
    $child = GlobalCategory::factory()->create(['parent_id' => $root->id]);

    $this->actingAs($seller)->get(route('admin.categories.index'))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => 'Nieto', 'parent_id' => $child->id, 'status' => 'active'])->assertSessionHasErrors('parent_id');
    $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => 'Ropa', 'parent_id' => $root->id, 'status' => 'active'])->assertRedirect();

    $this->assertDatabaseHas('global_categories', ['name' => 'Ropa', 'parent_id' => $root->id, 'slug' => 'ropa']);
});

test('administrator can update and delete a global category through its named routes', function () {
    $admin = User::factory()->admin()->create();
    $category = GlobalCategory::factory()->create(['name' => 'Accesorios', 'slug' => 'accesorios']);

    $this->actingAs($admin)
        ->put(route('admin.categories.update', $category), [
            'name' => 'Accesorios premium',
            'slug' => 'accesorios-premium',
            'status' => 'inactive',
            'sort_order' => 10,
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Categoria global actualizada.');

    expect($category->fresh()->name)->toBe('Accesorios premium')
        ->and($category->fresh()->slug)->toBe('accesorios-premium')
        ->and($category->fresh()->status->value)->toBe('inactive');

    $this->actingAs($admin)
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect()
        ->assertSessionHas('status', 'Categoria global eliminada.');

    $this->assertDatabaseMissing('global_categories', ['id' => $category->id]);
});

test('shop owner can update and delete an own shop category through its named routes', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create();
    $category = ShopCategory::factory()->for($shop)->create(['name' => 'Hogar', 'slug' => 'hogar']);

    $this->actingAs($owner)
        ->put(route('seller.shops.categories.update', [$shop, $category]), [
            'name' => 'Hogar y oficina',
            'slug' => 'hogar-oficina',
            'status' => 'active',
            'sort_order' => 5,
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Categoria actualizada.');

    expect($category->fresh()->name)->toBe('Hogar y oficina')
        ->and($category->fresh()->slug)->toBe('hogar-oficina');

    $this->actingAs($owner)
        ->delete(route('seller.shops.categories.destroy', [$shop, $category]))
        ->assertRedirect()
        ->assertSessionHas('status', 'Categoria eliminada.');

    $this->assertDatabaseMissing('shop_categories', ['id' => $category->id]);
});
