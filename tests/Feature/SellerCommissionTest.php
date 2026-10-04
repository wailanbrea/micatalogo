<?php

use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('a shop owner can assign an existing account with a percentage commission', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create();
    $seller = User::factory()->create(['email' => 'seller@example.com']);

    $this->actingAs($owner)
        ->post(route('seller.shops.sellers.store', $shop), [
            'email' => $seller->email,
            'commission_type' => 'percentage',
            'commission_value' => '7.50',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('shop_sellers', [
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'commission_type' => 'percentage',
        'commission_value' => 7.5,
        'is_active' => true,
    ]);
});

test('a shop owner can choose the menus visible to a seller', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create();
    $seller = User::factory()->create();
    $assignment = ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'commission_type' => 'percentage',
        'commission_value' => 5,
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->patch(route('seller.shops.sellers.update', [$shop, $assignment]), [
            'commission_type' => 'percentage',
            'commission_value' => 5,
            'menu_permissions_configured' => '1',
            'menu_permissions' => ['sales', 'customers'],
        ])
        ->assertRedirect();

    expect($assignment->fresh()->menu_permissions)->toBe(['sales', 'customers']);
});

test('a salesperson POS sale saves a percentage commission snapshot', function () {
    [$owner, $shop, $product] = commissionFixture(200);
    $seller = User::factory()->create();
    ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'commission_type' => 'percentage',
        'commission_value' => 10,
        'is_active' => true,
    ]);

    $this->withToken($seller->createToken('BSPOS', ['pos:write'])->plainTextToken)
        ->postJson("/api/v1/shops/{$shop->public_id}/pos-sales", commissionPayload($product, 2))
        ->assertCreated();

    $invoice = Invoice::query()->sole();
    expect($invoice->salesperson_id)->toBe($seller->id)
        ->and($invoice->commission_type)->toBe('percentage')
        ->and((float) $invoice->commission_value)->toBe(10.0)
        ->and((float) $invoice->commission_amount)->toBe(40.0);
});

test('a salesperson fixed commission remains unchanged after the owner updates the assignment', function () {
    [$owner, $shop, $product] = commissionFixture(150);
    $seller = User::factory()->create();
    $assignment = ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'commission_type' => 'fixed',
        'commission_value' => 25,
        'is_active' => true,
    ]);
    $token = $seller->createToken('BSPOS', ['pos:write'])->plainTextToken;

    $this->withToken($token)->postJson("/api/v1/shops/{$shop->public_id}/pos-sales", commissionPayload($product, 1))->assertCreated();
    $assignment->update(['commission_type' => 'percentage', 'commission_value' => 20]);
    $this->withToken($token)->postJson("/api/v1/shops/{$shop->public_id}/pos-sales", commissionPayload($product, 1))->assertCreated();

    $invoices = Invoice::query()->orderBy('id')->get();
    expect((float) $invoices[0]->commission_amount)->toBe(25.0)
        ->and($invoices[0]->commission_type)->toBe('fixed')
        ->and((float) $invoices[1]->commission_amount)->toBe(30.0)
        ->and($invoices[1]->commission_type)->toBe('percentage');
});

test('an inactive salesperson cannot submit POS sales for the shop', function () {
    [, $shop, $product] = commissionFixture(100);
    $seller = User::factory()->create();
    ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'commission_type' => 'fixed',
        'commission_value' => 10,
        'is_active' => false,
    ]);

    $this->withToken($seller->createToken('BSPOS', ['pos:write'])->plainTextToken)
        ->postJson("/api/v1/shops/{$shop->public_id}/pos-sales", commissionPayload($product, 1))
        ->assertNotFound();
});

/** @return array{0: User, 1: Shop, 2: Product} */
function commissionFixture(float $price): array
{
    $owner = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create();
    $product = Product::factory()->create(['shop_id' => $shop->id, 'price' => $price]);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 10,
        'sold_quantity' => 0,
    ]);

    return [$owner, $shop, $product];
}

/** @return array<string, mixed> */
function commissionPayload(Product $product, int $quantity): array
{
    return [
        'client_sale_uuid' => (string) Str::uuid(),
        'payment_status' => 'paid',
        'items' => [[
            'product_id' => $product->public_id,
            'quantity' => $quantity,
            'unit_price' => number_format((float) $product->price, 2, '.', ''),
        ]],
    ];
}
