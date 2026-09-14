<?php

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\ShopCategory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('catalog factories create public identifiers and relationships', function () {
    $product = Product::factory()->create();
    $image = ProductImage::factory()->for($product)->create();

    expect($product->public_id)->toHaveLength(26)
        ->and($product->availability_status)->toBe(ProductAvailabilityStatus::Available)
        ->and($product->moderation_status)->toBe(ProductModerationStatus::Draft)
        ->and($image->product->is($product))->toBeTrue();
});

test('shop category slugs are unique within their shop', function () {
    $shop = Shop::factory()->create();
    ShopCategory::factory()->for($shop)->create(['slug' => 'ofertas']);

    ShopCategory::factory()->for($shop)->create(['slug' => 'ofertas']);
})->throws(QueryException::class);
