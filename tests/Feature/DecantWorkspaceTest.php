<?php

use App\Enums\UserPlan;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use App\Services\DecantWorkspaceService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function physicalDecantFixture(): array
{
    $owner = User::factory()->create(['plan' => UserPlan::Premium]);
    $shop = Shop::factory()->for($owner)->create(['business_type' => 'perfumery']);
    $source = Product::factory()->for($shop)->create(['sale_unit' => 'bottle', 'volume_ml' => 100, 'price' => '1800.00', 'moderation_status' => 'active']);
    ProductInventory::create(['product_id' => $source->id, 'track_inventory' => true, 'stock_quantity' => 2,
        'available_ml' => 200, 'cost_price' => '1000.00', 'sold_quantity' => 0]);
    $product = Product::factory()->for($shop)->create(['sale_unit' => 'decant', 'volume_ml' => 5,
        'inventory_source_product_id' => $source->id, 'price' => '300.00', 'moderation_status' => 'active']);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 40, 'sold_quantity' => 0]);
    app(InventoryService::class)->openBottle($source, 1, null, $owner->id, true);

    return [$owner, $shop, $source, $product, DB::table('decant_openings')->first()];
}

function decantCommand($test, User $owner, Shop $shop, array $payload)
{
    return $test->actingAs($owner)->postJson('/api/v1/shops/'.$shop->public_id.'/mobile-operations',
        $payload + ['client_operation_uuid' => (string) Str::uuid()]);
}

test('physical preparation transfers ml and FIFO vial cost without creating a sale or duplicating inventory', function () {
    [$owner, $shop, $source, $product, $opening] = physicalDecantFixture();
    decantCommand($this, $owner, $shop, ['type' => 'decant_vial_receive', 'volume_ml' => 5, 'quantity' => 1, 'unit_cost' => '50.00'])->assertCreated();
    decantCommand($this, $owner, $shop, ['type' => 'decant_vial_receive', 'volume_ml' => 5, 'quantity' => 2, 'unit_cost' => '60.00'])->assertCreated();
    $payload = ['type' => 'decant_prepare', 'opening_id' => $opening->public_id, 'product_id' => $product->public_id,
        'expected_ml' => 100, 'quantity' => 2, 'price' => '300.00', 'client_operation_uuid' => (string) Str::uuid()];
    decantCommand($this, $owner, $shop, $payload)->assertCreated();
    decantCommand($this, $owner, $shop, $payload)->assertCreated();
    $batch = DB::table('decant_batches')->sole();
    expect($batch->quantity)->toBe(2)->and($batch->cost_cents)->toBe(21000)
        ->and(DB::table('decant_vial_lots')->sum('remaining_quantity'))->toBe(1)
        ->and(DB::table('decant_openings')->value('remaining_ml'))->toBe(90)
        ->and($source->fresh()->inventory->available_ml)->toBe(190)
        ->and(DB::table('invoices')->count())->toBe(0);
    $snapshot = app(DecantWorkspaceService::class)->snapshot($shop, true);
    expect($snapshot['prepared'])->toBe(2)->and($snapshot['prepared_value_cents'])->toBe(60000);
    $sale = app(InventoryService::class)->recordSale($product, 1, null, $owner->id, false);
    expect((int) $sale->total_cost_cents)->toBe(10500)
        ->and($source->fresh()->inventory->available_ml)->toBe(190)
        ->and(DB::table('decant_openings')->value('remaining_ml'))->toBe(90)
        ->and(DB::table('decant_batches')->value('remaining_quantity'))->toBe(1);
    app(InventoryService::class)->recordReturn($product, 1, 10500, $owner->id);
    expect(DB::table('decant_batches')->sum('remaining_quantity'))->toBe(2)
        ->and($source->fresh()->inventory->available_ml)->toBe(190);
});

