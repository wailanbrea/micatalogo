<?php

use App\Enums\UserPlan;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('creation and restoration cannot exceed the plan quota', function (UserPlan $plan, int $limit) {
    $seller = User::factory()->create(['plan' => $plan]);
    $shop = Shop::factory()->for($seller)->create();
    $products = Product::factory()->for($shop)->count($limit)->sequence(fn ($sequence) => [
        'slug' => 'existing-'.$sequence->index,
    ])->create();
    $before = $shop->products()->orderBy('id')->get()->map->getAttributes()->all();
    $trashed = Product::factory()->for($shop)->create(['slug' => 'trashed', 'deleted_at' => now()]);

    $this->actingAs($seller)->postJson(route('seller.shops.products.store', $shop), [
        'name' => 'Over quota', 'price' => 100,
        'availability_status' => 'available', 'moderation_status' => 'active',
    ])->assertStatus(422);

    $this->actingAs($seller)->postJson(route('seller.shops.products.restore', [$shop, $trashed->id]))
        ->assertStatus(422)->assertJsonValidationErrors('products');

    expect($shop->products()->count())->toBe($limit)
        ->and($trashed->fresh()->trashed())->toBeTrue()
        ->and($shop->products()->orderBy('id')->get()->map->getAttributes()->all())->toBe($before);

    $products->first()->delete();
    $this->actingAs($seller)->post(route('seller.shops.products.restore', [$shop, $trashed->id]))->assertRedirect();
    expect($shop->products()->count())->toBe($limit)->and($trashed->fresh()->trashed())->toBeFalse();
})->with([
    'free product 101' => [UserPlan::Free, 100],
    'premium product 501' => [UserPlan::Premium, 500],
]);

test('API exposes current effective quota and excludes trashed products', function () {
    $seller = User::factory()->create(['plan' => UserPlan::Premium]);
    $shop = Shop::factory()->for($seller)->create();
    Product::factory()->for($shop)->create();
    Product::factory()->for($shop)->create(['deleted_at' => now()]);
    $token = $seller->createToken('quota-test', ['catalog:read'])->plainTextToken;

    foreach (['/api/v1/shops', "/api/v1/shops/{$shop->public_id}/catalog"] as $url) {
        $path = $url === '/api/v1/shops' ? '0.quota' : 'shop.quota';
        $this->withToken($token)->getJson($url)->assertOk()
            ->assertJsonPath("{$path}.plan", 'premium')
            ->assertJsonPath("{$path}.product_count", 1)
            ->assertJsonPath("{$path}.product_limit", 500)
            ->assertJsonPath("{$path}.products_remaining", 499);
    }

    $seller->update(['plan_expires_at' => now()->subDay()]);
    $this->withToken($token)->getJson('/api/v1/shops')->assertOk()
        ->assertJsonPath('0.quota.plan', 'free')
        ->assertJsonPath('0.quota.product_limit', 100);
});

test('CSV import rejects the whole batch when it would exceed quota', function () {
    config()->set('catalog.free.max_products_per_shop', 2);
    $seller = User::factory()->create();
    $shop = Shop::factory()->for($seller)->create();
    Product::factory()->for($shop)->create();
    $rows = [
        ['valid' => true, 'name' => 'First', 'price' => 100, 'stock' => 1, 'cost_price' => 50],
        ['valid' => true, 'name' => 'Second', 'price' => 200, 'stock' => 1, 'cost_price' => 100],
    ];
    $this->actingAs($seller)->postJson(route('seller.shops.products.import.store', $shop), [
        'rows' => json_encode($rows),
    ])->assertStatus(422)->assertJsonValidationErrors('rows');
    expect($shop->products()->count())->toBe(1);
    $this->assertDatabaseMissing('products', ['shop_id' => $shop->id, 'name' => 'First']);
    $this->assertDatabaseMissing('products', ['shop_id' => $shop->id, 'name' => 'Second']);
});

test('an administrator sees the import option and current product quota', function () {
    $admin = User::factory()->admin()->create();
    $shop = Shop::factory()->for($admin)->create();
    Product::factory()->for($shop)->count(2)->create();

    $this->actingAs($admin)
        ->get(route('seller.shops.products.index', $shop))
        ->assertOk()
        ->assertSee('Importar CSV/XLSX');

    $this->actingAs($admin)
        ->get(route('seller.shops.products.import.create', $shop))
        ->assertOk()
        ->assertSee('Esta tienda tiene')
        ->assertSee('2')
        ->assertSee('100')
        ->assertSee('98');

    $this->actingAs($admin)
        ->get(route('seller.shops.inventory.index', $shop))
        ->assertOk()
        ->assertSee('Cargar inventario');
});
