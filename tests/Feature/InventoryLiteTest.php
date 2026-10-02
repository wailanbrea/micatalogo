<?php

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Models\InventoryMovement;
use App\Models\Invoice;
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

test('recording a sale persists a paid invoice with a price snapshot', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'price' => 2750,
    ]);

    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 4,
        'sold_quantity' => 0,
    ]);

    $this->actingAs($user)->post(route('seller.shops.inventory.sale', [$shop, $product]), [
        'quantity' => 2,
    ])->assertRedirect();

    $invoice = Invoice::query()->with('items')->sole();

    expect($invoice->status)->toBe('paid')
        ->and($invoice->shop_id)->toBe($shop->id)
        ->and($invoice->items)->toHaveCount(1)
        ->and((float) $invoice->total)->toBe(5500.0)
        ->and((float) $invoice->items->first()->unit_price)->toBe(2750.0);

    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $product->id,
        'invoice_id' => $invoice->id,
    ]);
});

test('shared cart checkout creates one invoice for all product lines', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $firstProduct = Product::factory()->create(['shop_id' => $shop->id, 'price' => 1000]);
    $secondProduct = Product::factory()->create(['shop_id' => $shop->id, 'price' => 1500]);

    ProductInventory::create(['product_id' => $firstProduct->id, 'track_inventory' => true, 'stock_quantity' => 5, 'sold_quantity' => 0]);
    ProductInventory::create(['product_id' => $secondProduct->id, 'track_inventory' => true, 'stock_quantity' => 5, 'sold_quantity' => 0]);

    $response = $this->actingAs($user)->postJson(route('seller.shops.inventory.checkout', $shop), [
        'items' => [
            ['product_id' => $firstProduct->id, 'quantity' => 2],
            ['product_id' => $secondProduct->id, 'quantity' => 1],
        ],
    ]);

    $response->assertOk()->assertJsonPath('redirect', route('seller.shops.inventory.index', $shop));

    $invoice = Invoice::query()->with('items')->sole();

    expect($invoice->items)->toHaveCount(2)
        ->and((float) $invoice->total)->toBe(3500.0)
        ->and($firstProduct->fresh()->inventory->stock_quantity)->toBe(3)
        ->and($secondProduct->fresh()->inventory->stock_quantity)->toBe(4);
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

test('selling a decant deducts its milliliters from the source bottle', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $bottle = Product::factory()->create([
        'shop_id' => $shop->id,
        'name' => 'Perfume Original',
        'sale_unit' => 'bottle',
        'volume_ml' => 100,
    ]);
    $decant = Product::factory()->create([
        'shop_id' => $shop->id,
        'name' => 'Perfume Decant 5 ml',
        'sale_unit' => 'decant',
        'volume_ml' => 5,
        'inventory_source_product_id' => $bottle->id,
    ]);

    ProductInventory::create([
        'product_id' => $bottle->id,
        'track_inventory' => true,
        'stock_quantity' => 1,
        'available_ml' => 100,
        'low_stock_threshold' => 1,
    ]);
    $decantInventory = ProductInventory::create([
        'product_id' => $decant->id,
        'track_inventory' => true,
        'stock_quantity' => 20,
        'sold_quantity' => 0,
        'low_stock_threshold' => 2,
    ]);

    $this->actingAs($user)->post(route('seller.shops.inventory.sale', [$shop, $decant]), [
        'quantity' => 2,
    ])->assertRedirect();

    $decantInventory->refresh();
    $bottle->refresh();

    expect($decantInventory->stock_quantity)->toBe(18)
        ->and($decantInventory->sold_quantity)->toBe(2)
        ->and($bottle->inventory->available_ml)->toBe(90)
        ->and($bottle->inventory->stock_quantity)->toBe(0);

    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $decant->id,
        'type' => 'sale',
        'quantity' => -2,
        'stock_before' => 20,
        'stock_after' => 18,
    ]);
});

