<?php

use App\Enums\UserPlan;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\ShopSyncRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function routeCommand(string $type, string $id, array $fields = []): array
{
    return ['type' => $type, 'record_id' => $id, 'client_operation_uuid' => (string) Str::uuid()] + $fields;
}

beforeEach(function () {
    $this->owner = User::factory()->create(['plan' => UserPlan::Premium]);
    $this->shop = Shop::factory()->create(['user_id' => $this->owner->id]);
    $this->other = Shop::factory()->create(['user_id' => $this->owner->id]);
    $this->route = (string) Str::uuid();
    $this->url = '/api/v1/shops/'.$this->shop->public_id.'/mobile-operations';
    Sanctum::actingAs($this->owner, ['pos:write']);
    $this->postJson($this->url, routeCommand('route_upsert', $this->route, ['name' => 'Ruta QA', 'code' => 'QA-N',
        'is_active' => true, 'created_at' => '2026-10-12T10:00:00Z']))->assertCreated();
});

test('routes persist edits and idempotency without accepting duplicate codes or external references', function () {
    $payload = routeCommand('route_upsert', $this->route, ['name' => 'Ruta actualizada', 'code' => 'QA-N',
        'is_active' => false, 'created_at' => '2026-10-13T10:00:00Z']);
    $this->postJson($this->url, $payload)->assertCreated();
    $this->postJson($this->url, $payload)->assertCreated();
    expect(ShopSyncRecord::where('kind', 'route')->count())->toBe(1);
    $this->postJson($this->url, routeCommand('route_upsert', (string) Str::uuid(), ['name' => 'Otra ruta', 'code' => 'qa-n',
        'is_active' => true, 'created_at' => '2026-10-12T10:00:00Z']))->assertUnprocessable();
    $this->getJson('/api/v1/shops/'.$this->shop->public_id.'/catalog')->assertOk()->assertJsonPath('store_records.0.payload.name', 'Ruta actualizada');
    $this->postJson('/api/v1/shops/'.$this->other->public_id.'/mobile-operations',
        routeCommand('route_reorder', $this->route, ['customer_ids' => []]))->assertNotFound();
});

test('customer assignments reorder move and unassign within one tenant and reject inactive customers', function () {
    $first = Customer::create(['shop_id' => $this->shop->id, 'name' => 'QA A', 'credit_limit' => 0]);
    $second = Customer::create(['shop_id' => $this->shop->id, 'name' => 'QA B', 'credit_limit' => 0]);
    $foreign = Customer::create(['shop_id' => $this->other->id, 'name' => 'No debe asignarse', 'credit_limit' => 0]);
    foreach ([$first, $second] as $customer) {
        $request = routeCommand('route_assign', $this->route, ['customer_id' => $customer->public_id]);
        $this->postJson($this->url, $request)->assertCreated();
        $this->postJson($this->url, $request)->assertCreated();
    }
    $this->postJson($this->url, routeCommand('route_reorder', $this->route, ['customer_ids' => [$second->public_id, $first->public_id]]))->assertCreated();
    $row = ShopSyncRecord::where('kind', 'route_members')->first();
    expect(array_column($row->payload['customers'], 'customer_id'))->toBe([$second->public_id, $first->public_id]);
    $this->postJson($this->url, routeCommand('route_assign', $this->route, ['customer_id' => $foreign->public_id]))->assertNotFound();
    $first->update(['is_active' => false]);
    $this->postJson($this->url, routeCommand('route_assign', $this->route, ['customer_id' => $first->public_id]))->assertUnprocessable();
    $this->postJson($this->url, routeCommand('route_unassign', $this->route, ['customer_id' => $first->public_id]))->assertCreated();
    expect(array_column($row->fresh()->payload['customers'], 'customer_id'))->toBe([$second->public_id]);
    $this->postJson($this->url, routeCommand('route_reorder', $this->route, ['customer_ids' => [$first->public_id]]))->assertConflict();
});

