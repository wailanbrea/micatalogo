<?php

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a verified seller can access their shop inventory dashboard', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);

    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'name' => 'Nike Air Max',
        'price' => 5000,
        'availability_status' => ProductAvailabilityStatus::Available,
    ]);

    ProductInventory::create([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'track_inventory' => true,
        'cost_price' => 2800,
        'stock_quantity' => 12,
        'sold_quantity' => 8,
        'low_stock_threshold' => 3,
    ]);

    $response = $this->actingAs($user)->get(route('seller.shops.inventory.index', $shop));
    $response->assertOk();
    $response->assertSee('Control de Inventario');
    $response->assertSee('Nike Air Max');
    $response->assertSee('12'); // Stock actual
    $response->assertSee('8'); // Vendidas
    $response->assertSee('RD$ 5,000'); // Precio venta
    $response->assertSee('RD$ 2,800'); // Costo compra privado
    $response->assertSee('RD$ 2,200'); // Margen unitario
});

test('inventory actions reject products without inventory control', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create(['shop_id' => $shop->id]);
    $inventory = ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => false,
        'stock_quantity' => 8,
        'sold_quantity' => 0,
        'low_stock_threshold' => 3,
    ]);

    $this->actingAs($user)->post(route('seller.shops.inventory.sale', [$shop, $product]), ['quantity' => 1])
        ->assertSessionHasErrors('quantity');
    $this->actingAs($user)->post(route('seller.shops.inventory.restock', [$shop, $product]), ['quantity' => 1])
        ->assertSessionHasErrors('quantity');
    $this->actingAs($user)->post(route('seller.shops.inventory.adjustment', [$shop, $product]), ['new_stock' => 0])
        ->assertSessionHasErrors('new_stock');

    $inventory->refresh();
    expect($inventory->stock_quantity)->toBe(8);
    expect($inventory->sold_quantity)->toBe(0);
    $this->assertDatabaseMissing('inventory_movements', ['product_id' => $product->id]);
});

test('a seller cannot access inventory of another sellers shop', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser)->get(route('seller.shops.inventory.index', $shop));
    $response->assertForbidden();
});

test('recording a sale decrements stock, increments sold, and records movement', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create(['shop_id' => $shop->id]);

    $inventory = ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'cost_price' => 2000,
        'stock_quantity' => 10,
        'sold_quantity' => 5,
        'low_stock_threshold' => 3,
    ]);

    $response = $this->actingAs($user)->post(route('seller.shops.inventory.sale', [$shop, $product]), [
        'quantity' => 3,
        'notes' => 'Venta en tienda física',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status');

    $inventory->refresh();
    expect($inventory->stock_quantity)->toBe(7);
    expect($inventory->sold_quantity)->toBe(8);

    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $product->id,
        'user_id' => $user->id,
        'type' => 'sale',
        'quantity' => -3,
        'stock_before' => 10,
        'stock_after' => 7,
        'notes' => 'Venta en tienda física',
    ]);
});

test('recording a sale validates available stock and prevents overselling', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create(['shop_id' => $shop->id]);

    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 2,
        'sold_quantity' => 0,
        'low_stock_threshold' => 3,
    ]);

    $response = $this->actingAs($user)->from(route('seller.shops.inventory.index', $shop))
        ->post(route('seller.shops.inventory.sale', [$shop, $product]), [
            'quantity' => 5, // Exceeds 2
        ]);

    $response->assertRedirect(route('seller.shops.inventory.index', $shop));
    $response->assertSessionHasErrors('quantity');

    $product->refresh();
    expect($product->inventory->stock_quantity)->toBe(2);
});

test('selling the last unit automatically sets product to out_of_stock', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'availability_status' => ProductAvailabilityStatus::Available,
    ]);

    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 1,
        'sold_quantity' => 4,
        'low_stock_threshold' => 2,
    ]);

    $this->actingAs($user)->post(route('seller.shops.inventory.sale', [$shop, $product]), [
        'quantity' => 1,
    ]);

    $product->refresh();
    expect($product->inventory->stock_quantity)->toBe(0);
    expect($product->inventory->sold_quantity)->toBe(5);
    expect($product->availability_status)->toBe(ProductAvailabilityStatus::OutOfStock);
});

