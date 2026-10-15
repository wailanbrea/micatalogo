<?php

// Frozen diagnostic for the F0 baseline; run explicitly, outside the regression suite.
use App\Models\{Product, ProductInventory, Shop, ShopCategory, ShopMember, ShopSeller, User};
use App\Services\SellerNavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('closure F0 records the existing service taxonomy denial without changing business data', function () {
    expect(app()->environment('testing'))->toBeTrue();
    expect(DB::connection()->getDriverName())->toBe('sqlite');
    expect(DB::connection()->getDatabaseName())->toBe(':memory:');
    $owner = User::factory()->create(['name' => 'QA_CLOSURE_Owner', 'plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create(['name' => 'QA_CLOSURE_Shop', 'business_type' => 'perfume_store']);
    $category = $shop->categories()->create(['name' => 'QA_CLOSURE_PhysicalOnly', 'slug' => 'qa-closure-physical', 'status' => 'active']);
    $product = Product::factory()->for($shop)->create(['name' => 'QA_CLOSURE_Physical', 'shop_category_id' => $category->id, 'sale_unit' => 'unit', 'price' => '100.00']);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 5, 'cost_price' => '20.00']);
    $roles = ['owner' => $owner];
    foreach (['manager', 'accountant', 'physical', 'services', 'none'] as $role) {
        $actor = User::factory()->create(['name' => 'QA_CLOSURE_'.$role]);
        if (in_array($role, ['manager', 'accountant'], true)) {
            ShopMember::create(['shop_id' => $shop->id, 'user_id' => $actor->id, 'role' => $role, 'is_active' => true]);
        } else {
            ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $actor->id, 'is_active' => true, 'commission_type' => 'percentage',
                'commission_value' => 5, 'menu_permissions' => match ($role) { 'physical' => ['products'], 'services' => ['services'], default => [] }]);
        }
        $roles[$role] = $actor;
    }
    $snap = function (): array {
        $result = [];
        foreach (['shop_categories', 'shop_sync_records', 'products', 'product_inventories', 'inventory_lots', 'invoices', 'mobile_operations'] as $table) {
            $rows = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
            $result[$table] = ['count' => count($rows), 'sha256' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR))];
        }
        return $result;
    };
    $before = $snap();
    $actor = $roles['services'];
    $this->actingAs($actor);
    $responses = [];
    $responses['web_get'] = $this->get(route('seller.shops.categories.index', $shop))->status();
    $responses['web_post'] = $this->postJson(route('seller.shops.categories.store', $shop), ['name' => 'QA_CLOSURE_Service', 'status' => 'active'])->status();
    $responses['web_put'] = $this->putJson(route('seller.shops.categories.update', [$shop, $category]), ['name' => 'QA_CLOSURE_Changed', 'status' => 'active'])->status();
    $responses['web_delete'] = $this->deleteJson(route('seller.shops.categories.destroy', [$shop, $category]))->status();
    Sanctum::actingAs($actor, ['pos:write', 'catalog:read']);
    foreach (['category', 'unit'] as $kind) {
        $payload = ['type' => $kind.'_upsert', 'client_operation_uuid' => (string) Str::uuid(), 'record_id' => (string) Str::uuid(),
            'name' => 'QA_CLOSURE_Service_'.$kind, 'is_active' => true, 'is_deleted' => false, 'created_at' => now()->toISOString(),
            'icon' => 'category_default', 'sort_order' => 0, 'abbreviation' => 'SV'];
        $responses[$kind.'_upsert'] = $this->postJson('/api/v1/shops/'.$shop->public_id.'/mobile-operations', $payload)->status();
    }
    $snapshot = $this->getJson('/api/v1/shops/'.$shop->public_id.'/catalog')->assertOk();
    $responses['catalog_category_names'] = collect($snapshot->json('categories'))->pluck('name')->all();
    $after = $snap();
    expect($after)->toBe($before);
    foreach (['web_get', 'web_post', 'web_put', 'web_delete', 'category_upsert', 'unit_upsert'] as $key) expect($responses[$key])->toBe(403);
    $navigation = [];
    foreach ($roles as $role => $user) {
        $navigation[$role] = app(SellerNavigationService::class)->forUser($shop, $user);
    }
    $destination = __DIR__.'/evidence/closure-f0-reproduction.json';
    if (file_exists($destination)) throw new RuntimeException('Frozen F0 evidence already exists; preserve it.');
    file_put_contents($destination, json_encode(['environment' => 'testing', 'connection' => 'sqlite', 'host' => 'none (in-memory)',
        'database' => ':memory:', 'responses' => $responses, 'before' => $before, 'after' => $after,
        'navigation' => $navigation], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
});