test('a route load allocates existing stock once and preserves its historical cost through sales and closure', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'sale_unit' => 'unit', 'price' => 300]);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 10, 'cost_price' => 100]);
    $load = (string) Str::uuid();
    $payload = routeCommand('route_load_create', $load, ['route_id' => $this->route, 'date' => '2026-10-12T10:00:00Z',
        'created_at' => '2026-10-12T10:00:00Z', 'items' => [['id' => (string) Str::uuid(), 'product_id' => $product->public_id,
            'quantity' => 4, 'unit_cost_cents' => 10000, 'expected_main_quantity' => 10]]]);
    $this->postJson($this->url, $payload)->assertCreated();
    $this->postJson($this->url, $payload)->assertCreated();
    expect($product->inventory->fresh()->stock_quantity)->toBe(10)->and(ShopSyncRecord::where('kind', 'route_load')->count())->toBe(1);
    $this->getJson('/api/v1/shops/'.$this->shop->public_id.'/catalog')->assertOk()->assertJsonPath('products.0.inventory.stock_quantity', 6)
        ->assertJsonPath('route_stock.0.quantity', 4);
    $sale = ['client_sale_uuid' => (string) Str::uuid(), 'payment_status' => 'paid', 'route_uuid' => $this->route,
        'items' => [['product_id' => $product->public_id, 'quantity' => 2, 'unit_price' => '300.00']]];
    $this->postJson('/api/v1/shops/'.$this->shop->public_id.'/pos-sales', $sale)->assertCreated();
    $this->postJson('/api/v1/shops/'.$this->shop->public_id.'/pos-sales', $sale)->assertCreated();
    $this->getJson('/api/v1/shops/'.$this->shop->public_id.'/catalog')->assertOk()->assertJsonPath('products.0.inventory.stock_quantity', 6)
        ->assertJsonPath('route_stock.0.quantity', 2);
    $this->postJson($this->url, routeCommand('route_load_status', $load, ['status' => 'SETTLED']))->assertCreated();
    $this->getJson('/api/v1/shops/'.$this->shop->public_id.'/catalog')->assertOk()->assertJsonPath('products.0.inventory.stock_quantity', 8)
        ->assertJsonPath('route_stock.0.quantity', 0);
    expect(ShopSyncRecord::where('kind', 'route_load')->first()->payload['items'][0]['unit_cost_cents'])->toBe(10000);
});

test('stale and oversized loads leave inventory untouched and warehouse sales cannot consume allocated route stock', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'sale_unit' => 'unit', 'price' => 300]);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 10, 'cost_price' => 100]);
    $fields = ['route_id' => $this->route, 'date' => '2026-10-12T10:00:00Z', 'created_at' => '2026-10-12T10:00:00Z',
        'items' => [['id' => (string) Str::uuid(), 'product_id' => $product->public_id, 'quantity' => 11,
            'unit_cost_cents' => 10000, 'expected_main_quantity' => 10]]];
    $this->postJson($this->url, routeCommand('route_load_create', (string) Str::uuid(), $fields))->assertConflict();
    expect(ShopSyncRecord::where('kind', 'route_load')->count())->toBe(0);
    $fields['items'][0]['quantity'] = 8;
    $this->postJson($this->url, routeCommand('route_load_create', (string) Str::uuid(), $fields))->assertCreated();
    $sale = ['client_sale_uuid' => (string) Str::uuid(), 'payment_status' => 'paid',
        'items' => [['product_id' => $product->public_id, 'quantity' => 3, 'unit_price' => '300.00']]];
    $this->postJson('/api/v1/shops/'.$this->shop->public_id.'/pos-sales', $sale)->assertConflict();
    expect($product->inventory->fresh()->stock_quantity)->toBe(10)->and($this->shop->invoices()->count())->toBe(0);
    $sale['route_uuid'] = (string) Str::uuid();
    $this->postJson('/api/v1/shops/'.$this->shop->public_id.'/pos-sales', $sale)->assertNotFound();
    expect($product->inventory->fresh()->stock_quantity)->toBe(10);
});