test('restocking increments stock and restores available status if out of stock', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'availability_status' => ProductAvailabilityStatus::OutOfStock,
    ]);

    $inventory = ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 0,
        'sold_quantity' => 10,
        'low_stock_threshold' => 3,
    ]);

    $response = $this->actingAs($user)->post(route('seller.shops.inventory.restock', [$shop, $product]), [
        'quantity' => 15,
        'notes' => 'Reposición de proveedor local',
    ]);

    $response->assertRedirect();
    $product->refresh();
    expect($product->inventory->stock_quantity)->toBe(15);
    expect($product->inventory->sold_quantity)->toBe(10);
    expect($product->availability_status)->toBe(ProductAvailabilityStatus::Available);

    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $product->id,
        'type' => 'restock',
        'quantity' => 15,
        'stock_before' => 0,
        'stock_after' => 15,
    ]);
});

test('adjusting stock sets exact count without altering sold quantity', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create(['shop_id' => $shop->id]);

    $inventory = ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 8,
        'sold_quantity' => 12,
        'low_stock_threshold' => 3,
    ]);

    $response = $this->actingAs($user)->post(route('seller.shops.inventory.adjustment', [$shop, $product]), [
        'new_stock' => 5,
        'notes' => 'Conteo físico de fin de semana',
    ]);

    $response->assertRedirect();
    $inventory->refresh();
    expect($inventory->stock_quantity)->toBe(5);
    expect($inventory->sold_quantity)->toBe(12); // Intact

    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $product->id,
        'type' => 'adjustment',
        'quantity' => -3,
        'stock_before' => 8,
        'stock_after' => 5,
        'notes' => 'Conteo físico de fin de semana',
    ]);
});

test('inventory service computes financial valuation and gross profit correctly', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'price' => 1000,
    ]);

    $inventory = ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'cost_price' => 600,
        'stock_quantity' => 10,
        'sold_quantity' => 5,
        'low_stock_threshold' => 3,
    ]);

    expect($inventory->inventory_value)->toBe(6000.0); // 10 * 600
    expect($inventory->gross_profit)->toBe(2000.0);    // 5 * (1000 - 600)
    expect($inventory->unit_margin)->toBe(400.0);      // 1000 - 600
    expect($inventory->margin_percentage)->toBe(66.7); // (400 / 600) * 100

    $service = app(InventoryService::class);
    $summary = $service->getShopInventorySummary($shop);

    expect($summary['total_available'])->toBe(10);
    expect($summary['total_sold'])->toBe(5);
    expect($summary['total_inventory_value'])->toBe(6000.0);
    expect($summary['total_gross_profit'])->toBe(2000.0);
});

test('public storefront and product view render inventory status badges correctly', function () {
    $shop = Shop::factory()->create(['status' => 'active']);

    $productLow = Product::factory()->create([
        'shop_id' => $shop->id,
        'name' => 'Nike Air Max',
        'moderation_status' => ProductModerationStatus::Active,
        'availability_status' => ProductAvailabilityStatus::Available,
    ]);

    ProductInventory::create([
        'product_id' => $productLow->id,
        'track_inventory' => true,
        'stock_quantity' => 2,
        'sold_quantity' => 10,
        'low_stock_threshold' => 3,
    ]);

    $productOut = Product::factory()->create([
        'shop_id' => $shop->id,
        'name' => 'Reloj Casio Vintage',
        'moderation_status' => ProductModerationStatus::Active,
        'availability_status' => ProductAvailabilityStatus::OutOfStock,
    ]);

    ProductInventory::create([
        'product_id' => $productOut->id,
        'track_inventory' => true,
        'stock_quantity' => 0,
        'sold_quantity' => 15,
        'low_stock_threshold' => 2,
    ]);

    // Visitar tienda pública
    $response = $this->get(route('shops.show', $shop));
    $response->assertOk();
    $response->assertSee('¡Últimas 2 unid.!');
    $response->assertSee('Agotado');

    // Visitar ficha del producto con poco stock
    $responseDetail = $this->get(route('products.show', [$shop, $productLow]));
    $responseDetail->assertOk();
    $responseDetail->assertSee('¡Últimas 2 unidades!');
});
