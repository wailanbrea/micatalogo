<?php

use App\Enums\ProductImageProcessingStatus;
use App\Enums\UserPlan;
use App\Http\Controllers\SellerBusinessController;
use App\Models\InventoryLot;
use App\Models\Invoice;
use App\Models\MobileOperation;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\ShopSeller;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\MediaStorageService;
use App\Services\PlanLimitsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('mobile photos are immutable idempotent and promoted without deleting previous media', function () {
    Storage::fake('public');
    Storage::fake('r2');
    [$user, $shop, $product, $token] = mobileFixture();
    $old = ProductImage::factory()->for($product)->create(['sort_order' => 0]);
    $file = UploadedFile::fake()->image('movil.jpg', 40, 50);
    $bytes = file_get_contents($file->getRealPath());
    $payload = ['client_operation_uuid' => (string) Str::uuid(), 'type' => 'product_upsert',
        'product_id' => $product->public_id, 'image_base64' => base64_encode($bytes), 'image_sha256' => hash('sha256', $bytes)];
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $this->withToken($token)->postJson($url, $payload)->assertCreated();
    $this->withToken($token)->postJson($url, $payload)->assertCreated();
    expect($product->images()->count())->toBe(2)->and($old->fresh()->sort_order)->toBe(1);
    $new = $product->images()->whereKeyNot($old->id)->first();
    expect($new->processing_status)->toBe(ProductImageProcessingStatus::Ready)
        ->and($new->sort_order)->toBe(0);
    app(MediaStorageService::class)->disk()->assertExists($new->object_key);
    $payload['client_operation_uuid'] = (string) Str::uuid();
    $this->withToken($token)->postJson($url, $payload)->assertCreated();
    expect($product->images()->count())->toBe(2);
    $payload['client_operation_uuid'] = (string) Str::uuid();
    $payload['name'] = 'No debe guardarse';
    $payload['image_sha256'] = str_repeat('0', 64);
    $this->withToken($token)->postJson($url, $payload)->assertUnprocessable();
    expect($product->fresh()->name)->not->toBe('No debe guardarse')->and($product->images()->count())->toBe(2);
});

test('mobile photos enforce plan quota without deleting media or applying partial edits', function () {
    Storage::fake('public');
    Storage::fake('r2');
    [$user, $shop, $product, $token] = mobileFixture();
    ProductImage::factory()->for($product)->count(app(PlanLimitsService::class)->imageLimit($shop))->create();
    $before = $product->images()->pluck('id')->all();
    $file = UploadedFile::fake()->image('foto.jpg', 40, 40);
    $bytes = file_get_contents($file->getRealPath());
    $this->withToken($token)->postJson('/api/v1/shops/'.$shop->public_id.'/mobile-operations', [
        'client_operation_uuid' => (string) Str::uuid(), 'type' => 'product_upsert', 'product_id' => $product->public_id,
        'name' => 'Edición rechazada', 'image_base64' => base64_encode($bytes), 'image_sha256' => hash('sha256', $bytes),
    ])->assertUnprocessable();
    expect($product->images()->pluck('id')->all())->toBe($before)->and($product->fresh()->name)->not->toBe('Edición rechazada')
        ->and(MobileOperation::count())->toBe(0);
});

function mobileFixture(): array
{
    $user = User::factory()->create(['plan' => UserPlan::Premium]);
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create(['shop_id' => $shop->id, 'price' => 250, 'sale_unit' => 'unit']);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 5, 'cost_price' => 100]);

    return [$user, $shop, $product, $user->createToken('test', ['pos:write'])->plainTextToken];
}