test('insufficient envases or stale ml rolls back the complete preparation and idempotency record', function () {
    [$owner, $shop, $source, $product, $opening] = physicalDecantFixture();
    decantCommand($this, $owner, $shop, ['type' => 'decant_prepare', 'opening_id' => $opening->public_id,
        'product_id' => $product->public_id, 'expected_ml' => 100, 'quantity' => 1, 'price' => '300.00'])->assertStatus(409);
    expect(DB::table('decant_batches')->count())->toBe(0)->and(DB::table('mobile_operations')->count())->toBe(0);
    decantCommand($this, $owner, $shop, ['type' => 'decant_vial_receive', 'volume_ml' => 5, 'quantity' => 3, 'unit_cost' => '10.00'])->assertCreated();
    decantCommand($this, $owner, $shop, ['type' => 'decant_prepare', 'opening_id' => $opening->public_id,
        'product_id' => $product->public_id, 'expected_ml' => 99, 'quantity' => 1, 'price' => '300.00'])->assertStatus(409);
    expect(DB::table('decant_vial_lots')->sum('remaining_quantity'))->toBe(3)
        ->and(DB::table('decant_openings')->value('remaining_ml'))->toBe(100);
});

test('on demand consumes perfume and envase once and never claims capacity as prepared stock', function () {
    [$owner, $shop, $source, $product] = physicalDecantFixture();
    decantCommand($this, $owner, $shop, ['type' => 'decant_vial_receive', 'volume_ml' => 5, 'quantity' => 20, 'unit_cost' => '50.00'])->assertCreated();
    $state = app(DecantWorkspaceService::class)->snapshot($shop, true);
    expect($state['prepared'])->toBe(0)->and($state['groups'][0]['sizes'][0]['capacity'])->toBe(20);
    $movement = app(InventoryService::class)->recordSale($product, 1, null, $owner->id, false);
    expect($source->fresh()->inventory->available_ml)->toBe(195)
        ->and(DB::table('decant_openings')->value('remaining_ml'))->toBe(95)
        ->and(DB::table('decant_vial_lots')->sum('remaining_quantity'))->toBe(19)
        ->and(DB::table('decant_batches')->sum('remaining_quantity'))->toBe(0)
        ->and((int) $movement->total_cost_cents)->toBe(10000);
});

test('unused opening reverses into original FIFO lots and a used opening cannot be reversed', function () {
    [$owner, $shop, $source, $product, $opening] = physicalDecantFixture();
    decantCommand($this, $owner, $shop, ['type' => 'decant_undo_open', 'opening_id' => $opening->public_id, 'expected_ml' => 100])->assertCreated();
    expect($source->fresh()->inventory->stock_quantity)->toBe(2)
        ->and($source->fresh()->inventory->available_ml)->toBe(200)
        ->and(DB::table('inventory_lots')->sum('remaining_quantity'))->toBe(200)
        ->and(DB::table('inventory_lots')->sum('remaining_cost_cents'))->toBe(200000);
    app(InventoryService::class)->openBottle($source, 1, null, $owner->id, true);
    $opening = DB::table('decant_openings')->where('status', 'open')->first();
    decantCommand($this, $owner, $shop, ['type' => 'decant_discard', 'opening_id' => $opening->public_id,
        'expected_ml' => 100, 'quantity' => 2, 'notes' => 'Derrame'])->assertCreated();
    decantCommand($this, $owner, $shop, ['type' => 'decant_undo_open', 'opening_id' => $opening->public_id, 'expected_ml' => 98])->assertStatus(409);
    expect($source->fresh()->inventory->available_ml)->toBe(198)
        ->and(DB::table('inventory_movements')->where('type', 'decant_waste')->value('total_cost_cents'))->toBe(2000);
});

test('decant command cannot consume another tenants opening', function () {
    [$owner, $shop, $source, $product, $opening] = physicalDecantFixture();
    [$other, $otherShop] = physicalDecantFixture();
    decantCommand($this, $other, $otherShop, ['type' => 'decant_discard', 'opening_id' => $opening->public_id,
        'expected_ml' => 100, 'quantity' => 1, 'notes' => 'Intento externo'])->assertNotFound();
    expect(DB::table('decant_openings')->value('remaining_ml'))->toBe(100);
});

