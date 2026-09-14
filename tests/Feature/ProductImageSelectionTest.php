<?php

use App\Enums\ProductImageProcessingStatus;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a product image url only uses a ready image from an eager loaded collection', function () {
    $product = Product::factory()->create();
    ProductImage::factory()->for($product)->create(['processing_status' => ProductImageProcessingStatus::Pending, 'sort_order' => 0]);
    $readyImage = ProductImage::factory()->for($product)->create(['processing_status' => ProductImageProcessingStatus::Ready, 'sort_order' => 1]);

    $product->load('images');

    expect($product->image_url)->toBe($readyImage->url);
});
