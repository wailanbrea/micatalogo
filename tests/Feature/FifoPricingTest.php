<?php

use App\Enums\UserPlan;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ProductPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function fifoFixture(): array
{
    $user = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create(['shop_id' => $shop->id, 'price' => 2500, 'sale_unit' => 'unit']);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true,
        'cost_price' => 1500, 'stock_quantity' => 2, 'sold_quantity' => 0, 'low_stock_threshold' => 1]);

    return [$user, $shop, $product, app(InventoryService::class)];
}

test('FIFO preserves old receipt costs and sale cost across different lots', function () {
    [$user, $shop, $product, $service] = fifoFixture();
    $service->recordRestock($product, 2, null, $user->id, 1800);
    $sale = $service->recordSale($product, 3, null, $user->id);
    expect((int) $sale->total_cost_cents)->toBe(480000)
        ->and(InventoryLot::where('product_id', $product->id)->sum('remaining_quantity'))->toBe(1)
        ->and((int) $sale->invoice->items->first()->total_cost_cents)->toBe(480000);
    $product->inventory->update(['cost_price' => 2200]);
    expect((int) $sale->fresh()->total_cost_cents)->toBe(480000);
});

test('FIFO unknown costs remain unknown and failed sales do not consume lots', function () {
    [$user, $shop, $product, $service] = fifoFixture();
    $product->inventory->update(['cost_price' => null]);
    expect($service->recordSale($product, 1)->total_cost_cents)->toBeNull();
    try {
        $service->recordSale($product, 2);
        $this->fail('Overselling must fail');
    } catch (InvalidArgumentException) {
        expect((int) InventoryLot::sum('remaining_quantity'))->toBe(1);
    }
});

test('margin is calculated over effective selling price', function () {
    [$user, $shop, $product] = fifoFixture();
    expect($product->inventory->margin_percentage)->toBe(40.0);
});

test('Pro rule rounds increases and never applies decreases without approval', function () {
    [$user, $shop, $product, $service] = fifoFixture();
    DB::table('product_price_rules')->insert(['product_id' => $product->id,
        'margin_percent' => 40, 'round_step_cents' => 10000, 'auto_increase' => true,
        'created_at' => now(), 'updated_at' => now()]);
    $service->recordRestock($product, 1, null, $user->id, 1810);
    expect((float) $product->fresh()->price)->toBe(3100.0);
    $service->recordRestock($product, 1, null, $user->id, 1200);
    expect((float) $product->fresh()->price)->toBe(3100.0)
        ->and((float) DB::table('product_price_rules')->value('pending_price'))->toBe(2000.0);
});

test('ml FIFO conserves cents and decants share source lots without double valuation', function () {
    [$user, $shop, $product, $service] = fifoFixture();
    $product->update(['sale_unit' => 'bottle', 'volume_ml' => 100]);
    $product->inventory->update(['stock_quantity' => 1, 'available_ml' => 100, 'cost_price' => 10.01]);
    $decant = Product::factory()->create(['shop_id' => $shop->id, 'price' => 10, 'sale_unit' => 'decant', 'volume_ml' => 3, 'inventory_source_product_id' => $product->id]);
    ProductInventory::create(['product_id' => $decant->id, 'track_inventory' => true, 'stock_quantity' => 33]);
    $first = $service->recordSale($decant, 1);
    $second = $service->recordSale($decant, 32);
    expect((int) $first->total_cost_cents)->toBe(30)
        ->and((int) $second->total_cost_cents)->toBe(960)
        ->and($product->fresh()->inventory->inventory_value)->toBe(0.11)
        ->and($decant->fresh()->inventory->inventory_value)->toBe(0.0)
        ->and((int) InventoryLot::sum('remaining_cost_cents'))->toBe(11);
});

test('free accounts do not apply automatic Pro prices', function () {
    [$user, $shop, $product, $service] = fifoFixture();
    $user->update(['plan' => UserPlan::Free]);
    DB::table('product_price_rules')->insert(['product_id' => $product->id,
        'margin_percent' => 40, 'round_step_cents' => 100, 'auto_increase' => true,
        'created_at' => now(), 'updated_at' => now()]);
    $service->recordRestock($product, 1, null, $user->id, 2000);
    expect((float) $product->fresh()->price)->toBe(2500.0);
});
