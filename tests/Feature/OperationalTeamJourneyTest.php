<?php

use App\Enums\UserPlan;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\ShopSeller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('E2E-02 team permissions seller sale commission and accountant read-only access', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro, 'email' => 'e2e-02-owner@example.com']);
    $manager = User::factory()->create(['email' => 'e2e-02-manager@example.com']);
    $accountant = User::factory()->create(['email' => 'e2e-02-accountant@example.com']);
    $seller = User::factory()->create(['email' => 'e2e-02-seller@example.com']);
    $shop = Shop::factory()->for($owner)->create();

    ShopMember::create(['shop_id' => $shop->id, 'user_id' => $manager->id, 'role' => 'manager', 'is_active' => true]);
    ShopMember::create(['shop_id' => $shop->id, 'user_id' => $accountant->id, 'role' => 'accountant', 'is_active' => true]);

    $product = Product::factory()->for($shop)->create([
        'name' => 'Producto de equipo E2E-02',
        'price' => '200.00',
        'sale_unit' => 'unit',
    ]);
    ProductInventory::query()->create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 3,
        'sold_quantity' => 0,
        'cost_price' => '100.00',
    ]);
    $base = "/api/v1/shops/{$shop->public_id}";
    $as = function (User $user, array $abilities = ['*']) {
        app('auth')->forgetGuards();

        return $this->withToken($user->createToken('e2e-02-'.Str::lower(Str::random(8)), $abilities)->plainTextToken);
    };

    $as($owner)
        ->postJson("{$base}/sellers", [
            'email' => $seller->email,
            'commission_type' => 'percentage',
            'commission_value' => '10.00',
            'menu_permissions' => ['sales', 'customers'],
        ])
        ->assertOk()
        ->assertJsonPath('seller.email', $seller->email)
        ->assertJsonPath('seller.menu_permissions', ['sales', 'customers']);

    $assignment = ShopSeller::query()->where('shop_id', $shop->id)->where('user_id', $seller->id)->sole();
    $as($owner)
        ->putJson("{$base}/sellers/{$assignment->id}/menus", ['menu_permissions' => ['sales', 'customers']])
        ->assertOk()
        ->assertExactJson(['menu_permissions' => ['sales', 'customers']]);

    $as($manager)
        ->getJson('/api/v1/shops')
        ->assertOk()
        ->assertJsonPath('0.can_manage_sellers', true);

    $as($seller, ['pos:write'])
        ->getJson('/api/v1/shops')
        ->assertOk()
        ->assertJsonPath('0.menu_permissions', ['sales', 'customers'])
        ->assertJsonPath('0.can_manage_sellers', false);

    $as($seller, ['pos:write'])
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
        ->assertJsonPath('status', 'paid');

    $invoice = Invoice::query()->where('shop_id', $shop->id)->where('salesperson_id', $seller->id)->sole();
    expect($invoice->commission_type)->toBe('percentage')
        ->and((float) $invoice->commission_amount)->toBe(20.0)
        ->and($product->fresh()->inventory->stock_quantity)->toBe(2);

    $as($accountant)
        ->getJson("{$base}/finance/summary")
        ->assertOk()
        ->assertJsonPath('period.net_sales', 200);

    $as($seller, ['pos:write'])
        ->getJson("{$base}/finance/summary")
        ->assertForbidden();

    $as($seller, ['pos:write'])
        ->postJson("{$base}/finance/day-close", [
            'date' => now()->toDateString(),
            'counted_cash' => '200.00',
        ])
        ->assertForbidden();

    $as($seller, ['pos:write'])
        ->postJson("{$base}/sellers", [
            'email' => 'e2e-02-not-allowed@example.com',
            'commission_type' => 'percentage',
            'commission_value' => '5.00',
        ])
        ->assertForbidden();

    expect(ShopSeller::query()->where('shop_id', $shop->id)->count())->toBe(1)
        ->and(Invoice::query()->where('shop_id', $shop->id)->count())->toBe(1);
});

test('ACL-003/005/006 direct API cannot bypass seller menus or accountant read-only access', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro, 'email' => 'acl-menu-owner@example.com']);
    $seller = User::factory()->create(['email' => 'acl-menu-seller@example.com']);
    $accountant = User::factory()->create(['email' => 'acl-menu-accountant@example.com']);
    $shop = Shop::factory()->for($owner)->create();

    ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'commission_type' => 'percentage',
        'commission_value' => '10.00',
        'is_active' => true,
        'menu_permissions' => ['sales', 'customers'],
    ]);
    ShopMember::create(['shop_id' => $shop->id, 'user_id' => $accountant->id, 'role' => 'accountant', 'is_active' => true]);

    $product = Product::factory()->for($shop)->create(['price' => '200.00', 'sale_unit' => 'unit']);
    ProductInventory::query()->create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 2,
        'sold_quantity' => 0,
        'cost_price' => '100.00',
    ]);
    $order = $shop->orders()->create([
        'order_number' => 'ACL-MENU-ORDER',
        'currency' => 'DOP',
        'subtotal' => 200,
        'total' => 200,
        'status' => 'sent_to_whatsapp',
    ]);
    $order->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => 200,
        'line_total' => 200,
    ]);

    $base = "/api/v1/shops/{$shop->public_id}";
    $as = function (User $user, array $abilities = ['*']) {
        app('auth')->forgetGuards();

        return $this->withToken($user->createToken('acl-menu-'.Str::lower(Str::random(8)), $abilities)->plainTextToken);
    };

    $sellerClient = $as($seller, ['pos:write', 'customers:read', 'customers:write']);
    $sellerClient->getJson("{$base}/settings")->assertForbidden();
    $sellerClient->getJson("{$base}/purchases")->assertForbidden();
    $sellerClient->getJson("{$base}/reports/income-statement")->assertForbidden();
    $sellerClient->getJson("{$base}/features/decants")->assertForbidden();
    $sellerClient->postJson("{$base}/quotes", [])->assertForbidden();
    $sellerClient->postJson("{$base}/orders/{$order->id}/confirm", [])->assertForbidden();
    $sellerClient->postJson("{$base}/cash-sessions/open", [])->assertForbidden();
    $sellerClient->postJson("{$base}/expenses", [])->assertForbidden();
    $sellerClient->postJson("{$base}/inventory-import", [])->assertForbidden();
    $sellerClient->postJson("{$base}/mobile-operations", [
        'client_operation_uuid' => (string) Str::uuid(),
        'type' => 'product_upsert',
        'product_id' => $product->public_id,
        'name' => 'No debe editarse por API',
        'price' => '300.00',
    ])->assertForbidden();
    $sellerClient->postJson("{$base}/sellers", [])->assertForbidden();

    $accountantClient = $as($accountant, ['*']);
    $accountantClient->getJson("{$base}/finance/summary")->assertOk();
    $accountantClient->getJson("{$base}/finance/day-close")->assertOk();
    $accountantClient->postJson("{$base}/finance/day-close", [
        'date' => now()->toDateString(),
        'counted_cash' => '0.00',
    ])->assertForbidden();
    $accountantClient->postJson("{$base}/cash-sessions/open", [])->assertForbidden();
    $accountantClient->postJson("{$base}/expenses", [])->assertForbidden();
    $accountantClient->postJson("{$base}/sellers", [])->assertForbidden();

    expect($product->fresh()->price)->toBe('200.00')
        ->and(ProductInventory::query()->where('product_id', $product->id)->value('stock_quantity'))->toBe(2)
        ->and(Invoice::query()->where('shop_id', $shop->id)->count())->toBe(0);
});