test('mobile operations preserve seller menu boundaries by operation type', function () {
    [$owner, $shop, $product] = mobileFixture();
    expect($owner->ownsShop($shop))->toBeTrue()
        ->and(app(\App\Services\SellerMenuService::class)->visibleForUser($shop, $owner))->toContain('sales');

    $productsSeller = User::factory()->create();
    ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $productsSeller->id,
        'is_active' => true,
        'commission_type' => 'percentage',
        'commission_value' => 5,
        'menu_permissions' => ['products'],
    ]);
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $saleUuid = (string) Str::uuid();
    Sanctum::actingAs($owner, ['pos:write']);
    $this->postJson('/api/v1/shops/'.$shop->public_id.'/pos-sales', [
        'client_sale_uuid' => $saleUuid,
        'payment_status' => 'paid',
        'items' => [['product_id' => $product->public_id, 'quantity' => 1, 'unit_price' => '250.00']],
    ])->assertCreated();

    Sanctum::actingAs($productsSeller, ['pos:write']);
    $this->postJson($url, [
        'client_operation_uuid' => (string) Str::uuid(),
        'type' => 'product_upsert',
        'product_id' => $product->public_id,
        'name' => 'No debe editarse por seller',
        'price' => '300.00',
    ])->assertForbidden();
    expect($product->fresh()->name)->not->toBe('No debe editarse por seller');

    $inventorySeller = User::factory()->create();
    ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $inventorySeller->id,
        'is_active' => true,
        'commission_type' => 'percentage',
        'commission_value' => 5,
        'menu_permissions' => ['inventory'],
    ]);
    Sanctum::actingAs($inventorySeller, ['pos:write']);
    $this->postJson($url, [
        'client_operation_uuid' => (string) Str::uuid(),
        'type' => 'restock',
        'product_id' => $product->public_id,
        'quantity' => 1,
        'unit_cost' => '100.00',
    ])->assertForbidden();
    expect($product->fresh()->inventory->stock_quantity)->toBe(4);

    $returnsSeller = User::factory()->create();
    ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $returnsSeller->id,
        'is_active' => true,
        'commission_type' => 'percentage',
        'commission_value' => 5,
        'menu_permissions' => ['returns'],
    ]);
    Sanctum::actingAs($returnsSeller, ['pos:write']);
    $this->postJson($url, [
        'client_operation_uuid' => (string) Str::uuid(),
        'type' => 'return',
        'client_sale_uuid' => $saleUuid,
        'items' => [['product_id' => $product->public_id, 'quantity' => 1, 'refund_price' => '250.00', 'restock' => true]],
    ])->assertCreated()->assertJsonPath('total', '250.00');
});

test('mobile operation role matrix allows manager and blocks accountant mutations', function () {
    [$owner, $shop, $product] = mobileFixture();
    $manager = User::factory()->create();
    ShopMember::create(['shop_id' => $shop->id, 'user_id' => $manager->id, 'role' => 'manager', 'is_active' => true]);
    $accountant = User::factory()->create();
    ShopMember::create(['shop_id' => $shop->id, 'user_id' => $accountant->id, 'role' => 'accountant', 'is_active' => true]);
    expect($accountant->fresh()->canSellAtShop($shop))->toBeFalse();
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';

    $managerToken = $manager->createToken('qa-manager', ['pos:write'])->plainTextToken;
    app('auth')->forgetGuards();
    $this->withToken($managerToken)->getJson('/api/v1/me')->assertOk()->assertJsonPath('email', $manager->email);
    $this->withToken($managerToken)->postJson($url, [
        'client_operation_uuid' => (string) Str::uuid(),
        'type' => 'restock',
        'product_id' => $product->public_id,
        'quantity' => 1,
        'unit_cost' => '100.00',
    ])->assertCreated();
    expect($product->fresh()->inventory->stock_quantity)->toBe(6);

    $accountantToken = $accountant->createToken('qa-accountant', ['pos:write'])->plainTextToken;
    app('auth')->forgetGuards();
    $this->withToken($accountantToken)->getJson('/api/v1/me')->assertOk()->assertJsonPath('email', $accountant->email);
    app('auth')->forgetGuards();
    $this->withToken($accountantToken)->postJson($url, [
        'client_operation_uuid' => (string) Str::uuid(),
        'type' => 'restock',
        'product_id' => $product->public_id,
        'quantity' => 1,
        'unit_cost' => '100.00',
    ])->assertNotFound();
    expect($product->fresh()->inventory->stock_quantity)->toBe(6);

    expect(app(\App\Services\SellerMenuService::class)->visibleForUser($shop, $manager))
        ->toContain('inventory')
        ->and(app(\App\Services\SellerMenuService::class)->visibleForUser($shop, $accountant))
        ->toBe(['finance', 'reports']);
});

test('platform admin cannot mutate a shop through the seller mobile operation endpoint', function () {
    [$owner, $shop, $product] = mobileFixture();
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('qa-admin', ['pos:write'])->plainTextToken;
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';

    $this->withToken($token)->postJson($url, [
        'client_operation_uuid' => (string) Str::uuid(),
        'type' => 'restock',
        'product_id' => $product->public_id,
        'quantity' => 1,
        'unit_cost' => '100.00',
    ])->assertNotFound();

    expect($product->fresh()->inventory->stock_quantity)->toBe(5)
        ->and($admin->canSellAtShop($shop))->toBeFalse();
});