test('selling the remainder captures its exact cost and payment once and a return restores the same opening', function () {
    [$owner, $shop, $source, $product, $opening] = physicalDecantFixture();
    $uuid = (string) Str::uuid();
    $payload = ['type' => 'decant_sell_remainder', 'opening_id' => $opening->public_id, 'expected_ml' => 100,
        'price' => '1200.00', 'payment_method' => 'cash', 'client_operation_uuid' => $uuid];
    decantCommand($this, $owner, $shop, $payload)->assertCreated();
    decantCommand($this, $owner, $shop, $payload)->assertCreated();
    expect(DB::table('invoices')->count())->toBe(1)
        ->and(DB::table('invoice_payments')->sum('amount_cents'))->toBe(120000)
        ->and(DB::table('invoice_items')->value('total_cost_cents'))->toBe(100000)
        ->and($source->fresh()->inventory->available_ml)->toBe(100)
        ->and($source->fresh()->sale_unit)->toBe('bottle');
    decantCommand($this, $owner, $shop, ['type' => 'return', 'client_sale_uuid' => $uuid,
        'items' => [['product_id' => $source->public_id, 'quantity' => 1, 'refund_price' => '1200.00', 'restock' => true]]])->assertCreated();
    expect($source->fresh()->inventory->available_ml)->toBe(200)
        ->and(DB::table('decant_openings')->value('remaining_ml'))->toBe(100)
        ->and($source->fresh()->inventory->stock_quantity)->toBe(1);
});

test('legacy pool reconciliation transfers existing FIFO cost and never fabricates ready vials', function () {
    [$owner, $shop, $source, $product] = physicalDecantFixture();
    // A separate, genuinely legacy source; new tracked records are never erased.
    $legacy = Product::factory()->for($shop)->create(['sale_unit' => 'bottle', 'volume_ml' => 100]);
    ProductInventory::create(['product_id' => $legacy->id, 'track_inventory' => true, 'stock_quantity' => 1,
        'available_ml' => 140, 'opened_bottles' => 1, 'cost_price' => '1000.00', 'sold_quantity' => 0]);
    decantCommand($this, $owner, $shop, ['type' => 'decant_reconcile_opening', 'product_id' => $legacy->public_id,
        'quantity' => 40, 'expected_ml' => 40])->assertCreated();
    $opening = DB::table('decant_openings')->where('product_id', $legacy->id)->first();
    expect($opening->remaining_ml)->toBe(40)->and($opening->initial_cost_cents)->toBeNull()
        ->and($opening->remaining_cost_cents)->toBe(40000)
        ->and($legacy->fresh()->inventory->available_ml)->toBe(140)
        ->and(DB::table('decant_batches')->count())->toBe(0);
    decantCommand($this, $owner, $shop, ['type' => 'decant_undo_open', 'opening_id' => $opening->public_id, 'expected_ml' => 40])->assertStatus(409);
});

test('workspace read model is tenant scoped and preserves full inventory valuation across preparation', function () {
    [$owner, $shop, $source, $product, $opening] = physicalDecantFixture();
    decantCommand($this, $owner, $shop, ['type' => 'decant_vial_receive', 'volume_ml' => 5, 'quantity' => 2, 'unit_cost' => '50.00'])->assertCreated();
    $this->actingAs($owner)->getJson('/api/v1/shops/'.$shop->public_id.'/decants')->assertOk()->assertJsonPath('prepared', 0)->assertJsonPath('empty_vials', 2);
    decantCommand($this, $owner, $shop, ['type' => 'decant_prepare', 'opening_id' => $opening->public_id,
        'product_id' => $product->public_id, 'quantity' => 2, 'expected_ml' => 100, 'price' => '300.00'])->assertCreated();
    $source = $source->fresh('inventory'); $product = $product->fresh('inventory');
    expect($source->inventory->inventory_value)->toBe(1900.0)->and($product->inventory->inventory_value)->toBe(200.0);
    $this->actingAs($owner)->getJson('/api/v1/shops/'.$shop->public_id.'/inventory-value')->assertOk()->assertJsonPath('inventory_value_cents', 210000);
});

