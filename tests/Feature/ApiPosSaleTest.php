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

test('presentation hints retain sales directly measured in milliliters', function () {
    [$user, $shop, $product] = posSaleFixture(stock: 5, price: 250);
    $product->update(['sale_unit' => 'ml']);
    $product->inventory->update(['available_ml' => 5]);
    $payload = posSalePayload((string) Str::uuid(), $product, 2, '250.00');
    $payload['items'][0]['expected_sale_unit'] = 'ml';
    $this->withToken($user->createToken('BSPOS', ['pos:write'])->plainTextToken)
        ->postJson('/api/v1/shops/'.$shop->public_id.'/pos-sales', $payload)->assertCreated();
    expect($product->fresh()->inventory->available_ml)->toBe(3);
});

test('new POS clients reject a changed decant presentation before consuming shared stock', function () {
    [$user, $shop, $source] = posSaleFixture(stock: 1, price: 250);
    $source->update(['sale_unit' => 'bottle', 'volume_ml' => 100]);
    $source->inventory->update(['available_ml' => 100]);
    $decant = Product::factory()->create(['shop_id' => $shop->id, 'price' => 10,
        'sale_unit' => 'decant', 'volume_ml' => 5, 'inventory_source_product_id' => $source->id]);
    ProductInventory::create(['product_id' => $decant->id, 'track_inventory' => true, 'stock_quantity' => 20]);
    $payload = posSalePayload((string) Str::uuid(), $decant, 2, '10.00');
    $payload['items'][0] += ['expected_sale_unit' => 'decant', 'expected_volume_ml' => 5,
        'expected_source_product_id' => $source->public_id];
    $token = $user->createToken('BSPOS', ['pos:write'])->plainTextToken;
    $url = '/api/v1/shops/'.$shop->public_id.'/pos-sales';
    foreach (['volume_ml' => 6, 'inventory_source_product_id' => null, 'sale_unit' => 'unit'] as $field => $value) {
        $original = $decant->getAttribute($field);
        $decant->update([$field => $value]);
        $this->withToken($token)->postJson($url, $payload)->assertConflict()->assertJsonPath('reason', 'presentation_conflict');
        expect(Invoice::count())->toBe(0)->and(InventoryMovement::count())->toBe(0)
            ->and($source->fresh()->inventory->available_ml)->toBe(100);
        $decant->update([$field => $original]);
    }
    $this->withToken($token)->postJson($url, $payload)->assertCreated();
    expect($source->fresh()->inventory->available_ml)->toBe(90);
    $decant->update(['volume_ml' => 6]);
    $this->withToken($token)->postJson($url, $payload)->assertCreated();
    expect(Invoice::count())->toBe(1)->and($source->fresh()->inventory->available_ml)->toBe(90);
});

test('a POS sale uses the pos channel, derives partial status with customer debt, and decrements stock', function () {
    [$user, $shop, $product] = posSaleFixture(stock: 5, price: 300);
    $product->update([
        'sale_price' => 250,
        'sale_starts_at' => now()->subMinute(),
        'sale_ends_at' => now()->addMinute(),
    ]);
    $customer = Customer::create(['shop_id' => $shop->id, 'name' => 'Cliente Crédito', 'credit_limit' => 1000]);
    $clientSaleUuid = (string) Str::uuid();

    $payload = posSalePayload($clientSaleUuid, $product, 2, '250.00');
    $payload['customer_id'] = $customer->public_id;
    $payload['credit_amount'] = '250.00';
    $payload['payments'] = [
        ['method' => 'cash', 'amount' => '250.00'],
    ];

    $this->withToken($user->createToken('BSPOS', ['pos:write'])->plainTextToken)
        ->postJson("/api/v1/shops/{$shop->public_id}/pos-sales", $payload)
        ->assertCreated()
        ->assertJsonPath('client_sale_uuid', $clientSaleUuid)
        ->assertJsonPath('status', 'partial')
        ->assertJsonPath('total', '500.00');

    $invoice = Invoice::query()->with('items')->sole();
    expect($invoice->channel)->toBe('pos')
        ->and($invoice->status)->toBe('partial')
        ->and((float) $invoice->total)->toBe(500.0)
        ->and($invoice->items)->toHaveCount(1)
        ->and((float) $invoice->items->first()->unit_price)->toBe(250.0)
        ->and($product->fresh()->inventory->stock_quantity)->toBe(3)
        ->and($product->fresh()->inventory->sold_quantity)->toBe(2);

    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $product->id,
        'invoice_id' => $invoice->id,
        'type' => 'sale',
        'quantity' => -2,
        'unit_price' => 250,
    ]);
});

test('a POS sale ignores client manipulation of payment status and derives paid when fully settled', function () {
    [$user, $shop, $product] = posSaleFixture(stock: 5, price: 300);
    $clientSaleUuid = (string) Str::uuid();

    // Client maliciously or erroneously attempts to mark fully paid sale as 'partial'
    $payload = posSalePayload($clientSaleUuid, $product, 1, '300.00', 'partial');

    $this->withToken($user->createToken('BSPOS', ['pos:write'])->plainTextToken)
        ->postJson("/api/v1/shops/{$shop->public_id}/pos-sales", $payload)
        ->assertCreated()
        ->assertJsonPath('status', 'paid');

    $invoice = Invoice::query()->sole();
    expect($invoice->status)->toBe('paid');
});