test('mobile refund includes global invoice tax without turning it into profit', function () {
    [$user, $shop, $product, $token] = mobileFixture();
    $saleUuid = (string) Str::uuid();
    $this->withToken($token)->postJson('/api/v1/shops/'.$shop->public_id.'/pos-sales', [
        'client_sale_uuid' => $saleUuid, 'payment_status' => 'paid', 'tax' => '45.00',
        'items' => [['product_id' => $product->public_id, 'quantity' => 1, 'unit_price' => '250.00']],
    ])->assertCreated()->assertJsonPath('total', '295.00');
    $this->withToken($token)->postJson('/api/v1/shops/'.$shop->public_id.'/mobile-operations', [
        'client_operation_uuid' => (string) Str::uuid(), 'type' => 'return', 'client_sale_uuid' => $saleUuid,
        'items' => [['product_id' => $product->public_id, 'quantity' => 1, 'refund_price' => '295.00', 'restock' => true]],
    ])->assertCreated()->assertJsonPath('total', '295.00');
    expect((float) DB::table('invoice_return_items')->value('tax_refund'))->toBe(45.0)
        ->and($product->fresh()->inventory->gross_profit)->toBe(0.0);
});

test('mobile receipts are idempotent and preserve old FIFO lots', function () {
    [$user, $shop, $product, $token] = mobileFixture();
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $payload = ['client_operation_uuid' => (string) Str::uuid(), 'type' => 'restock', 'product_id' => $product->public_id, 'quantity' => 2, 'unit_cost' => '300.00'];
    $this->withToken($token)->postJson($url, $payload)->assertCreated()->assertJsonPath('stock', 7);
    $this->withToken($token)->postJson($url, $payload)->assertCreated()->assertJsonPath('stock', 7);
    expect(InventoryLot::count())->toBe(2)->and(MobileOperation::count())->toBe(1)
        ->and((int) InventoryLot::oldest('id')->first()->received_cost_cents)->toBe(50000);
    $payload['quantity'] = 3;
    $this->withToken($token)->postJson($url, $payload)->assertStatus(409);
    expect($product->fresh()->inventory->stock_quantity)->toBe(7);
});

test('mobile product creation and edits are scoped and keep offers meaningful', function () {
    [$user, $shop, $product, $token] = mobileFixture();
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $id = (string) Str::ulid();
    $create = ['client_operation_uuid' => (string) Str::uuid(), 'type' => 'product_upsert', 'product_id' => $id,
        'name' => 'Producto móvil', 'price' => '45.00', 'cost_price' => '20.00', 'category_name' => 'Accesorios', 'internal_code' => '000123'];
    $this->withToken($token)->postJson($url, $create)->assertCreated()->assertJsonPath('product_id', $id);
    $this->withToken($token)->postJson($url, $create)->assertCreated();
    expect($shop->products()->count())->toBe(2)->and($shop->categories()->count())->toBe(1);
    $product->update(['sale_price' => 200]);
    $edit = ['client_operation_uuid' => (string) Str::uuid(), 'type' => 'product_upsert', 'product_id' => $product->public_id,
        'name' => 'Nuevo nombre', 'expected_price' => '200.00', 'price' => '180.00'];
    $this->withToken($token)->postJson($url, $edit)->assertCreated()->assertJsonPath('price', '180.00');
    expect((float) $product->fresh()->price)->toBe(250.0);
    $edit['client_operation_uuid'] = (string) Str::uuid();
    $this->withToken($token)->postJson($url, $edit)->assertStatus(409);
    $other = Shop::factory()->create();
    $this->withToken($token)->postJson('/api/v1/shops/'.$other->public_id.'/mobile-operations', $create)->assertNotFound();
});

