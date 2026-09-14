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
