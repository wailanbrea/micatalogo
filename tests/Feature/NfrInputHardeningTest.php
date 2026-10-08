<?php

use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('NFR-001 rejects malformed money UUID and oversized quantities before mutation', function () {
    [$user, $shop, $product] = nfrSaleFixture();
    $token = $user->createToken('nfr-inputs', ['pos:write'])->plainTextToken;
    $url = "/api/v1/shops/{$shop->public_id}/pos-sales";

    $base = [
        'client_sale_uuid' => (string) Str::uuid(),
        'payment_status' => 'paid',
        'items' => [[
            'product_id' => $product->public_id,
            'quantity' => 1,
            'unit_price' => '250.00',
        ]],
    ];

    $this->withToken($token)->postJson($url, [...$base, 'client_sale_uuid' => 'not-a-uuid'])
        ->assertUnprocessable();
    $this->withToken($token)->postJson($url, [...$base, 'client_sale_uuid' => (string) Str::uuid(), 'items' => [[
        'product_id' => $product->public_id,
        'quantity' => 1,
        'unit_price' => '250.001',
    ]]])->assertUnprocessable();
    $this->withToken($token)->postJson($url, [...$base, 'client_sale_uuid' => (string) Str::uuid(), 'items' => [[
        'product_id' => $product->public_id,
        'quantity' => 999999999999,
        'unit_price' => '250.00',
    ]]])->assertUnprocessable();

    expect(Invoice::count())->toBe(0)
        ->and(InventoryMovement::count())->toBe(0)
        ->and($product->fresh()->inventory->stock_quantity)->toBe(5);
});

test('NFR-001 rejects cross-tenant product and customer identifiers without writes', function () {
    [$user, $shop, $product] = nfrSaleFixture();
    $otherOwner = User::factory()->create();
    $otherShop = Shop::factory()->for($otherOwner)->create();
    $otherProduct = Product::factory()->for($otherShop)->create(['price' => 250]);
    ProductInventory::create(['product_id' => $otherProduct->id, 'track_inventory' => true, 'stock_quantity' => 5]);
    $otherCustomer = Customer::create(['shop_id' => $otherShop->id, 'name' => 'Cliente de otra tienda']);
    $token = $user->createToken('nfr-tenant', ['pos:write'])->plainTextToken;
    $url = "/api/v1/shops/{$shop->public_id}/pos-sales";

    $payload = [
        'client_sale_uuid' => (string) Str::uuid(),
        'payment_status' => 'paid',
        'customer_id' => $otherCustomer->public_id,
        'items' => [[
            'product_id' => $otherProduct->public_id,
            'quantity' => 1,
            'unit_price' => '250.00',
        ]],
    ];

    $this->withToken($token)->postJson($url, $payload)->assertUnprocessable();

    expect(Invoice::count())->toBe(0)
        ->and(InventoryMovement::count())->toBe(0)
        ->and($product->fresh()->inventory->stock_quantity)->toBe(5)
        ->and($otherProduct->fresh()->inventory->stock_quantity)->toBe(5);
});

/** @return array{0: User, 1: Shop, 2: Product} */
function nfrSaleFixture(): array
{
    $user = User::factory()->create();
    $shop = Shop::factory()->for($user)->create();
    $product = Product::factory()->for($shop)->create(['price' => 250, 'sale_unit' => 'unit']);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 5,
        'sold_quantity' => 0,
        'cost_price' => 100,
    ]);

    return [$user, $shop, $product];
}
