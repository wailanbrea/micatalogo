<?php

use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use App\Enums\UserPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('E2E-05 decant sale and return reconcile shared ml stock and captured FIFO cost', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Premium]);
    $shop = Shop::factory()->for($owner)->create(['business_type' => 'perfumery']);
    $source = Product::factory()->for($shop)->create([
        'name' => 'Botella E2E-05',
        'price' => '1200.00',
        'sale_unit' => 'bottle',
        'volume_ml' => 100,
    ]);
    ProductInventory::query()->create([
        'product_id' => $source->id,
        'track_inventory' => true,
        'stock_quantity' => 1,
        'sold_quantity' => 0,
        'available_ml' => 100,
        'cost_price' => '300.00',
    ]);
    $decant = Product::factory()->for($shop)->create([
        'name' => 'Decant E2E-05 5ml',
        'price' => '50.00',
        'sale_unit' => 'decant',
        'volume_ml' => 5,
        'inventory_source_product_id' => $source->id,
    ]);
    ProductInventory::query()->create([
        'product_id' => $decant->id,
        'track_inventory' => true,
        'stock_quantity' => 20,
        'sold_quantity' => 0,
    ]);

    $token = $owner->createToken('E2E-05', ['pos:write'])->plainTextToken;
    $base = "/api/v1/shops/{$shop->public_id}";
    $saleUuid = (string) Str::uuid();

    $this->withToken($token)
        ->postJson("{$base}/pos-sales", [
            'client_sale_uuid' => $saleUuid,
            'payment_status' => 'paid',
            'items' => [[
                'product_id' => $decant->public_id,
                'quantity' => 2,
                'unit_price' => '50.00',
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('total', '100.00');

    $invoice = Invoice::query()->with('items')->sole();
    $invoiceItem = $invoice->items->sole();
    expect($source->fresh()->inventory->available_ml)->toBe(90)
        ->and($decant->fresh()->inventory->stock_quantity)->toBe(18)
        ->and($invoiceItem->inventory_source_product_id)->toBe($source->id)
        ->and($invoiceItem->total_cost_cents)->not->toBeNull();

    $returnPayload = [
        'client_operation_uuid' => (string) Str::uuid(),
        'type' => 'return',
        'client_sale_uuid' => $saleUuid,
        'items' => [[
            'product_id' => $decant->public_id,
            'quantity' => 1,
            'refund_price' => '50.00',
            'restock' => true,
        ]],
    ];
    $returnUrl = "{$base}/mobile-operations";
    $this->withToken($token)->postJson($returnUrl, $returnPayload)
        ->assertCreated()
        ->assertJsonPath('total', '50.00');

    $returnItem = DB::table('invoice_return_items')->sole();
    expect($source->fresh()->inventory->available_ml)->toBe(95)
        ->and($decant->fresh()->inventory->stock_quantity)->toBe(19)
        ->and((int) $returnItem->total_cost_cents)->toBe(intdiv((int) $invoiceItem->total_cost_cents, 2))
        ->and(DB::table('invoice_returns')->count())->toBe(1);

    // Exact replay is safe and does not restore ml or stock twice.
    $this->withToken($token)->postJson($returnUrl, $returnPayload)->assertCreated();
    expect(DB::table('invoice_returns')->count())->toBe(1)
        ->and($source->fresh()->inventory->available_ml)->toBe(95)
        ->and($decant->fresh()->inventory->stock_quantity)->toBe(19);
});
