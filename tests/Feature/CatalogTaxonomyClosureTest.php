<?php

use App\Models\{Product, ProductInventory, Shop, ShopSeller, ShopSyncRecord, User};
use App\Services\ShopRecordSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function closureActor(): array
{
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create(['business_type' => 'perfume_store']);
    $actor = User::factory()->create();
    $assignment = ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $actor->id, 'is_active' => true,
        'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions' => ['services']]);
    $category = $shop->categories()->create(['name' => 'QA_CLOSURE_Physical', 'slug' => 'qa-closure-physical', 'status' => 'active']);
    $physical = Product::factory()->for($shop)->create(['name' => 'QA_CLOSURE_Bottle', 'sale_unit' => 'unit', 'shop_category_id' => $category->id, 'price' => '100.00']);
    ProductInventory::create(['product_id' => $physical->id, 'track_inventory' => true, 'stock_quantity' => 5, 'cost_price' => '20.00']);
    return [$shop, $actor, $assignment, $category, $physical, $owner];
}

function closureRecord(string $kind, array $extra = []): array
{
    return $extra + ['type' => $kind.'_upsert', 'client_operation_uuid' => (string) Str::uuid(), 'record_id' => (string) Str::uuid(),
        'name' => 'QA_CLOSURE_'.$kind, 'is_active' => true, 'is_deleted' => false, 'created_at' => now()->toISOString(),
        ...($kind === 'category' ? ['icon' => 'category_default', 'sort_order' => 0] : ['abbreviation' => 'SV']), 'scope' => 'service'];
}

test('services only opens taxonomy and creates real web categories and units without products authority', function () {
    [$shop, $actor, , , $physical] = closureActor();
    $before = $physical->fresh()->getAttributes();
    $inventory = $physical->inventory->getAttributes();
    $this->actingAs($actor)->get(route('seller.shops.categories.index', $shop))->assertOk()->assertSee('Unidades');
    $this->post(route('seller.shops.categories.store', $shop), ['name' => 'QA_CLOSURE_WebService', 'scope' => 'service', 'status' => 'active',
        'client_operation_uuid' => (string) Str::uuid()])->assertRedirect();
    $category = $shop->categories()->where('name', 'QA_CLOSURE_WebService')->firstOrFail();
    $record = ShopSyncRecord::where('shop_id', $shop->id)->where('kind', 'category')->firstOrFail();
    expect($record->payload['server_id'])->toBe((string) $category->id)->and($record->payload['scope'])->toBe('service');
    $this->post(route('seller.shops.units.store', $shop), ['name' => 'QA_CLOSURE_Session', 'abbreviation' => 'SS', 'scope' => 'service', 'status' => 'active',
        'client_operation_uuid' => (string) Str::uuid()])->assertRedirect();
    $this->get(route('seller.shops.categories.index', [$shop, 'tab' => 'units']))->assertOk()->assertSee('QA_CLOSURE_Session');
    expect($physical->fresh()->getAttributes())->toBe($before)->and($physical->inventory->fresh()->getAttributes())->toBe($inventory);
});

test('service taxonomy envelopes create once revalidate revocation and reject changed UUID payload', function () {
    [$shop, $actor, $assignment, , $physical] = closureActor();
    Sanctum::actingAs($actor, ['pos:write', 'catalog:read']);
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    foreach (['category', 'unit'] as $kind) {
        $payload = closureRecord($kind);
        $first = $this->postJson($url, $payload)->assertCreated()->assertJsonPath('record_id', $payload['record_id']);
        $this->postJson($url, $payload)->assertCreated()->assertExactJson($first->json());
        $this->postJson($url, array_replace($payload, ['name' => 'QA_CLOSURE_Forged']))->assertConflict();
        $assignment->update(['menu_permissions' => []]);
        $this->postJson($url, $payload)->assertForbidden();
        $assignment->update(['menu_permissions' => ['services']]);
    }
    expect($physical->inventory->fresh()->stock_quantity)->toBe(5)->and($shop->categories()->count())->toBe(2);
});