test('mobile product creation supports validated decant presentations linked to source ml', function () {
    $user = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->create(['user_id' => $user->id, 'business_type' => 'perfume_store']);
    $source = Product::factory()->create([
        'shop_id' => $shop->id,
        'name' => 'Botella fuente',
        'price' => 2800,
        'sale_unit' => 'bottle',
        'volume_ml' => 100,
    ]);
    ProductInventory::create([
        'product_id' => $source->id,
        'track_inventory' => true,
        'stock_quantity' => 4,
        'available_ml' => 400,
        'cost_price' => 1200,
    ]);
    $token = $user->createToken('test', ['pos:write'])->plainTextToken;
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $id = (string) Str::ulid();
    $payload = [
        'client_operation_uuid' => (string) Str::uuid(),
        'type' => 'product_upsert',
        'product_id' => $id,
        'name' => 'Decant fuente 5 ml',
        'price' => '250.00',
        'sale_unit' => 'decant',
        'volume_ml' => 5,
        'inventory_source_product_id' => $source->public_id,
    ];

    $this->withToken($token)->postJson($url, $payload)->assertCreated()->assertJsonPath('product_id', $id);
    $this->withToken($token)->postJson($url, $payload)->assertCreated();

    $decant = Product::where('public_id', $id)->firstOrFail();
    expect($decant->sale_unit)->toBe('decant')
        ->and($decant->volume_ml)->toBe(5)
        ->and($decant->inventory_source_product_id)->toBe($source->id)
        ->and($decant->inventory->stock_quantity)->toBe(80)
        ->and($decant->availability_status->value)->toBe('available')
        ->and(Product::where('shop_id', $shop->id)->count())->toBe(2);

    $invalid = $payload;
    $invalid['client_operation_uuid'] = (string) Str::uuid();
    $invalid['product_id'] = (string) Str::ulid();
    $invalid['volume_ml'] = 101;
    $this->withToken($token)->postJson($url, $invalid)->assertUnprocessable();
    expect(Product::where('public_id', $invalid['product_id'])->exists())->toBeFalse();
});

test('mobile archive is reversible and adjustment rejects stale inventory', function () {
    [$user, $shop, $product, $token] = mobileFixture();
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $adjust = ['client_operation_uuid' => (string) Str::uuid(), 'type' => 'adjustment', 'product_id' => $product->public_id,
        'stock' => 10, 'expected_stock' => 4, 'notes' => 'Conteo físico'];
    $this->withToken($token)->postJson($url, $adjust)->assertStatus(409);
    expect($product->fresh()->inventory->stock_quantity)->toBe(5)->and(MobileOperation::count())->toBe(0);
    $archive = ['client_operation_uuid' => (string) Str::uuid(), 'type' => 'product_archive', 'product_id' => $product->public_id];
    $this->withToken($token)->postJson($url, $archive)->assertCreated();
    $this->withToken($token)->postJson($url, $archive)->assertCreated();
    expect(Product::withTrashed()->find($product->id)->trashed())->toBeTrue()->and(ProductInventory::count())->toBe(1);
});

test('mobile bottle count detects decant sales even when whole bottle stock is unchanged', function () {
    [$user, $shop, $product, $token] = mobileFixture();
    $product->update(['sale_unit' => 'bottle', 'volume_ml' => 100]);
    $product->inventory->update(['stock_quantity' => 5, 'available_ml' => 550]);
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $payload = ['client_operation_uuid' => (string) Str::uuid(), 'type' => 'adjustment',
        'product_id' => $product->public_id, 'expected_stock' => 5, 'expected_available_ml' => 590,
        'stock' => 4, 'notes' => 'Conteo de botellas'];
    $this->withToken($token)->postJson($url, $payload)->assertStatus(409);
    expect($product->fresh()->inventory->available_ml)->toBe(550)->and(MobileOperation::count())->toBe(0);
    $payload['expected_available_ml'] = 550;
    $this->withToken($token)->postJson($url, $payload)->assertCreated()->assertJsonPath('stock', 4);
    expect($product->fresh()->inventory->available_ml)->toBe(400);
});

test('mobile opening a bottle is idempotent and preserves total source ml', function () {
    $user = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->create(['user_id' => $user->id, 'business_type' => 'perfume_store']);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'name' => 'Perfume fuente móvil',
        'sale_unit' => 'bottle',
        'volume_ml' => 100,
        'price' => 2800,
    ]);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 4,
        'available_ml' => 400,
        'cost_price' => 1200,
    ]);
    $token = $user->createToken('test', ['pos:write'])->plainTextToken;
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $payload = [
        'client_operation_uuid' => (string) Str::uuid(),
        'type' => 'open_bottle',
        'product_id' => $product->public_id,
        'quantity' => 1,
        'expected_stock' => 4,
        'expected_available_ml' => 400,
        'notes' => 'Preparación de decants',
    ];

    $this->withToken($token)->postJson($url, $payload)
        ->assertCreated()
        ->assertJsonPath('stock', 3)
        ->assertJsonPath('opened_bottles', 1)
        ->assertJsonPath('available_ml', 400);
    $this->withToken($token)->postJson($url, $payload)->assertCreated();
    expect($product->fresh()->inventory->stock_quantity)->toBe(3)
        ->and($product->fresh()->inventory->opened_bottles)->toBe(1)
        ->and($product->fresh()->inventory->available_ml)->toBe(400)
        ->and(DB::table('inventory_movements')->where('type', 'opening')->count())->toBe(1);

    $stale = $payload;
    $stale['client_operation_uuid'] = (string) Str::uuid();
    $this->withToken($token)->postJson($url, $stale)->assertStatus(409);
    expect($product->fresh()->inventory->stock_quantity)->toBe(3);
});

