<?php

use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\ShopCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a seller can retrieve a canonical catalog snapshot for their shop', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $category = ShopCategory::factory()->create([
        'shop_id' => $shop->id,
        'name' => 'Cuidado personal',
        'sort_order' => 2,
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'shop_category_id' => $category->id,
        'name' => 'Jabón artesanal',
        'product_code' => 'JAB-001',
        'price' => 275.5,
        'sale_unit' => 'unit',
    ]);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'cost_price' => 125,
        'stock_quantity' => 8,
        'low_stock_threshold' => 3,
    ]);

    $token = $user->createToken('BSPOS', ['catalog:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/shops/{$shop->public_id}/catalog")
        ->assertOk()
        ->assertJsonPath('shop.id', $shop->public_id)
        ->assertJsonPath('categories.0.id', (string) $category->id)
        ->assertJsonPath('categories.0.name', 'Cuidado personal')
        ->assertJsonPath('products.0.id', $product->public_id)
        ->assertJsonPath('products.0.category_id', (string) $category->id)
        ->assertJsonPath('products.0.internal_code', 'JAB-001')
        ->assertJsonPath('products.0.inventory.stock_quantity', 8)
        ->assertJsonPath('products.0.inventory.cost_price', '125.00');
});

test('a seller cannot retrieve another sellers catalog snapshot', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create();
    $token = $user->createToken('BSPOS', ['catalog:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/shops/{$shop->public_id}/catalog")
        ->assertNotFound();
});
