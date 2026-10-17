<?php

use App\Enums\ProductAvailabilityStatus;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\ShopCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function legacyPerfumeCommandFixture(string $name): array
{
    $owner = User::factory()->create(['email' => 'luis@example.test', 'email_verified_at' => now()]);
    $shop = Shop::factory()->for($owner)->create([
        'slug' => 'rincon-del-todo-test',
        'business_type' => 'perfume_store',
    ]);
    $category = ShopCategory::factory()->for($shop)->create(['name' => 'Perfumes de hombre']);
    $product = Product::factory()->for($shop)->create([
        'shop_category_id' => $category->id,
        'name' => $name,
        'sale_unit' => 'unit',
        'volume_ml' => null,
        'availability_status' => ProductAvailabilityStatus::Available,
    ]);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 2,
        'sold_quantity' => 0,
        'cost_price' => 1000,
        'low_stock_threshold' => 1,
    ]);

    return [$owner, $shop, $product];
}

test('legacy perfume bottle command defaults to dry run and reports the resolved capacity', function () {
    [, $shop, $product] = legacyPerfumeCommandFixture('Perfume sin capacidad');

    $this->artisan('catalog:configure-legacy-perfume-bottles', [
        'shop' => $shop->slug,
        'owner-email' => 'luis@example.test',
    ])->expectsOutputToContain('100 ml (default)')->assertExitCode(0);

    expect($product->fresh()->sale_unit)->toBe('unit')
        ->and($product->fresh()->volume_ml)->toBeNull()
        ->and($product->inventory()->value('stock_quantity'))->toBe(2);
});

test('legacy perfume bottle command applies the name capacity through mobile operation flow', function () {
    [, $shop, $product] = legacyPerfumeCommandFixture('Al Haramain Gold Edition 60ML');

    $this->artisan('catalog:configure-legacy-perfume-bottles', [
        'shop' => $shop->slug,
        'owner-email' => 'luis@example.test',
        '--apply' => true,
    ])->expectsOutputToContain('60 ml (name)')->expectsOutputToContain('APLICADO')->assertExitCode(0);

    expect($product->fresh()->sale_unit)->toBe('bottle')
        ->and($product->fresh()->volume_ml)->toBe(60)
        ->and($product->inventory()->value('stock_quantity'))->toBe(2)
        ->and($product->inventory()->value('available_ml'))->toBe(120);
});
