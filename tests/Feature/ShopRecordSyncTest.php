<?php

use App\Enums\UserPlan;
use App\Models\MobileOperation;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopSyncRecord;
use App\Models\User;
use App\Services\ShopRecordSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function shopRecordPayload(string $type, ?string $id = null): array
{
    $base = ['type' => $type, 'client_operation_uuid' => (string) Str::uuid(), 'record_id' => $id ?? (string) Str::uuid(),
        'name' => 'QA '.Str::random(10), 'is_active' => true, 'is_deleted' => false, 'created_at' => '2026-10-12T10:00:00Z'];

    return $base + match ($type) {
        'category_upsert' => ['description' => 'Descripción QA', 'icon' => 'drink', 'sort_order' => 4],
        'unit_upsert' => ['abbreviation' => 'caj'],
        'adjustment_reason_upsert' => ['direction' => 'BOTH'],
    };
}

beforeEach(function () {
    $this->owner = User::factory()->create(['plan' => UserPlan::Premium]);
    $this->shop = Shop::factory()->create(['user_id' => $this->owner->id]);
    $this->other = Shop::factory()->create(['user_id' => $this->owner->id]);
    Sanctum::actingAs($this->owner, ['pos:write']);
    $this->url = '/api/v1/shops/'.$this->shop->public_id.'/mobile-operations';
});

test('store records replay exactly once and read back only in their shop', function (string $type, string $kind) {
    $payload = shopRecordPayload($type);
    $this->postJson($this->url, $payload)->assertCreated()->assertJsonPath('client_operation_uuid', $payload['client_operation_uuid']);
    $this->postJson($this->url, $payload)->assertCreated();
    expect(MobileOperation::count())->toBe(1)->and(ShopSyncRecord::count())->toBe(1);
    $catalog = $this->getJson('/api/v1/shops/'.$this->shop->public_id.'/catalog')->assertOk();
    if ($kind === 'category') {
        $catalog->assertJsonPath('categories.0.client_record_uuid', $payload['record_id'])->assertJsonPath('categories.0.icon', 'drink');
    } else {
        $catalog->assertJsonPath('store_records.0.kind', $kind)->assertJsonPath('store_records.0.record_id', $payload['record_id']);
    }
    $this->getJson('/api/v1/shops/'.$this->other->public_id.'/catalog')->assertOk()->assertJsonCount(0, 'categories')->assertJsonCount(0, 'store_records');
    $payload['name'] = 'Contenido diferente';
    $this->postJson($this->url, $payload)->assertConflict();
})->with([
    ['category_upsert', 'category'], ['unit_upsert', 'unit'], ['adjustment_reason_upsert', 'adjustment_reason'],
]);

test('editing deactivating and archiving records retains identity and original creation time', function (string $type) {
    $payload = shopRecordPayload($type);
    $this->postJson($this->url, $payload)->assertCreated();
    $payload['client_operation_uuid'] = (string) Str::uuid();
    $payload['name'] = 'Nuevo nombre QA';
    $payload['is_active'] = false;
    $payload['is_deleted'] = true;
    $payload['created_at'] = '2026-10-13T10:00:00Z';
    $this->postJson($this->url, $payload)->assertCreated()->assertJsonPath('record.created_at', '2026-10-12T10:00:00Z');
    expect(ShopSyncRecord::count())->toBe(1)->and(ShopSyncRecord::first()->payload['is_deleted'])->toBeTrue();
})->with(['category_upsert', 'unit_upsert', 'adjustment_reason_upsert']);

test('duplicate names and invalid payloads leave no partial record or operation', function (string $type) {
    $payload = shopRecordPayload($type);
    $this->postJson($this->url, $payload)->assertCreated();
    $duplicate = shopRecordPayload($type);
    $duplicate['name'] = $payload['name'];
    $this->postJson($this->url, $duplicate)->assertUnprocessable();
    $duplicate['name'] = '';
    $this->postJson($this->url, $duplicate)->assertUnprocessable();
    expect(ShopSyncRecord::count())->toBe(1)->and(MobileOperation::count())->toBe(1);
})->with(['category_upsert', 'unit_upsert', 'adjustment_reason_upsert']);

test('an existing web category is edited through its deterministic Android identity without duplicating it', function () {
    $category = $this->shop->categories()->create(['name' => 'Web', 'slug' => 'web', 'status' => 'active', 'sort_order' => 2]);
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'shop_category_id' => $category->id]);
    $id = app(ShopRecordSyncService::class)->localId($this->shop, 'category', (string) $category->id);
    $payload = shopRecordPayload('category_upsert', $id);
    $payload['name'] = 'Categoría editada';
    $this->postJson($this->url, $payload)->assertCreated();
    expect($this->shop->categories()->count())->toBe(1)->and($product->fresh()->shop_category_id)->toBe($category->id);
    $payload['client_operation_uuid'] = (string) Str::uuid();
    $payload['sort_order'] = 0;
    $payload['is_deleted'] = true;
    $this->postJson($this->url, $payload)->assertCreated();
    expect($category->fresh()->name)->toBe('Categoría editada')->and($product->fresh()->shop_category_id)->toBe($category->id);
});

test('foreign category and unit identities cannot be used to move product references across shops', function () {
    $category = shopRecordPayload('category_upsert');
    $unit = shopRecordPayload('unit_upsert');
    $this->postJson($this->url, $category)->assertCreated();
    $this->postJson($this->url, $unit)->assertCreated();
    $productId = (string) Str::ulid();
    $url = '/api/v1/shops/'.$this->other->public_id.'/mobile-operations';
    $payload = ['type' => 'product_upsert', 'client_operation_uuid' => (string) Str::uuid(), 'product_id' => $productId,
        'name' => 'Producto QA', 'price' => '10.00', 'category_uuid' => $category['record_id'], 'unit_uuid' => $unit['record_id']];
    $this->postJson($url, $payload)->assertUnprocessable();
    expect($this->other->products()->count())->toBe(0);
    $payload['client_operation_uuid'] = (string) Str::uuid();
    $payload['category_uuid'] = null;
    $this->postJson($url, $payload)->assertUnprocessable();
    expect($this->other->products()->count())->toBe(0);
});

test('a custom product unit and category survive catalog read back', function () {
    $category = shopRecordPayload('category_upsert');
    $unit = shopRecordPayload('unit_upsert');
    $this->postJson($this->url, $category)->assertCreated();
    $this->postJson($this->url, $unit)->assertCreated();
    $this->postJson($this->url, ['type' => 'product_upsert', 'client_operation_uuid' => (string) Str::uuid(), 'product_id' => (string) Str::ulid(),
        'name' => 'Producto QA', 'price' => '10.00', 'category_uuid' => $category['record_id'], 'unit_uuid' => $unit['record_id']])->assertCreated();
    $this->getJson('/api/v1/shops/'.$this->shop->public_id.'/catalog')->assertOk()
        ->assertJsonPath('products.0.unit_uuid', $unit['record_id'])
        ->assertJsonPath('categories.0.client_record_uuid', $category['record_id']);
});

test('a different owner cannot probe or mutate store records', function () {
    Sanctum::actingAs(User::factory()->create(), ['pos:write']);
    $this->postJson($this->url, shopRecordPayload('unit_upsert'))->assertNotFound();
    expect(ShopSyncRecord::count())->toBe(0)->and(MobileOperation::count())->toBe(0);
});