test('services cannot mutate shared physical category or forge a product scope through mobile or web', function () {
    [$shop, $actor, , $category, $physical] = closureActor();
    $this->actingAs($actor)->putJson(route('seller.shops.categories.update', [$shop, $category]), ['name' => 'QA_CLOSURE_No', 'status' => 'active', 'scope' => 'service'])->assertForbidden();
    $this->deleteJson(route('seller.shops.categories.destroy', [$shop, $category]))->assertForbidden();
    Sanctum::actingAs($actor, ['pos:write']);
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $this->postJson($url, closureRecord('category', ['record_id' => app(ShopRecordSyncService::class)->localId($shop, 'category', (string) $category->id)]))->assertForbidden();
    $this->postJson($url, closureRecord('unit', ['scope' => 'product']))->assertForbidden();
    expect($category->fresh()->name)->toBe('QA_CLOSURE_Physical')->and($physical->fresh()->shop_category_id)->toBe($category->id);
});

test('web and mobile keep category UUID and reject stale revisions without changing scope or references', function () {
    [$shop, $actor] = closureActor();
    Sanctum::actingAs($actor, ['pos:write', 'catalog:read']);
    $payload = closureRecord('category');
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $ack = $this->postJson($url, $payload)->assertCreated()->json('record');
    $category = $shop->categories()->whereKey($ack['server_id'])->firstOrFail();
    $this->actingAs($actor)->put(route('seller.shops.categories.update', [$shop, $category]), ['name' => 'QA_CLOSURE_WebEdited', 'status' => 'active',
        'scope' => 'service', 'expected_revision' => $ack['revision'], 'client_operation_uuid' => (string) Str::uuid()])->assertRedirect();
    $stale = array_replace($payload, ['client_operation_uuid' => (string) Str::uuid(), 'name' => 'QA_CLOSURE_Stale', 'expected_revision' => $ack['revision']]);
    $this->postJson($url, $stale)->assertConflict();
    $this->postJson($url, array_replace($stale, ['client_operation_uuid' => (string) Str::uuid(), 'expected_revision' => null]))->assertConflict();
    expect($category->fresh()->name)->toBe('QA_CLOSURE_WebEdited')->and(ShopSyncRecord::where('record_uuid', $payload['record_id'])->count())->toBe(1);
    $snapshot = $this->getJson('/api/v1/shops/'.$shop->public_id.'/catalog')->assertOk();
    $projected = collect($snapshot->json('categories'))->firstWhere('id', (string) $category->id);
    expect($projected['client_record_uuid'])->toBe($payload['record_id'])->and($projected['scope'])->toBe('service');
});

test('service product assignment cannot bypass scope through category uuid name unit uuid or web category id', function () {
    [$shop, $actor, , $category, $physical] = closureActor();
    $categoryUuid = app(ShopRecordSyncService::class)->localId($shop, 'category', (string) $category->id);
    ShopSyncRecord::create(['shop_id' => $shop->id, 'kind' => 'category', 'record_uuid' => $categoryUuid,
        'payload' => ['scope' => 'product', 'server_id' => (string) $category->id]]);
    $unitUuid = (string) Str::uuid();
    ShopSyncRecord::create(['shop_id' => $shop->id, 'kind' => 'unit', 'record_uuid' => $unitUuid,
        'payload' => ['scope' => 'product', 'name' => 'QA_CLOSURE_Box', 'abbreviation' => 'BX', 'is_active' => true, 'is_deleted' => false, 'created_at' => now()->toISOString()]]);
    Sanctum::actingAs($actor, ['pos:write', 'catalog:read']);
    foreach ([['category_uuid' => $categoryUuid], ['category_name' => $category->name], ['unit_uuid' => $unitUuid]] as $forged) {
        $this->postJson('/api/v1/shops/'.$shop->public_id.'/mobile-operations', $forged + ['type' => 'product_upsert',
            'client_operation_uuid' => (string) Str::uuid(), 'product_id' => (string) Str::ulid(), 'name' => 'QA_CLOSURE_ForbiddenService',
            'sale_unit' => 'service', 'price' => '150.00'])->assertForbidden();
    }
    $this->actingAs($actor)->postJson(route('seller.shops.products.store', $shop), ['name' => 'QA_CLOSURE_WebForbidden', 'sale_unit' => 'service',
        'price' => '150.00', 'availability_status' => 'available', 'moderation_status' => 'active', 'shop_category_id' => $category->id])->assertForbidden();
    expect($shop->products()->count())->toBe(1)->and($physical->inventory->fresh()->stock_quantity)->toBe(5);
});

