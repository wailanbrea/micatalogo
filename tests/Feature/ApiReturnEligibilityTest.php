<?php

use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('cash invoices from any server flow are eligible for returns and the refund is idempotent', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $product = Product::factory()->for($shop)->create([
        'name' => 'Producto para devolución',
        'price' => '200.00',
        'sale_unit' => 'unit',
    ]);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 2,
        'sold_quantity' => 0,
        'cost_price' => '80.00',
    ]);

    $token = $owner->createToken('returns-qa', ['pos:write'])->plainTextToken;
    $base = "/api/v1/shops/{$shop->public_id}";
    $sale = $this->withToken($token)
        ->postJson("{$base}/pos-sales", [
            'client_sale_uuid' => (string) Str::uuid(),
            'payment_status' => 'paid',
            'items' => [[
                'product_id' => $product->public_id,
                'quantity' => 1,
                'unit_price' => '200.00',
            ]],
        ])
        ->assertCreated()
        ->json();

    $invoice = Invoice::query()->sole();

    $this->withToken($token)
        ->getJson("{$base}/returns/eligible")
        ->assertOk()
        ->assertJsonPath('invoices.0.id', $invoice->id)
        ->assertJsonPath('invoices.0.items.0.remaining_quantity', 1)
        ->assertJsonPath('invoices.0.items.0.product_id', $product->public_id);

    $returnPayload = [
        'client_operation_uuid' => (string) Str::uuid(),
        'type' => 'return',
        'invoice_id' => $invoice->id,
        'items' => [[
            'product_id' => $product->public_id,
            'quantity' => 1,
            'refund_price' => '200.00',
            'restock' => true,
        ]],
    ];

    $firstReturn = $this->withToken($token)
        ->postJson("{$base}/mobile-operations", $returnPayload)
        ->assertCreated()
        ->assertJsonPath('total', '200.00');

    $this->withToken($token)
        ->postJson("{$base}/mobile-operations", $returnPayload)
        ->assertCreated()
        ->assertExactJson($firstReturn->json());

    expect($product->fresh()->inventory->stock_quantity)->toBe(2)
        ->and(DB::table('invoice_returns')->count())->toBe(1)
        ->and(DB::table('invoice_return_items')->count())->toBe(1)
        ->and($sale['status'])->toBe('paid');
});
