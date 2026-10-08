<?php

use App\Enums\UserPlan;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\Money;
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
        ->and((int) InventoryLot::where('product_id', $product->id)->sum('remaining_quantity'))->toBe(1)
        ->and((int) $sale->invoice->items->first()->total_cost_cents)->toBe(480000);
    $product->inventory->update(['cost_price' => 2200]);
    expect((int) $sale->fresh()->total_cost_cents)->toBe(480000);
});

test('different receipts remain visible as separate lots with their own entry cost', function () {
    [$user, $shop, $product, $service] = fifoFixture();
    $service->recordRestock($product, 2, 'Primer lote', $user->id, 1800);
    $service->recordRestock($product, 1, 'Segundo lote', $user->id, 2300);

    $lots = InventoryLot::where('product_id', $product->id)->orderBy('received_at')->orderBy('id')->get();

    expect($lots)->toHaveCount(3)
        ->and($lots[0]->received_product_unit_cost_cents)->toBe(150000)
        ->and($lots[1]->received_product_unit_cost_cents)->toBe(180000)
        ->and($lots[2]->received_product_unit_cost_cents)->toBe(230000)
        ->and($lots[1]->remaining_quantity)->toBe(2)
        ->and($lots[2]->remaining_quantity)->toBe(1);
});

test('decimal strings cross inventory mutation boundaries without float rounding', function () {
    [$user, $shop, $product, $service] = fifoFixture();
    $product->inventory->update(['stock_quantity' => 0, 'cost_price' => null]);

    $service->recordRestock($product, 2, 'Costo con centavos', $user->id, '249.99');
    $lot = InventoryLot::where('product_id', $product->id)->latest('id')->firstOrFail();

    expect((int) $lot->received_cost_cents)->toBe(49998)
        ->and((string) $product->fresh()->inventory->getRawOriginal('cost_price'))->toBe('249.99');

    $sale = $service->recordSale($product->fresh(), 1, null, $user->id);

    expect((int) $sale->total_cost_cents)->toBe(24999)
        ->and((int) $lot->fresh()->remaining_cost_cents)->toBe(24999);
});

test('effective catalog prices cross POS mutations as exact decimal strings', function () {
    [$user, $shop, $product, $service] = fifoFixture();
    $product->update(['price' => '0.29', 'sale_price' => '0.28']);

    expect($product->fresh()->currentPriceDecimal())->toBe('0.28')
        ->and($product->fresh()->currentPriceCents())->toBe(28);

    $movement = $service->recordSale($product->fresh(), 1, null, $user->id);
    $invoiceItem = $movement->fresh()->invoice->items->first();

    expect((string) $movement->unit_price)->toBe('0.28')
        ->and((string) $invoiceItem->unit_price)->toBe('0.28')
        ->and(Money::toCents($invoiceItem->line_total))->toBe(28);
});

test('per-unit movement costs divide cents deterministically without floats', function () {
    expect(Money::perUnitDecimal(1, 3))->toBe('0.00')
        ->and(Money::perUnitDecimal(2, 3))->toBe('0.01')
        ->and(Money::perUnitDecimal(5, 2))->toBe('0.03')
        ->and(Money::perUnitDecimal(null, 3))->toBeNull();
});

test('cart sale keeps discount and tax as exact cents at the service boundary', function () {
    [$user, $shop, $product, $service] = fifoFixture();

    $movements = $service->recordCartSales([
        [
            'product' => $product,
            'quantity' => 1,
            'unit_price' => '100.01',
            'discount' => '0.01',
            'tax' => '0.02',
        ],
    ], $user->id, 'pos', 'paid', '0.03', '0.04');
    $invoice = $movements[0]->fresh()->invoice;

    expect(Money::toCents($invoice->items->first()->line_total))->toBe(10002)
        ->and(Money::toCents($invoice->discount))->toBe(3)
        ->and(Money::toCents($invoice->tax))->toBe(4)
        ->and(Money::toCents($invoice->total))->toBe(10003);
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

test('Pro can recalculate existing pricing rules without silently applying a decrease', function () {
    [$user, $shop, $product] = fifoFixture();
    $product->update(['price' => 1000]);
    DB::table('product_price_rules')->insert([
        'product_id' => $product->id,
        'margin_percent' => 40,
        'round_step_cents' => 100,
        'auto_increase' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/shops/{$shop->public_id}/pricing/recalculate")
        ->assertOk()
        ->assertJsonPath('evaluated', 1);

    expect((float) $product->fresh()->price)->toBe(1000.0)
        ->and((float) DB::table('product_price_rules')->where('product_id', $product->id)->value('pending_price'))->toBe(2500.0);
});

test('Pro puede aplicar una regla global sin cambiar precios de inmediato', function () {
    [$user, $shop, $product] = fifoFixture();
    $second = Product::factory()->create(['shop_id' => $shop->id, 'price' => 800]);

    $response = $this->actingAs($user)->post(route('seller.shops.pricing.bulk-rule', $shop), [
        'scope' => 'all',
        'margin_percent' => '35',
        'round_step' => '5.00',
        'auto_increase' => '1',
    ]);
    $response->assertSessionHasNoErrors();

    expect(DB::table('product_price_rules')->whereIn('product_id', [$product->id, $second->id])->count())->toBe(2)
        ->and((float) $product->fresh()->price)->toBe(2500.0)
        ->and((float) $second->fresh()->price)->toBe(800.0);
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
