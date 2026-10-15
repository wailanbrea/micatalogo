<?php

use App\Models\MobileOperation;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;
use App\Services\MediaStorageService;
use App\Services\PlanLimitsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function navigationPhotoFixture(): array
{
    Storage::fake('public');
    Storage::fake('r2');
    Storage::fake('temp');
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create(['business_type' => 'perfume_store']);
    $seller = User::factory()->create();
    $assignment = ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $seller->id, 'is_active' => true,
        'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions' => ['photos']]);
    $product = Product::factory()->for($shop)->create(['price' => '900.00', 'sale_unit' => 'unit']);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 3, 'cost_price' => '240.00']);
    $file = UploadedFile::fake()->image('QA_NAV_photo.jpg', 40, 50);
    $bytes = file_get_contents($file->getRealPath());
    $payload = ['client_operation_uuid' => (string) Str::uuid(), 'type' => 'product_photo_update',
        'product_id' => $product->public_id, 'image_base64' => base64_encode($bytes), 'image_sha256' => hash('sha256', $bytes)];

    return [$shop, $seller, $product->refresh(), $assignment, $payload];
}

test('photos only delegation uploads from web without granting catalog metadata editing', function () {
    [$shop, $seller, $product] = navigationPhotoFixture();
    Queue::fake();
    $before = $product->getAttributes();
    $inventory = $product->inventory->getAttributes();
    $this->actingAs($seller)->get(route('seller.shops.feature', [$shop, 'photos']))->assertOk();
    $this->get(route('seller.shops.products.edit', [$shop, $product]))->assertForbidden();
    $this->putJson(route('seller.shops.products.update', [$shop, $product]), ['name' => 'Forbidden'])->assertForbidden();
    $this->post(route('seller.shops.products.images.store', [$shop, $product]), [
        'image' => UploadedFile::fake()->image('QA_NAV_web.jpg', 40, 50),
    ])->assertRedirect();
    expect($product->fresh()->getAttributes())->toBe($before)
        ->and($product->inventory->fresh()->getAttributes())->toBe($inventory)
        ->and($product->images()->count())->toBe(1);
});

test('photos only mobile operation confirms image identity once and preserves product inventory and previous media', function () {
    [$shop, $seller, $product, , $payload] = navigationPhotoFixture();
    Sanctum::actingAs($seller, ['pos:write', 'catalog:read']);
    $old = ProductImage::factory()->for($product)->create(['sort_order' => 0]);
    $before = $product->getAttributes();
    $inventory = $product->inventory->getAttributes();
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $first = $this->postJson($url, $payload)->assertCreated()->assertJsonPath('product_id', $product->public_id)
        ->assertJsonPath('image_sha256', $payload['image_sha256'])->assertJsonPath('client_operation_uuid', $payload['client_operation_uuid']);
    $this->postJson($url, $payload)->assertCreated()->assertExactJson($first->json());
    $new = $product->images()->whereKeyNot($old->id)->firstOrFail();
    app(MediaStorageService::class)->disk()->assertExists($new->object_key);
    expect($product->fresh()->getAttributes())->toBe($before)
        ->and($product->inventory->fresh()->getAttributes())->toBe($inventory)
        ->and($product->images()->count())->toBe(2)->and($old->fresh()->sort_order)->toBe(1)
        ->and(MobileOperation::count())->toBe(1);
    $snapshot = $this->getJson('/api/v1/shops/'.$shop->public_id.'/catalog')->assertOk();
    expect(collect($snapshot->json('products'))->pluck('id')->all())->toContain($product->public_id);
    expect(json_encode($snapshot->json('products'), JSON_THROW_ON_ERROR))->not->toContain('240.00');
});

test('photo envelopes reject metadata smuggling without partially changing the product', function () {
    [$shop, $seller, $product, , $payload] = navigationPhotoFixture();
    Sanctum::actingAs($seller, ['pos:write']);
    $before = $product->getAttributes();
    foreach (['name' => 'Forbidden', 'price' => '1.00', 'cost_price' => '1.00', 'stock' => 0,
        'sale_unit' => 'service', 'published' => false, 'minimum_stock' => 1, 'barcode' => '12345678',
        'category_uuid' => (string) Str::uuid(), 'combo_items' => [['product_id' => $product->public_id, 'quantity' => 1]]] as $key => $value) {
        $this->postJson('/api/v1/shops/'.$shop->public_id.'/mobile-operations', $payload + [$key => $value])
            ->assertUnprocessable()->assertJsonValidationErrors($key);
    }
    expect($product->fresh()->getAttributes())->toBe($before)->and($product->images()->count())->toBe(0)
        ->and($product->inventory->fresh()->stock_quantity)->toBe(3)->and(MobileOperation::count())->toBe(0);
});

test('photo replay revalidates current permission and tenant before returning a prior acknowledgement', function () {
    [$shop, $seller, $product, $assignment, $payload] = navigationPhotoFixture();
    Sanctum::actingAs($seller, ['pos:write']);
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $this->postJson($url, $payload)->assertCreated();
    $assignment->update(['menu_permissions' => ['products']]);
    $this->postJson($url, $payload)->assertForbidden();
    $assignment->update(['menu_permissions' => ['photos']]);
    $this->postJson($url, $payload)->assertCreated();
    $other = Shop::factory()->create();
    $external = Product::factory()->for($other)->create();
    $foreign = array_replace($payload, ['client_operation_uuid' => (string) Str::uuid(), 'product_id' => $external->public_id]);
    $this->postJson($url, $foreign)->assertNotFound();
    $this->postJson('/api/v1/shops/'.$other->public_id.'/mobile-operations', $payload)->assertNotFound();
    expect($product->images()->count())->toBe(1)->and($external->images()->count())->toBe(0)
        ->and(MobileOperation::count())->toBe(1);
});

test('invalid photo checksum and exhausted image quota leave no receipt or partial changes', function () {
    [$shop, $seller, $product, , $payload] = navigationPhotoFixture();
    Sanctum::actingAs($seller, ['pos:write']);
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $this->postJson($url, array_replace($payload, ['image_sha256' => str_repeat('0', 64)]))->assertUnprocessable();
    expect($product->images()->count())->toBe(0)->and(MobileOperation::count())->toBe(0);
    ProductImage::factory()->for($product)->count(app(PlanLimitsService::class)->imageLimit($shop))->create();
    $before = $product->images()->get()->map->getAttributes()->all();
    $this->postJson($url, $payload)->assertUnprocessable();
    expect($product->images()->get()->map->getAttributes()->all())->toBe($before)->and(MobileOperation::count())->toBe(0);
});
