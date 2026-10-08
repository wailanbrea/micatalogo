<?php

use App\Enums\UserPlan;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('E2E-10 multi-shop owner and seller stay isolated across verticals', function () {
    $owner = User::factory()->create([
        'plan' => UserPlan::Pro,
        'email' => 'e2e-10-owner@example.com',
    ]);
    $seller = User::factory()->create(['email' => 'e2e-10-seller@example.com']);
    $perfumeShop = Shop::factory()->for($owner)->create([
        'name' => 'E2E Perfumería',
        'business_type' => 'perfume_store',
    ]);
    $clothingShop = Shop::factory()->for($owner)->create([
        'name' => 'E2E Ropa',
        'business_type' => 'clothing',
    ]);

    $perfume = Product::factory()->for($perfumeShop)->create([
        'name' => 'Perfume E2E-10',
        'price' => '200.00',
        'sale_unit' => 'bottle',
        'volume_ml' => 100,
    ]);
    $clothing = Product::factory()->for($clothingShop)->create([
        'name' => 'Camisa E2E-10',
        'price' => '350.00',
        'sale_unit' => 'unit',
    ]);
    ProductInventory::query()->create([
        'product_id' => $perfume->id,
        'track_inventory' => true,
        'stock_quantity' => 2,
        'sold_quantity' => 0,
        'cost_price' => '100.00',
        'available_ml' => 200,
    ]);
    ProductInventory::query()->create([
        'product_id' => $clothing->id,
        'track_inventory' => true,
        'stock_quantity' => 3,
        'sold_quantity' => 0,
        'cost_price' => '180.00',
    ]);
    ShopSeller::create([
        'shop_id' => $perfumeShop->id,
        'user_id' => $seller->id,
        'commission_type' => 'percentage',
        'commission_value' => '10.00',
        'is_active' => true,
        'menu_permissions' => ['sales'],
    ]);

    $ownerToken = $owner->createToken('e2e-10-owner')->plainTextToken;
    $sellerToken = $seller->createToken('e2e-10-seller', ['pos:write'])->plainTextToken;
    $as = function (string $token) {
        app('auth')->forgetGuards();

        return $this->withToken($token);
    };
    $perfumeBase = "/api/v1/shops/{$perfumeShop->public_id}";
    $clothingBase = "/api/v1/shops/{$clothingShop->public_id}";

    $as($ownerToken)->getJson('/api/v1/shops')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.presentation.archetype', 'fragrance')
        ->assertJsonPath('1.presentation.archetype', 'fashion');

    $as($sellerToken)->getJson('/api/v1/shops')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $perfumeShop->public_id)
        ->assertJsonPath('0.presentation.archetype', 'fragrance');

    $as($sellerToken)->getJson("{$clothingBase}/catalog")
        ->assertNotFound();

    $perfumeSale = $as($sellerToken)->postJson("{$perfumeBase}/pos-sales", [
        'client_sale_uuid' => (string) Str::uuid(),
        'payment_status' => 'paid',
        'items' => [[
            'product_id' => $perfume->public_id,
            'quantity' => 1,
            'unit_price' => '200.00',
        ]],
    ]);
    $perfumeSale->assertCreated()->assertJsonPath('status', 'paid');

    $as($sellerToken)->postJson("{$clothingBase}/pos-sales", [
        'client_sale_uuid' => (string) Str::uuid(),
        'payment_status' => 'paid',
        'items' => [[
            'product_id' => $clothing->public_id,
            'quantity' => 1,
            'unit_price' => '350.00',
        ]],
    ])->assertNotFound();

    expect(ProductInventory::query()->where('product_id', $perfume->id)->value('stock_quantity'))->toBe(1)
        ->and(ProductInventory::query()->where('product_id', $clothing->id)->value('stock_quantity'))->toBe(3)
        ->and(Invoice::query()->where('shop_id', $perfumeShop->id)->count())->toBe(1)
        ->and(Invoice::query()->where('shop_id', $clothingShop->id)->count())->toBe(0);
});