test('inventory reports when decant revenue covers the source bottle cost', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $bottle = Product::factory()->create([
        'shop_id' => $shop->id,
        'sale_unit' => 'bottle',
        'volume_ml' => 100,
    ]);
    $decant = Product::factory()->create([
        'shop_id' => $shop->id,
        'sale_unit' => 'decant',
        'volume_ml' => 5,
        'price' => 50,
        'inventory_source_product_id' => $bottle->id,
    ]);

    ProductInventory::create([
        'product_id' => $bottle->id,
        'track_inventory' => true,
        'cost_price' => 300,
        'stock_quantity' => 1,
        'available_ml' => 100,
        'low_stock_threshold' => 1,
    ]);
    ProductInventory::create([
        'product_id' => $decant->id,
        'track_inventory' => true,
        'stock_quantity' => 20,
        'sold_quantity' => 0,
        'low_stock_threshold' => 3,
    ]);

    $this->actingAs($user)->post(route('seller.shops.inventory.sale', [$shop, $decant]), [
        'quantity' => 10,
    ])->assertRedirect();

    $summary = app(InventoryService::class)->getShopInventorySummary($shop);
    $recovery = $summary['cost_recovery'][$bottle->id];

    expect($recovery['revenue'])->toBe(500.0)
        ->and($recovery['cost'])->toBe(300.0)
        ->and($recovery['covered'])->toBeTrue()
        ->and($recovery['difference'])->toBe(200.0);
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

test('editing a legacy product does not enable inventory tracking or alter its availability', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'availability_status' => ProductAvailabilityStatus::Available,
    ]);

    $this->actingAs($user)->put(route('seller.shops.products.update', [$shop, $product]), [
        'name' => 'Producto legado actualizado',
        'price' => $product->price,
        'availability_status' => ProductAvailabilityStatus::Available->value,
        'moderation_status' => $product->moderation_status->value,
    ])->assertRedirect();

    $product->refresh();
    expect($product->availability_status)->toBe(ProductAvailabilityStatus::Available);
    expect($product->inventory->track_inventory)->toBeFalse();
    $this->assertDatabaseMissing('inventory_movements', ['product_id' => $product->id]);
});

test('activating inventory records opening stock and synchronizes availability', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'availability_status' => ProductAvailabilityStatus::OutOfStock,
    ]);

    $this->actingAs($user)->put(route('seller.shops.products.update', [$shop, $product]), [
        'name' => $product->name,
        'price' => $product->price,
        'availability_status' => ProductAvailabilityStatus::OutOfStock->value,
        'moderation_status' => $product->moderation_status->value,
        'track_inventory' => true,
        'stock_quantity' => 12,
        'low_stock_threshold' => 3,
    ])->assertRedirect();

    $product->refresh();
    expect($product->availability_status)->toBe(ProductAvailabilityStatus::Available);
    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $product->id,
        'type' => 'adjustment',
        'quantity' => 12,
        'stock_before' => 0,
        'stock_after' => 12,
    ]);
});

test('public stock filter excludes tracked products without units', function () {
    $shop = Shop::factory()->create(['status' => 'active']);
    $available = Product::factory()->create([
        'shop_id' => $shop->id,
        'moderation_status' => ProductModerationStatus::Active,
        'availability_status' => ProductAvailabilityStatus::Available,
        'name' => 'Producto con stock',
    ]);
    $empty = Product::factory()->create([
        'shop_id' => $shop->id,
        'moderation_status' => ProductModerationStatus::Active,
        'availability_status' => ProductAvailabilityStatus::Available,
        'name' => 'Producto agotado',
    ]);

    ProductInventory::create(['product_id' => $available->id, 'track_inventory' => true, 'stock_quantity' => 2, 'low_stock_threshold' => 3]);
    ProductInventory::create(['product_id' => $empty->id, 'track_inventory' => true, 'stock_quantity' => 0, 'low_stock_threshold' => 3]);

    $this->get(route('shops.show', [$shop, 'stock' => 'available']))
        ->assertOk()
        ->assertSee('Producto con stock')
        ->assertDontSee('Producto agotado');
});

test('gross profit retains the sale price and cost captured at the time of sale', function () {
    $product = Product::factory()->create(['price' => 100]);
    $inventory = ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'cost_price' => 60,
        'stock_quantity' => 2,
        'sold_quantity' => 0,
        'low_stock_threshold' => 1,
    ]);

    app(InventoryService::class)->recordSale($product, 1);

    $product->update(['price' => 200]);
    $inventory->update(['cost_price' => 80]);

    expect($inventory->fresh()->gross_profit)->toBe(40.0);
    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $product->id,
        'type' => 'sale',
        'unit_price' => 100,
        'unit_cost' => 60,
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
    InventoryMovement::create([
        'product_id' => $product->id,
        'type' => 'sale',
        'quantity' => -5,
        'stock_before' => 15,
        'stock_after' => 10,
        'unit_price' => 1000,
        'unit_cost' => 600,
    ]);

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
