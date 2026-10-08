<?php

use App\Enums\UserPlan;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('NFR-006 catalog snapshot stays bounded at 1500 products', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($owner)->create();
    $products = Product::factory()->count(1500)->for($shop)->create();
    $timestamp = now();

    ProductInventory::insert($products->map(fn (Product $product): array => [
        'product_id' => $product->id,
        'track_inventory' => true,
        'cost_price' => '100.00',
        'stock_quantity' => 10,
        'sold_quantity' => 0,
        'low_stock_threshold' => 3,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ])->all());

    $queryCount = 0;
    DB::listen(static function () use (&$queryCount): void {
        $queryCount++;
    });

    $token = $owner->createToken('nfr-benchmark', ['catalog:read'])->plainTextToken;
    $durations = [];
    $queryCounts = [];
    for ($run = 0; $run < 5; $run++) {
        app('auth')->forgetGuards();
        $beforeQueries = $queryCount;
        $startedAt = hrtime(true);
        $response = $this->withToken($token)->getJson("/api/v1/shops/{$shop->public_id}/catalog");
        $durations[] = (hrtime(true) - $startedAt) / 1_000_000;
        $queryCounts[] = $queryCount - $beforeQueries;
        $response->assertOk();
        expect($response->json('products'))->toHaveCount(1500);
    }

    sort($durations);
    sort($queryCounts);
    fwrite(STDOUT, sprintf(
        "CATALOG_BENCHMARK runs=5 p50_ms=%.2f p95_ms=%.2f max_queries=%d peak_mb=%.2f\n",
        $durations[2],
        $durations[4],
        $queryCounts[4],
        memory_get_peak_usage(true) / 1024 / 1024,
    ));
    expect($queryCounts[4])->toBeLessThanOrEqual(20);
});