test('service taxonomy deactivation and reactivation preserve references and server identifiers', function () {
    [$shop, $actor] = closureActor();
    Sanctum::actingAs($actor, ['pos:write']);
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $category = $this->postJson($url, closureRecord('category'))->assertCreated()->json('record');
    $unit = $this->postJson($url, closureRecord('unit'))->assertCreated()->json('record');
    $service = Product::factory()->for($shop)->create(['sale_unit' => 'service', 'shop_category_id' => $category['server_id']]);
    app(ShopRecordSyncService::class)->saveProductUnit($shop, $service->public_id, $unit['record_id'], $actor);
    $this->actingAs($actor)->delete(route('seller.shops.units.destroy', [$shop, $unit['record_id']]), ['expected_revision' => $unit['revision']])->assertRedirect();
    $inactive = ShopSyncRecord::where('shop_id', $shop->id)->where('record_uuid', $unit['record_id'])->firstOrFail();
    expect($inactive->payload['is_deleted'])->toBeTrue()->and($service->fresh()->shop_category_id)->toBe((int) $category['server_id']);
    $this->put(route('seller.shops.units.update', [$shop, $unit['record_id']]), ['name' => 'QA_CLOSURE_Restored', 'abbreviation' => 'SV',
        'status' => 'active', 'scope' => 'service', 'expected_revision' => $inactive->payload['revision']])->assertRedirect();
    expect($inactive->fresh()->payload['is_deleted'])->toBeFalse()->and(app(ShopRecordSyncService::class)->productUnits($shop)[$service->public_id])->toBe($unit['record_id']);
    $this->delete(route('seller.shops.categories.destroy', [$shop, $category['server_id']]), ['expected_revision' => $category['revision']])->assertRedirect();
    expect($shop->categories()->whereKey($category['server_id'])->firstOrFail()->status->value)->toBe('inactive')
        ->and($service->fresh()->shop_category_id)->toBe((int) $category['server_id']);
});

test('taxonomy lookups reject another tenant before returning or mutating records', function () {
    [$shop, $actor] = closureActor();
    $other = Shop::factory()->create();
    $foreign = $other->categories()->create(['name' => 'QA_CLOSURE_Foreign', 'slug' => 'qa-closure-foreign', 'status' => 'active']);
    $record = ShopSyncRecord::create(['shop_id' => $other->id, 'kind' => 'unit', 'record_uuid' => (string) Str::uuid(),
        'payload' => ['name' => 'QA_CLOSURE_ForeignUnit', 'scope' => 'service', 'is_active' => true, 'is_deleted' => false]]);
    $this->actingAs($actor)->putJson(route('seller.shops.categories.update', [$shop, $foreign]), ['name' => 'No', 'status' => 'active'])->assertNotFound();
    $this->deleteJson(route('seller.shops.units.destroy', [$shop, $record->record_uuid]))->assertNotFound();
    Sanctum::actingAs($actor, ['pos:write']);
    $this->postJson('/api/v1/shops/'.$shop->public_id.'/mobile-operations', closureRecord('unit', ['record_id' => $record->record_uuid]))->assertNotFound();
    expect($record->fresh()->payload['name'])->toBe('QA_CLOSURE_ForeignUnit');
});

test('scope changes cannot reclassify a referenced category and legacy web category uuid remains stable', function () {
    [$shop, , , $legacy, , $owner] = closureActor();
    $uuid = app(ShopRecordSyncService::class)->localId($shop, 'category', (string) $legacy->id);
    $this->actingAs($owner)->put(route('seller.shops.categories.update', [$shop, $legacy]), ['name' => 'QA_CLOSURE_LegacyEdited', 'status' => 'active',
        'scope' => 'shared', 'expected_revision' => null])->assertRedirect();
    expect(ShopSyncRecord::where('record_uuid', $uuid)->firstOrFail()->payload['server_id'])->toBe((string) $legacy->id);
    $this->putJson(route('seller.shops.categories.update', [$shop, $legacy]), ['name' => 'QA_CLOSURE_Reclassified', 'status' => 'active',
        'scope' => 'service', 'expected_revision' => ShopSyncRecord::where('record_uuid', $uuid)->firstOrFail()->payload['revision']])->assertUnprocessable();
    expect($legacy->fresh()->name)->toBe('QA_CLOSURE_LegacyEdited');
});
