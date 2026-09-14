<?php

use App\Enums\ProductImageProcessingStatus;
use App\Jobs\ProcessProductImageJob;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\User;
use App\Services\MediaStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('a seller can upload an image for their product and job is dispatched', function () {
    Queue::fake();
    Storage::fake('temp');

    $seller = User::factory()->create(['email_verified_at' => now()]);
    $shop = Shop::factory()->for($seller)->create();
    $product = Product::factory()->for($shop)->create();

    $file = UploadedFile::fake()->image('producto.jpg', 600, 600);

    $response = $this->actingAs($seller)->post(route('seller.shops.products.images.store', [$shop, $product]), [
        'image' => $file,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('product_images', [
        'product_id' => $product->id,
        'processing_status' => ProductImageProcessingStatus::Pending->value,
    ]);

    Queue::assertPushed(ProcessProductImageJob::class);
});

test('a seller cannot exceed 3 images per product', function () {
    $seller = User::factory()->create(['email_verified_at' => now()]);
    $shop = Shop::factory()->for($seller)->create();
    $product = Product::factory()->for($shop)->create();

    ProductImage::factory()->for($product)->count(3)->create();

    $file = UploadedFile::fake()->image('cuarta_foto.jpg', 600, 600);

    $response = $this->actingAs($seller)->post(route('seller.shops.products.images.store', [$shop, $product]), [
        'image' => $file,
    ]);

    $response->assertStatus(422);
    expect($product->images()->count())->toBe(3);
});

test('a seller cannot upload images to another sellers product', function () {
    $sellerA = User::factory()->create(['email_verified_at' => now()]);
    $sellerB = User::factory()->create(['email_verified_at' => now()]);
    $shopB = Shop::factory()->for($sellerB)->create();
    $productB = Product::factory()->for($shopB)->create();

    $file = UploadedFile::fake()->image('intruso.jpg', 600, 600);

    $response = $this->actingAs($sellerA)->post(route('seller.shops.products.images.store', [$shopB, $productB]), [
        'image' => $file,
    ]);

    $response->assertForbidden();
});

test('process product image job generates webp derivatives, deletes temp file, and marks ready', function () {
    Storage::fake('temp');
    Storage::fake('public');

    $seller = User::factory()->create(['email_verified_at' => now()]);
    $shop = Shop::factory()->for($seller)->create();
    $product = Product::factory()->for($shop)->create();

    // Create fake image file on temp disk
    $fakeFile = UploadedFile::fake()->image('test_product.jpg', 800, 800);
    $tempPath = Storage::disk('temp')->putFileAs('', $fakeFile, 'test_product.jpg');

    $productImage = $product->images()->create([
        'object_key' => "temp/{$tempPath}",
        'mime_type' => 'image/jpeg',
        'width' => 0,
        'height' => 0,
        'size_bytes' => 12000,
        'checksum_sha256' => hash('sha256', 'initial'),
        'processing_status' => ProductImageProcessingStatus::Pending,
    ]);

    $mediaStorage = app(MediaStorageService::class);
    $job = new ProcessProductImageJob($productImage->id, $tempPath);
    $job->handle($mediaStorage);

    $productImage->refresh();

    expect($productImage->processing_status)->toBe(ProductImageProcessingStatus::Ready)
        ->and($productImage->mime_type)->toBe('image/webp')
        ->and($productImage->width)->toBeGreaterThan(0)
        ->and($productImage->height)->toBeGreaterThan(0)
        ->and($productImage->object_key)->toStartWith('products/'.$product->public_id.'/main-')
        ->and($productImage->thumbnail_object_key)->toStartWith('products/'.$product->public_id.'/thumb-');

    // Verify temp file was purged
    expect(Storage::disk('temp')->exists($tempPath))->toBeFalse();

    // Verify persistent files were written
    expect($mediaStorage->disk()->exists($productImage->object_key))->toBeTrue()
        ->and($mediaStorage->disk()->exists($productImage->thumbnail_object_key))->toBeTrue();
});

test('a seller can delete an image from their product', function () {
    Storage::fake('public');

    $seller = User::factory()->create(['email_verified_at' => now()]);
    $shop = Shop::factory()->for($seller)->create();
    $product = Product::factory()->for($shop)->create();

    $image = ProductImage::factory()->for($product)->create([
        'object_key' => 'products/test/main.webp',
        'thumbnail_object_key' => 'products/test/thumb.webp',
        'processing_status' => ProductImageProcessingStatus::Ready,
    ]);

    Storage::disk('public')->put('products/test/main.webp', 'data');
    Storage::disk('public')->put('products/test/thumb.webp', 'data');

    $response = $this->actingAs($seller)->delete(
        route('seller.shops.products.images.destroy', [$shop, $product, $image])
    );

    $response->assertRedirect();
    $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    expect(Storage::disk('public')->exists('products/test/main.webp'))->toBeFalse()
        ->and(Storage::disk('public')->exists('products/test/thumb.webp'))->toBeFalse();
});