test('mobile returns restore original sold cost not the latest receipt cost and cannot duplicate', function () {
    [$user, $shop, $product, $token] = mobileFixture();
    $saleUuid = (string) Str::uuid();
    $this->withToken($token)->postJson('/api/v1/shops/'.$shop->public_id.'/pos-sales', ['client_sale_uuid' => $saleUuid,
        'payment_status' => 'paid', 'items' => [['product_id' => $product->public_id, 'quantity' => 2, 'unit_price' => '250.00']]])->assertCreated();
    app(InventoryService::class)->recordRestock($product, 2, null, $user->id, 300);
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $refund = ['client_operation_uuid' => (string) Str::uuid(), 'type' => 'return', 'client_sale_uuid' => $saleUuid,
        'items' => [['product_id' => $product->public_id, 'quantity' => 1, 'refund_price' => '250.00', 'restock' => true]]];
    $this->withToken($token)->postJson($url, $refund)->assertCreated()->assertJsonPath('total', '250.00');
    $this->withToken($token)->postJson($url, $refund)->assertCreated();
    expect(DB::table('invoice_returns')->count())->toBe(1)->and((int) DB::table('invoice_return_items')->value('total_cost_cents'))->toBe(10000)
        ->and($product->fresh()->inventory->stock_quantity)->toBe(6)
        ->and($product->fresh()->inventory->inventory_value)->toBe(1000.0)
        ->and((float) $product->fresh()->inventory->cost_price)->toBe(300.0);
    expect($product->fresh()->inventory->gross_profit)->toBe(150.0);
    $report = app(SellerBusinessController::class)->index(Request::create('/'), $shop)->getData();
    expect((float) $report['total'])->toBe(250.0)
        ->and((float) $report['products']->first()->revenue)->toBe(250.0)
        ->and((float) $report['products']->first()->known_cost)->toBe(100.0);
    $refund['client_operation_uuid'] = (string) Str::uuid();
    $refund['items'][0]['quantity'] = 2;
    $this->withToken($token)->postJson($url, $refund)->assertUnprocessable();
    expect(DB::table('invoice_returns')->count())->toBe(1)->and($product->fresh()->inventory->stock_quantity)->toBe(6);
});

test('mobile return cannot refund more than the discounted amount originally charged', function () {
    [$user, $shop, $product, $token] = mobileFixture();
    $saleUuid = (string) Str::uuid();
    $this->withToken($token)->postJson('/api/v1/shops/'.$shop->public_id.'/pos-sales', ['client_sale_uuid' => $saleUuid,
        'payment_status' => 'paid', 'discount' => '50.00', 'items' => [['product_id' => $product->public_id, 'quantity' => 1, 'unit_price' => '250.00']]])->assertCreated();
    $this->withToken($token)->postJson('/api/v1/shops/'.$shop->public_id.'/mobile-operations', [
        'client_operation_uuid' => (string) Str::uuid(), 'type' => 'return', 'client_sale_uuid' => $saleUuid,
        'items' => [['product_id' => $product->public_id, 'quantity' => 1, 'refund_price' => '250.00', 'restock' => true]]])->assertUnprocessable();
    expect(DB::table('invoice_returns')->count())->toBe(0)->and($product->fresh()->inventory->stock_quantity)->toBe(4);
});

