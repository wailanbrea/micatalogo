<?php

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\ShopCategory;
use App\Models\User;
use App\Enums\ProductModerationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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
    $image = ProductImage::factory()->for($product)->create();

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
        ->assertJsonPath('products.0.image_url', $image->url)
        ->assertJsonPath('products.0.thumbnail_url', $image->thumbnail_url)
        ->assertJsonPath('products.0.inventory.stock_quantity', 8)
        ->assertJsonPath('products.0.inventory.cost_price', '125.00')
        ->assertJsonPath('products.0.image_url', $image->url)
        ->assertJsonPath('products.0.thumbnail_url', $image->thumbnail_url);
});

test('a seller cannot retrieve another sellers catalog snapshot', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create();
    $token = $user->createToken('BSPOS', ['catalog:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/shops/{$shop->public_id}/catalog")
        ->assertNotFound();
});

test('the catalog snapshot preserves bottle and decant presentation metadata', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $source = Product::factory()->for($shop)->create([
        'name' => 'Perfume fuente 100 ml',
        'sale_unit' => 'bottle',
        'volume_ml' => 100,
        'price' => 2500,
    ]);
    $decant = Product::factory()->for($shop)->create([
        'name' => 'Perfume fuente 10 ml',
        'sale_unit' => 'decant',
        'volume_ml' => 10,
        'inventory_source_product_id' => $source->id,
        'price' => 500,
    ]);
    ProductInventory::create(['product_id' => $source->id, 'track_inventory' => true, 'cost_price' => 1000, 'stock_quantity' => 1, 'available_ml' => 100]);
    ProductInventory::create(['product_id' => $decant->id, 'track_inventory' => true, 'cost_price' => 100, 'stock_quantity' => 10, 'available_ml' => 100]);

    $token = $user->createToken('BSPOS', ['catalog:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/shops/{$shop->public_id}/catalog")
        ->assertOk()
        ->assertJsonPath('products.0.sale_unit', 'decant')
        ->assertJsonPath('products.0.volume_ml', 10)
        ->assertJsonPath('products.0.source_product_id', $source->public_id)
        ->assertJsonPath('products.0.source_product_name', 'Perfume fuente 100 ml')
        ->assertJsonPath('products.1.sale_unit', 'bottle')
        ->assertJsonPath('products.1.volume_ml', 100);
});

test('the catalog snapshot reports sellable decant units when vial inventory is managed', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $source = Product::factory()->for($shop)->create([
        'name' => 'Baccarat Rouge botella 100 ml',
        'sale_unit' => 'bottle',
        'volume_ml' => 100,
    ]);
    $decant = Product::factory()->for($shop)->create([
        'name' => 'Baccarat Rouge decant 10 ml',
        'sale_unit' => 'decant',
        'volume_ml' => 10,
        'inventory_source_product_id' => $source->id,
        'price' => 500,
        'moderation_status' => ProductModerationStatus::Active,
    ]);
    ProductInventory::create(['product_id' => $source->id, 'track_inventory' => true, 'stock_quantity' => 1, 'available_ml' => 100]);
    ProductInventory::create(['product_id' => $decant->id, 'track_inventory' => true, 'stock_quantity' => 99]);
    $vialId = DB::table('decant_vials')->insertGetId([
        'public_id' => (string) \Illuminate\Support\Str::ulid(),
        'shop_id' => $shop->id,
        'volume_ml' => 10,
        'name' => 'Frasco decant 10 ml',
        'active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('decant_vial_lots')->insert([
        'vial_id' => $vialId,
        'received_quantity' => 4,
        'remaining_quantity' => 4,
        'received_cost_cents' => 400,
        'remaining_cost_cents' => 400,
        'received_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $token = $user->createToken('BSPOS', ['catalog:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/shops/{$shop->public_id}/catalog")
        ->assertOk()
        ->assertJsonPath('products.1.inventory.stock_quantity', 4)
        ->assertJsonPath('products.1.source_product_name', 'Baccarat Rouge botella 100 ml');
});
