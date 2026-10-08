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

test('ACL-020 core operational actions succeed for owner manager seller and accountant', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Pro, 'email' => 'acl020-owner@example.com']);
    $manager = User::factory()->create(['email' => 'acl020-manager@example.com']);
    $seller = User::factory()->create(['email' => 'acl020-seller@example.com']);
    $accountant = User::factory()->create(['email' => 'acl020-accountant@example.com']);
    $shop = Shop::factory()->for($owner)->create();

    ShopMember::create([
        'shop_id' => $shop->id,
        'user_id' => $manager->id,
        'role' => 'manager',
        'is_active' => true,
    ]);
    ShopMember::create([
        'shop_id' => $shop->id,
        'user_id' => $accountant->id,
        'role' => 'accountant',
        'is_active' => true,
    ]);
    ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'commission_type' => 'percentage',
        'commission_value' => '5.00',
        'is_active' => true,
        'menu_permissions' => ['sales', 'day_close'],
    ]);

    $product = Product::factory()->for($shop)->create([
        'name' => 'ACL 020 Producto',
        'price' => '100.00',
        'sale_unit' => 'unit',
    ]);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 4,
        'sold_quantity' => 0,
        'cost_price' => '40.00',
    ]);

    $base = "/api/v1/shops/{$shop->public_id}";
    $as = function (User $user, array $abilities = []) {
        // Sanctum's guard can remain resolved during a feature test. Reset it
        // before every request so each positive action runs as its real actor.
        app('auth')->forgetGuards();

        return $this->withToken($user->createToken('acl020-'.uniqid(), $abilities)->plainTextToken);
    };

    $as($owner, ['*'])
        ->postJson("{$base}/cash-sessions/open", ['opening_amount' => '0.00'])
        ->assertCreated()
        ->assertJsonPath('session.status', 'open');

    $as($manager, ['pos:write'])
        ->postJson("{$base}/pos-sales", roleMatrixSalePayload($product, 'acl020-manager'))
        ->assertCreated()
        ->assertJsonPath('status', 'paid');

    $as($seller, ['pos:write'])
        ->postJson("{$base}/pos-sales", roleMatrixSalePayload($product, 'acl020-seller'))
        ->assertCreated()
        ->assertJsonPath('status', 'paid');

    $as($accountant, ['*'])
        ->getJson("{$base}/finance/summary")
        ->assertOk()
        ->assertJsonPath('period.net_sales', 200);

    $as($accountant, ['*'])
        ->getJson("{$base}/finance/day-close")
        ->assertOk()
        ->assertJsonPath('closure', null);

    $as($accountant, ['*'])
        ->postJson("{$base}/finance/day-close", [
            'date' => now()->toDateString(),
            'counted_cash' => '200.00',
        ])
        ->assertForbidden();

    $as($seller, ['*'])
        ->postJson("{$base}/finance/day-close", [
            'date' => now()->toDateString(),
            'counted_cash' => '200.00',
        ])
        ->assertOk()
        ->assertJsonPath('closure.difference', 0);

    $sellerInvoice = Invoice::query()
        ->where('shop_id', $shop->id)
        ->where('salesperson_id', $seller->id)
        ->sole();

    expect(Invoice::query()->where('shop_id', $shop->id)->count())->toBe(2)
        ->and($sellerInvoice->commission_type)->toBe('percentage')
        ->and((float) $sellerInvoice->commission_amount)->toBe(5.0)
        ->and($product->fresh()->inventory->stock_quantity)->toBe(2);
});

/** @return array<string, mixed> */
function roleMatrixSalePayload(Product $product, string $actor): array
{
    return [
        'client_sale_uuid' => (string) Str::uuid(),
        'payment_status' => 'paid',
        'items' => [[
            'product_id' => $product->public_id,
            'quantity' => 1,
            'unit_price' => '100.00',
        ]],
        'notes' => "ACL-020 {$actor}",
    ];
}
