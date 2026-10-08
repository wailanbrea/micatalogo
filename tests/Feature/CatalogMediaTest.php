<?php

use App\Jobs\ResolveProductCatalogMediaJob;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\User;
use App\Services\CatalogMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function openBeautyFactsResponses(): void
{
    $image = UploadedFile::fake()->image('frontal.jpg', 800, 800);
    $imageBytes = file_get_contents($image->getRealPath());

    Http::fake([
        'https://world.openbeautyfacts.org/api/v2/product/*' => Http::response([
            'code' => '7501234567890',
            'status' => 1,
            'product' => [
                'code' => '7501234567890',
                'product_name' => 'Crema de prueba',
                'generic_name' => 'Crema corporal',
                'brands' => 'Marca Demo',
                'categories' => 'Cuidado corporal',
                'quantity' => '250 ml',
                'image_front_url' => 'https://images.openfoodfacts.org/images/products/750/123/456/7890/front_en.400.jpg',
                'image_front_license' => 'CC BY-SA',
                'image_front_attribution' => 'Open Beauty Facts',
            ],
        ]),
        'https://images.openfoodfacts.org/*' => Http::response($imageBytes, 200, ['Content-Type' => 'image/jpeg']),
    ]);
}

test('it caches provider data, stores provenance and keeps external images out of the product until selected', function () {
    Storage::fake('public');
    openBeautyFactsResponses();

    $seller = User::factory()->create(['email_verified_at' => now()]);
    $shop = Shop::factory()->for($seller)->create();
    $product = Product::factory()->for($shop)->create(['barcode' => '750 1234-567890']);

    $catalogProduct = app(CatalogMediaService::class)->resolveProduct($product);

    expect($catalogProduct->lookup_status)->toBe('found')
        ->and($catalogProduct->barcode)->toBe('7501234567890')
        ->and($catalogProduct->name)->toBe('Crema de prueba')
        ->and($catalogProduct->images)->toHaveCount(1)
        ->and($catalogProduct->images->first()->processing_status)->toBe('ready');

    $product->refresh();
    expect($product->catalog_product_id)->toBe($catalogProduct->id)
        ->and($product->images()->count())->toBe(0);

    $image = $catalogProduct->images->first();
    expect(Storage::disk('public')->exists($image->object_key))->toBeTrue()
        ->and(Storage::disk('public')->exists($image->thumbnail_object_key))->toBeTrue();

    app(CatalogMediaService::class)->resolveProduct($product);
    Http::assertSentCount(2);
});

test('a selected catalog image is added without replacing a manual image', function () {
    Storage::fake('public');
    openBeautyFactsResponses();

    $seller = User::factory()->create(['email_verified_at' => now()]);
    $shop = Shop::factory()->for($seller)->create();
    $product = Product::factory()->for($shop)->create(['barcode' => '7501234567890']);
    $manualImage = ProductImage::factory()->for($product)->create(['source' => 'manual']);

    $catalogProduct = app(CatalogMediaService::class)->resolveProduct($product);
    $catalogImage = $catalogProduct->images->first();
    app(CatalogMediaService::class)->attachImage($product, $catalogImage);

    expect($product->images()->count())->toBe(2)
        ->and($product->images()->where('source', 'manual')->first()->id)->toBe($manualImage->id)
        ->and($product->images()->where('source', 'catalog')->first()->catalog_product_image_id)->toBe($catalogImage->id);
});

test('web seller can queue catalog resolution and select a ready image through named routes', function () {
    Storage::fake('public');
    Queue::fake();
    openBeautyFactsResponses();

    $seller = User::factory()->create(['email_verified_at' => now()]);
    $shop = Shop::factory()->for($seller)->create();
    $product = Product::factory()->for($shop)->create(['barcode' => '7501234567890']);

    $this->actingAs($seller)
        ->post(route('seller.shops.products.catalog-media.resolve', [$shop, $product]))
        ->assertRedirect()
        ->assertSessionHas('status', 'La consulta de Open Beauty Facts quedó en cola.');

    Queue::assertPushed(ResolveProductCatalogMediaJob::class, fn ($job) => $job->productId === $product->id);

    $catalogProduct = app(CatalogMediaService::class)->resolveProduct($product);
    $catalogImage = $catalogProduct->images->firstOrFail();

    $this->actingAs($seller)
        ->post(route('seller.shops.products.catalog-media.use', [$shop, $product, $catalogImage->id]))
        ->assertRedirect()
        ->assertSessionHas('status', 'La imagen externa se agregó sin reemplazar las fotos existentes.');

    $this->assertDatabaseHas('product_images', [
        'product_id' => $product->id,
        'catalog_product_image_id' => $catalogImage->id,
        'source' => 'catalog',
        'processing_status' => 'ready',
    ]);
});

test('catalog media endpoint requires a scoped API token and returns license metadata', function () {
    Storage::fake('public');
    openBeautyFactsResponses();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $token = $user->createToken('test', ['catalog:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/catalog/media/7501234567890')
        ->assertOk()
        ->assertJsonPath('status', 'found')
        ->assertJsonPath('barcode', '7501234567890')
        ->assertJsonPath('product.name', 'Crema de prueba')
        ->assertJsonPath('images.0.license', 'CC BY-SA');
});

test('invalid catalog barcode is rejected before contacting the provider', function () {
    Http::fake();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $token = $user->createToken('test', ['catalog:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/catalog/media/123')
        ->assertStatus(404);

    Http::assertNothingSent();
});