test('mobile partial returns conserve discounted cents and remain replay safe', function () {
    [$user, $shop, $product, $token] = mobileFixture();
    $saleUuid = (string) Str::uuid();
    $this->withToken($token)->postJson('/api/v1/shops/'.$shop->public_id.'/pos-sales', [
        'client_sale_uuid' => $saleUuid, 'payment_status' => 'paid', 'discount' => '0.01',
        'items' => [['product_id' => $product->public_id, 'quantity' => 3, 'unit_price' => '250.00']],
    ])->assertCreated()->assertJsonPath('total', '749.99');
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $first = ['client_operation_uuid' => (string) Str::uuid(), 'type' => 'return', 'client_sale_uuid' => $saleUuid,
        'items' => [['product_id' => $product->public_id, 'quantity' => 1, 'refund_price' => '249.99', 'refund_total' => '249.99', 'restock' => true]]];
    $this->withToken($token)->postJson($url, $first)->assertCreated()->assertJsonPath('total', '249.99');
    $this->withToken($token)->postJson($url, $first)->assertCreated();
    $second = $first;
    $second['client_operation_uuid'] = (string) Str::uuid();
    $second['items'][0] = ['product_id' => $product->public_id, 'quantity' => 2, 'refund_price' => '250.00', 'refund_total' => '500.00', 'restock' => true];
    $this->withToken($token)->postJson($url, $second)->assertCreated()->assertJsonPath('total', '500.00');
    expect(round((float) DB::table('invoice_returns')->sum('total'), 2))->toBe(749.99)
        ->and($product->fresh()->inventory->stock_quantity)->toBe(5)
        ->and($product->fresh()->inventory->gross_profit)->toBe(0.0);
    $second['client_operation_uuid'] = (string) Str::uuid();
    $this->withToken($token)->postJson($url, $second)->assertUnprocessable();
    expect(DB::table('invoice_returns')->count())->toBe(2);
});

test('mobile decant returns restore shared ml and reject changed source or presentation', function () {
    [$user, $shop, $source, $token] = mobileFixture();
    $source->update(['sale_unit' => 'bottle', 'volume_ml' => 100]);
    $source->inventory->update(['stock_quantity' => 1, 'available_ml' => 100]);
    $decant = Product::factory()->create(['shop_id' => $shop->id, 'price' => 10, 'sale_unit' => 'decant', 'volume_ml' => 5,
        'inventory_source_product_id' => $source->id]);
    ProductInventory::create(['product_id' => $decant->id, 'track_inventory' => true, 'stock_quantity' => 20]);
    $saleUuid = (string) Str::uuid();
    $this->withToken($token)->postJson('/api/v1/shops/'.$shop->public_id.'/pos-sales', [
        'client_sale_uuid' => $saleUuid, 'payment_status' => 'paid',
        'items' => [['product_id' => $decant->public_id, 'quantity' => 2, 'unit_price' => '10.00']],
    ])->assertCreated();
    $item = Invoice::first()->items()->first();
    expect((int) $item->inventory_source_product_id)->toBe($source->id);
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $refund = ['client_operation_uuid' => (string) Str::uuid(), 'type' => 'return', 'client_sale_uuid' => $saleUuid,
        'items' => [['product_id' => $decant->public_id, 'quantity' => 1, 'refund_price' => '10.00', 'restock' => true]]];
    $decant->update(['volume_ml' => 6]);
    $this->withToken($token)->postJson($url, $refund)->assertUnprocessable();
    expect(DB::table('invoice_returns')->count())->toBe(0)->and($source->fresh()->inventory->available_ml)->toBe(90);
    $decant->update(['volume_ml' => 5]);
    $this->withToken($token)->postJson($url, $refund)->assertCreated();
    expect($source->fresh()->inventory->available_ml)->toBe(95)
        ->and($decant->fresh()->inventory->stock_quantity)->toBe(19)
        ->and((int) DB::table('invoice_return_items')->value('total_cost_cents'))->toBe(500);
});

test('fully discounted invoices still refund global tax exactly', function () {
    [$user, $shop, $product, $token] = mobileFixture();
    $saleUuid = (string) Str::uuid();
    $this->withToken($token)->postJson('/api/v1/shops/'.$shop->public_id.'/pos-sales', [
        'client_sale_uuid' => $saleUuid, 'payment_status' => 'paid', 'discount' => '250.00', 'tax' => '0.01',
        'items' => [['product_id' => $product->public_id, 'quantity' => 1, 'unit_price' => '250.00']],
    ])->assertCreated()->assertJsonPath('total', '0.01');
    $this->withToken($token)->postJson('/api/v1/shops/'.$shop->public_id.'/mobile-operations', [
        'client_operation_uuid' => (string) Str::uuid(), 'type' => 'return', 'client_sale_uuid' => $saleUuid,
        'items' => [['product_id' => $product->public_id, 'quantity' => 1, 'refund_price' => '0.00', 'refund_total' => '0.01', 'restock' => true]],
    ])->assertCreated()->assertJsonPath('total', '0.01');
    expect((float) DB::table('invoice_return_items')->value('tax_refund'))->toBe(0.01);
});