test('opening and preparing a sealed bottle is atomic even when envases are insufficient', function () {
    [$owner, $shop, $source, $product] = physicalDecantFixture();
    $payload = ['type' => 'decant_prepare', 'product_id' => $product->public_id, 'source_product_id' => $source->public_id,
        'expected_stock' => 1, 'quantity' => 2, 'price' => '300.00'];
    decantCommand($this, $owner, $shop, $payload)->assertStatus(409);
    expect(DB::table('decant_openings')->count())->toBe(1)->and($source->fresh()->inventory->stock_quantity)->toBe(1);
    decantCommand($this, $owner, $shop, ['type' => 'decant_vial_receive', 'volume_ml' => 5, 'quantity' => 2, 'unit_cost' => '50.00'])->assertCreated();
    decantCommand($this, $owner, $shop, $payload)->assertCreated();
    expect(DB::table('decant_openings')->count())->toBe(2)->and($source->fresh()->inventory->stock_quantity)->toBe(0)
        ->and($source->fresh()->inventory->available_ml)->toBe(190)->and(DB::table('decant_batches')->value('quantity'))->toBe(2);
});

test('on demand opens a sealed source only when the existing openings cannot fill the requested size', function () {
    [$owner, $shop, $source, $product, $opening] = physicalDecantFixture();
    decantCommand($this, $owner, $shop, ['type' => 'decant_discard', 'opening_id' => $opening->public_id,
        'expected_ml' => 100, 'quantity' => 100, 'notes' => 'Merma aislada'])->assertCreated();
    decantCommand($this, $owner, $shop, ['type' => 'decant_vial_receive', 'volume_ml' => 5, 'quantity' => 1, 'unit_cost' => '50.00'])->assertCreated();
    $movement = app(InventoryService::class)->recordSale($product, 1, null, $owner->id, false);
    expect($source->fresh()->inventory->stock_quantity)->toBe(0)->and($source->fresh()->inventory->available_ml)->toBe(95)
        ->and(DB::table('decant_openings')->count())->toBe(2)->and((int) $movement->total_cost_cents)->toBe(10000);
});

test('visibility does not erase prepared stock and the on demand metric excludes ready sizes', function () {
    [$owner, $shop, $source, $product, $opening] = physicalDecantFixture();
    decantCommand($this, $owner, $shop, ['type' => 'decant_vial_receive', 'volume_ml' => 5, 'quantity' => 2, 'unit_cost' => '50.00'])->assertCreated();
    expect(app(DecantWorkspaceService::class)->snapshot($shop, true)['on_demand'])->toBe(1);
    decantCommand($this, $owner, $shop, ['type' => 'decant_prepare', 'opening_id' => $opening->public_id,
        'expected_ml' => 100, 'product_id' => $product->public_id, 'quantity' => 1, 'price' => '300.00'])->assertCreated();
    expect(app(DecantWorkspaceService::class)->snapshot($shop, true)['on_demand'])->toBe(0);
    decantCommand($this, $owner, $shop, ['type' => 'decant_presentation_update', 'product_id' => $product->public_id, 'offered' => false])->assertCreated();
    $state = app(DecantWorkspaceService::class)->snapshot($shop, true);
    expect($state['prepared'])->toBe(1)->and($state['groups'][0]['sizes'][0]['offered'])->toBeFalse()
        ->and(DB::table('decant_batches')->sum('remaining_quantity'))->toBe(1);
    decantCommand($this, $owner, $shop, ['type' => 'decant_presentation_update', 'product_id' => $product->public_id,
        'price' => '320.00', 'expected_price' => '300.00'])->assertCreated();
    decantCommand($this, $owner, $shop, ['type' => 'decant_presentation_update', 'product_id' => $product->public_id,
        'price' => '340.00', 'expected_price' => '300.00'])->assertStatus(409);
    expect(DB::table('decant_presentation_settings')->value('on_demand'))->toBe(1)
        ->and($product->fresh()->currentPriceDecimal())->toBe('320.00');
});
