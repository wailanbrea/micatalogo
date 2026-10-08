<?php

use App\Models\CashMovement;
use App\Models\Invoice;
use App\Models\InventoryMovement;
use App\Models\PosSaleUpload;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('SALE-008 POS failure after invoice creation rolls back upload invoice inventory and cash atomically', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'price' => '250.00',
        'sale_unit' => 'unit',
    ]);
    ProductInventory::query()->create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 2,
        'sold_quantity' => 0,
        'cost_price' => '100.00',
    ]);

    $this->mock(PaymentService::class, function ($mock): void {
        $mock->shouldReceive('processInvoicePayments')
            ->once()
            ->andThrow(new RuntimeException('QA injected failure after invoice creation'));
    });

    $token = $user->createToken('QA POS', ['pos:write'])->plainTextToken;
    $this->withoutExceptionHandling();

    expect(fn () => $this->withToken($token)->postJson(
        "/api/v1/shops/{$shop->public_id}/pos-sales",
        [
            'client_sale_uuid' => (string) Str::uuid(),
            'payment_status' => 'paid',
            'items' => [[
                'product_id' => $product->public_id,
                'quantity' => 1,
                'unit_price' => '250.00',
            ]],
        ],
    ))->toThrow(RuntimeException::class, 'QA injected failure after invoice creation');

    expect(Invoice::query()->count())->toBe(0)
        ->and(PosSaleUpload::query()->count())->toBe(0)
        ->and(InventoryMovement::query()->count())->toBe(0)
        ->and(CashMovement::query()->count())->toBe(0)
        ->and($product->fresh()->inventory->stock_quantity)->toBe(2)
        ->and($product->fresh()->inventory->sold_quantity)->toBe(0);
});