test('an identical POS sale replay returns its original response without another stock decrement', function () {
    [$user, $shop, $product] = posSaleFixture(stock: 5, price: 250);
    $payload = posSalePayload((string) Str::uuid(), $product, 2, '250.00');
    $token = $user->createToken('BSPOS', ['pos:write'])->plainTextToken;

    $first = $this->withToken($token)->postJson("/api/v1/shops/{$shop->public_id}/pos-sales", $payload);
    $first->assertCreated();

    $this->withToken($token)
        ->postJson("/api/v1/shops/{$shop->public_id}/pos-sales", $payload)
        ->assertCreated()
        ->assertExactJson($first->json());

    expect($product->fresh()->inventory->stock_quantity)->toBe(3)
        ->and(InventoryMovement::query()->count())->toBe(1)
        ->and(Invoice::query()->count())->toBe(1);
});

test('a POS sale rejects a stale price without mutating inventory or invoices', function () {
    [$user, $shop, $product] = posSaleFixture(stock: 5, price: 250);

    $this->withToken($user->createToken('BSPOS', ['pos:write'])->plainTextToken)
        ->postJson("/api/v1/shops/{$shop->public_id}/pos-sales", posSalePayload((string) Str::uuid(), $product, 2, '249.99'))
        ->assertConflict()
        ->assertExactJson([
            'message' => 'The submitted price no longer matches the catalog.',
            'reason' => 'price_conflict',
        ]);

    expect($product->fresh()->inventory->stock_quantity)->toBe(5)
        ->and(InventoryMovement::query()->count())->toBe(0)
        ->and(Invoice::query()->count())->toBe(0);
});

test('a POS sale rejects insufficient remote stock without mutations', function () {
    [$user, $shop, $product] = posSaleFixture(stock: 1, price: 250);

    $this->withToken($user->createToken('BSPOS', ['pos:write'])->plainTextToken)
        ->postJson("/api/v1/shops/{$shop->public_id}/pos-sales", posSalePayload((string) Str::uuid(), $product, 2, '250.00'))
        ->assertConflict()
        ->assertExactJson([
            'message' => 'Insufficient remote stock.',
            'reason' => 'stock_conflict',
        ]);

    expect($product->fresh()->inventory->stock_quantity)->toBe(1)
        ->and(InventoryMovement::query()->count())->toBe(0)
        ->and(Invoice::query()->count())->toBe(0);
});

test('a POS sale UUID cannot be reused with a different payload', function () {
    [$user, $shop, $product] = posSaleFixture(stock: 5, price: 250);
    $clientSaleUuid = (string) Str::uuid();
    $token = $user->createToken('BSPOS', ['pos:write'])->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/shops/{$shop->public_id}/pos-sales", posSalePayload($clientSaleUuid, $product, 1, '250.00'))
        ->assertCreated();

    $this->withToken($token)
        ->postJson("/api/v1/shops/{$shop->public_id}/pos-sales", posSalePayload($clientSaleUuid, $product, 2, '250.00'))
        ->assertConflict()
        ->assertExactJson([
            'message' => 'This client sale UUID has already been used with a different payload.',
            'reason' => 'idempotency_conflict',
        ]);

    expect($product->fresh()->inventory->stock_quantity)->toBe(4)
        ->and(InventoryMovement::query()->count())->toBe(1)
        ->and(Invoice::query()->count())->toBe(1);
});

test('a POS sale returns not found for a shop owned by another user', function () {
    [, $shop, $product] = posSaleFixture(stock: 5, price: 250);
    $otherUser = User::factory()->create();

    $this->withToken($otherUser->createToken('BSPOS', ['pos:write'])->plainTextToken)
        ->postJson("/api/v1/shops/{$shop->public_id}/pos-sales", posSalePayload((string) Str::uuid(), $product, 1, '250.00'))
        ->assertNotFound();

    expect($product->fresh()->inventory->stock_quantity)->toBe(5)
        ->and(InventoryMovement::query()->count())->toBe(0)
        ->and(Invoice::query()->count())->toBe(0);
});

test('a POS sale requires the pos write token ability', function () {
    [$user, $shop, $product] = posSaleFixture(stock: 5, price: 250);

    $this->withToken($user->createToken('BSPOS', ['catalog:read'])->plainTextToken)
        ->postJson("/api/v1/shops/{$shop->public_id}/pos-sales", posSalePayload((string) Str::uuid(), $product, 1, '250.00'))
        ->assertForbidden();

    expect($product->fresh()->inventory->stock_quantity)->toBe(5)
        ->and(InventoryMovement::query()->count())->toBe(0)
        ->and(Invoice::query()->count())->toBe(0);
});

/**
 * @return array{0: User, 1: Shop, 2: Product}
 */
function posSaleFixture(int $stock, float $price): array
{
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'price' => $price,
        'sale_unit' => 'unit',
    ]);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => $stock,
        'sold_quantity' => 0,
    ]);

    return [$user, $shop, $product];
}

/**
 * @return array{client_sale_uuid: string, payment_status: string, items: array<int, array{product_id: string, quantity: int, unit_price: string}>}
 */
function posSalePayload(string $clientSaleUuid, Product $product, int $quantity, string $unitPrice, string $paymentStatus = 'paid'): array
{
    return [
        'client_sale_uuid' => $clientSaleUuid,
        'payment_status' => $paymentStatus,
        'items' => [[
            'product_id' => $product->public_id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
        ]],
    ];
}
