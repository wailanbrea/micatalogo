<?php

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Models\GlobalCategory;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a verified seller can create a product in their shop', function () {
    $seller = User::factory()->create(['email_verified_at' => now()]);
    $shop = Shop::factory()->for($seller)->create();
    $globalCat = GlobalCategory::factory()->create();
    $shopCat = ShopCategory::factory()->for($shop)->create();

    $response = $this->actingAs($seller)->post(route('seller.shops.products.store', $shop), [
        'name' => 'Laptop Gamer RTX',
        'price' => 75000,
        'description' => 'Laptop de alto rendimiento para desarrollo y gaming.',
        'availability_status' => ProductAvailabilityStatus::Available->value,
        'moderation_status' => ProductModerationStatus::Active->value,
        'global_category_id' => $globalCat->id,
        'shop_category_id' => $shopCat->id,
    ]);

    $response->assertRedirect(route('seller.shops.products.index', $shop));

    $this->assertDatabaseHas('products', [
        'shop_id' => $shop->id,
        'name' => 'Laptop Gamer RTX',
        'slug' => 'laptop-gamer-rtx',
        'currency' => 'DOP',
        'availability_status' => ProductAvailabilityStatus::Available->value,
        'moderation_status' => ProductModerationStatus::Active->value,
    ]);

    $product = Product::where('slug', 'laptop-gamer-rtx')->firstOrFail();
    expect($product->published_at)->not->toBeNull();
});

test('a seller cannot create a product in another sellers shop', function () {
    $sellerA = User::factory()->create(['email_verified_at' => now()]);
    $sellerB = User::factory()->create(['email_verified_at' => now()]);
    $shopB = Shop::factory()->for($sellerB)->create();

    $response = $this->actingAs($sellerA)->post(route('seller.shops.products.store', $shopB), [
        'name' => 'Producto no autorizado',
        'price' => 1000,
        'availability_status' => ProductAvailabilityStatus::Available->value,
        'moderation_status' => ProductModerationStatus::Active->value,
    ]);

    $response->assertForbidden();
});

test('product slugs resolve collisions deterministically within the shop', function () {
    $seller = User::factory()->create(['email_verified_at' => now()]);
    $shop = Shop::factory()->for($seller)->create();

    $payload = [
        'name' => 'Camisa Azul',
        'price' => 1200,
        'availability_status' => ProductAvailabilityStatus::Available->value,
        'moderation_status' => ProductModerationStatus::Active->value,
    ];

    $this->actingAs($seller)->post(route('seller.shops.products.store', $shop), $payload);
    $this->actingAs($seller)->post(route('seller.shops.products.store', $shop), $payload);

    $this->assertDatabaseHas('products', ['shop_id' => $shop->id, 'slug' => 'camisa-azul']);
    $this->assertDatabaseHas('products', ['shop_id' => $shop->id, 'slug' => 'camisa-azul-2']);
});

test('different shops can have products with the same slug', function () {
    $sellerA = User::factory()->create(['email_verified_at' => now()]);
    $sellerB = User::factory()->create(['email_verified_at' => now()]);
    $shopA = Shop::factory()->for($sellerA)->create();
    $shopB = Shop::factory()->for($sellerB)->create();

    $payload = [
        'name' => 'Cafe Espresso',
        'price' => 150,
        'availability_status' => ProductAvailabilityStatus::Available->value,
        'moderation_status' => ProductModerationStatus::Active->value,
    ];

    $this->actingAs($sellerA)->post(route('seller.shops.products.store', $shopA), $payload);
    $this->actingAs($sellerB)->post(route('seller.shops.products.store', $shopB), $payload);

    $this->assertDatabaseHas('products', ['shop_id' => $shopA->id, 'slug' => 'cafe-espresso']);
    $this->assertDatabaseHas('products', ['shop_id' => $shopB->id, 'slug' => 'cafe-espresso']);
});

test('a seller cannot exceed the free limit of products per shop', function () {
    config()->set('catalog.free.max_products_per_shop', 2);

    $seller = User::factory()->create(['email_verified_at' => now()]);
    $shop = Shop::factory()->for($seller)->create();

    Product::factory()->for($shop)->count(2)->create();

    $response = $this->actingAs($seller)->post(route('seller.shops.products.store', $shop), [
        'name' => 'Tercer producto excedido',
        'price' => 500,
        'availability_status' => ProductAvailabilityStatus::Available->value,
        'moderation_status' => ProductModerationStatus::Active->value,
    ]);

    $response->assertStatus(422);
    expect($shop->products()->count())->toBe(2);
});

test('a seller can update their product', function () {
    $seller = User::factory()->create(['email_verified_at' => now()]);
    $shop = Shop::factory()->for($seller)->create();
    $product = Product::factory()->for($shop)->create([
        'name' => 'Nombre Viejo',
        'price' => 500,
        'availability_status' => ProductAvailabilityStatus::Available,
    ]);

    $response = $this->actingAs($seller)->put(route('seller.shops.products.update', [$shop, $product]), [
        'name' => 'Nombre Nuevo',
        'price' => 850,
        'availability_status' => ProductAvailabilityStatus::OutOfStock->value,
        'moderation_status' => ProductModerationStatus::Active->value,
    ]);

    $response->assertRedirect(route('seller.shops.products.index', $shop));

    $product->refresh();
    expect($product->name)->toBe('Nombre Nuevo')
        ->and((float) $product->price)->toBe(850.0)
        ->and($product->availability_status)->toBe(ProductAvailabilityStatus::OutOfStock);
});

test('a seller can delete and restore their product', function () {
    $seller = User::factory()->create(['email_verified_at' => now()]);
    $shop = Shop::factory()->for($seller)->create();
    $product = Product::factory()->for($shop)->create();

    // Soft delete
    $this->actingAs($seller)->delete(route('seller.shops.products.destroy', [$shop, $product]))
        ->assertRedirect(route('seller.shops.products.index', $shop));

    expect($product->fresh()->trashed())->toBeTrue();

    // Restore
    $this->actingAs($seller)->post(route('seller.shops.products.restore', [$shop, $product->id]))
        ->assertRedirect(route('seller.shops.products.index', $shop));

    expect($product->fresh()->trashed())->toBeFalse();
});
