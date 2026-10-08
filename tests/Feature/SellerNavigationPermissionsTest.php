<?php

use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\ShopSeller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function navigationSellerFixture(?array $permissions = null): array
{
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $seller = User::factory()->create();
    $assignment = ShopSeller::create([
        'shop_id' => $shop->id, 'user_id' => $seller->id, 'is_active' => true,
        'commission_type' => 'percentage', 'commission_value' => 5,
        'menu_permissions' => $permissions,
    ]);

    return [$owner, $shop, $seller, $assignment];
}

test('seller navigation hides unassigned and administrative links and rejects direct URLs', function () {
    [, $shop, $seller] = navigationSellerFixture();
    $this->actingAs($seller)->get(route('seller.shops.summary', $shop))->assertOk()
        ->assertSee('Tu espacio de ventas')->assertSee('Nueva venta')
        ->assertDontSee('Control de caja')->assertDontSee('Ganancias')
        ->assertDontSee('Precios automáticos')->assertDontSee('Subida masiva')
        ->assertDontSee('Menú Administrativo')->assertDontSee('Vendedores');

    foreach ([
        route('seller.shops.business', $shop),
        route('seller.shops.cash.index', $shop),
        route('seller.shops.inventory.index', $shop),
        route('seller.shops.sellers.index', $shop),
        route('seller.shops.edit', $shop),
    ] as $url) {
        $this->get($url)->assertForbidden();
    }
    $this->get(route('admin.dashboard'))->assertForbidden();
});

test('limited seller cannot recover FIFO costs or margins through catalog JSON, HTML, or exports', function () {
    [$owner, $shop, $seller, $assignment] = navigationSellerFixture(['sales', 'products']);
    $product = Product::factory()->for($shop)->create(['name' => 'Producto financiero oculto', 'price' => '250.00']);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 4,
        'cost_price' => '100.00',
        'sold_quantity' => 0,
        'low_stock_threshold' => 1,
    ]);

    // The seller can read the operational catalog, but its financial datum is
    // masked without the explicitly delegated finance menu.
    $catalogToken = $seller->createToken('qa-limited-catalog', ['catalog:read'])->plainTextToken;
    app('auth')->forgetGuards();
    $this->withToken($catalogToken)
        ->getJson('/api/v1/shops/'.$shop->public_id.'/catalog')
        ->assertOk()
        ->assertJsonPath('products.0.name', 'Producto financiero oculto')
        ->assertJsonPath('products.0.inventory.cost_price', null);

    $ownerToken = $owner->createToken('qa-owner-catalog', ['catalog:read'])->plainTextToken;
    app('auth')->forgetGuards();
    $this->withToken($ownerToken)
        ->getJson('/api/v1/shops/'.$shop->public_id.'/catalog')
        ->assertOk()
        ->assertJsonPath('products.0.inventory.cost_price', '100.00');

    // Direct Web/API entry points remain denied; hiding sidebar links is not
    // used as the security boundary.
    $this->actingAs($seller)->get(route('seller.shops.business', $shop))->assertForbidden();
    $this->get(route('seller.shops.feature', [$shop, 'feature' => 'reports']))->assertForbidden();
    $this->get(route('seller.shops.reports.export', [$shop, 'format' => 'csv']))->assertForbidden();

    $financeToken = $seller->createToken('qa-limited-finance', ['*'])->plainTextToken;
    foreach ([
        '/api/v1/shops/'.$shop->public_id.'/finance/summary',
        '/api/v1/shops/'.$shop->public_id.'/reports/income-statement',
        '/api/v1/shops/'.$shop->public_id.'/reports/cash-flow',
        '/api/v1/shops/'.$shop->public_id.'/reports/export?format=csv',
        '/api/v1/shops/'.$shop->public_id.'/features/reports',
    ] as $url) {
        app('auth')->forgetGuards();
        $this->withToken($financeToken)->getJson($url)->assertForbidden();
    }
});

test('seller operational modules redact financial details across web and feature APIs', function () {
    [$owner, $shop, $seller] = navigationSellerFixture(['inventory', 'price_health', 'pricing', 'decants']);
    $shop->update(['business_type' => 'perfume_store']);

    $product = Product::factory()->for($shop)->create([
        'name' => 'Perfume operativo sin costo visible',
        'price' => '250.00',
        'sale_unit' => 'bottle',
        'volume_ml' => 100,
    ]);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 4,
        'cost_price' => '100.00',
        'sold_quantity' => 0,
        'low_stock_threshold' => 1,
    ]);

    $token = $seller->createToken('qa-operational-finance-redaction', ['*'])->plainTextToken;
    foreach (['price_health', 'pricing', 'decants'] as $feature) {
        app('auth')->forgetGuards();
        $response = $this->withToken($token)
            ->getJson('/api/v1/shops/'.$shop->public_id.'/features/'.$feature)
            ->assertOk();
        $rows = $response->json('module.rows') ?? [];
        expect(json_encode($response->json(), JSON_THROW_ON_ERROR))
            ->not->toContain('Costo actual RD$ 100.00')
            ->not->toContain('Costo RD$ 100.00')
            ->not->toContain('margin_percent')
            ->not->toContain('target_margin_percent')
            ->not->toContain('pending_price');
        foreach ($rows as $row) {
            expect(json_encode($row, JSON_THROW_ON_ERROR))->not->toContain('100.00');
        }
    }

    $this->actingAs($seller)->get(route('seller.shops.inventory.index', $shop))
        ->assertOk()
        ->assertDontSee('Valor Stock')
        ->assertDontSee('Ganancia Est.')
        ->assertDontSee('Costo Compra')
        ->assertDontSee('Margen')
        ->assertDontSee('RD$ 100');

    $this->actingAs($seller)->get(route('seller.shops.inventory.lots', $shop))
        ->assertOk()
        ->assertDontSee('Costo de entrada')
        ->assertDontSee('Costo restante')
        ->assertDontSee('RD$ 100.00');

    $this->actingAs($seller)->get(route('seller.shops.feature', [$shop, 'feature' => 'price_health']))
        ->assertOk()
        ->assertSee('Revisión financiera')
        ->assertDontSee('Costo RD$ 100.00')
        ->assertDontSee('Margen bajo');

    expect($owner->fresh()->id)->toBe($shop->user_id);
});

test('owner and administrator can grant and revoke individual seller menus', function (bool $admin) {
    [$owner, $shop, $seller, $assignment] = navigationSellerFixture();
    $manager = $admin ? User::factory()->admin()->create() : $owner;
    $this->actingAs($manager)->get(route('seller.shops.sellers.index', $shop))->assertOk()
        ->assertSee('Menús visibles para este vendedor');
    $update = ['commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions_configured' => 1];
    $this->patch(route('seller.shops.sellers.update', [$shop, $assignment]), $update + ['menu_permissions' => ['customers']])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($seller)->get(route('seller.shops.customers.index', $shop))->assertOk();
    $this->get(route('seller.shops.pos', $shop))->assertForbidden();
    $this->actingAs($manager)->patch(route('seller.shops.sellers.update', [$shop, $assignment]), $update + ['menu_permissions' => []])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($seller)->get(route('seller.shops.customers.index', $shop))->assertForbidden();
    expect($assignment->fresh()->menu_permissions)->toBe([]);
})->with([false, true]);

test('summary and profits are distinct destinations with exactly one selected sidebar link', function () {
    [$owner, $shop] = navigationSellerFixture();
    $response = $this->actingAs($owner)->get(route('seller.shops.summary', $shop))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($dom);
    $active = $xpath->query('//aside//a[@aria-current="page"]');
    expect($active->length)->toBe(1)
        ->and(trim($active->item(0)->textContent))->toBe('Resumen')
        ->and(route('seller.shops.summary', $shop))->not->toBe(route('seller.shops.business', $shop));
});

test('accountant sees financial navigation and summary without seller capabilities', function () {
    [, $shop] = navigationSellerFixture();
    $accountant = User::factory()->create(['name' => 'Contador QA']);
    ShopMember::create(['shop_id' => $shop->id, 'user_id' => $accountant->id, 'role' => 'accountant', 'is_active' => true]);

    $this->actingAs($accountant)->get(route('seller.shops.summary', $shop))->assertOk()
        ->assertSee('Contador')->assertSee('Resumen financiero')
        ->assertSee('Ganancias')->assertSee('Reportes')
        ->assertDontSee('Panel del vendedor');
    $this->get(route('seller.shops.business', $shop))->assertOk();
    $this->get(route('seller.shops.feature', [$shop, 'feature' => 'reports']))->assertOk();
    $this->get(route('seller.shops.feature', [$shop, 'feature' => 'accountant']))->assertForbidden();
    $this->post(route('seller.shops.accountant.store', $shop), ['email' => 'another-accountant@example.test'])
        ->assertForbidden();
    $accountantToken = $accountant->createToken('qa-accountant', ['*'])->plainTextToken;
    app('auth')->forgetGuards();
    $this->withToken($accountantToken)
        ->getJson('/api/v1/shops/'.$shop->public_id.'/features/accountant')
        ->assertForbidden();
    app('auth')->forgetGuards();
    $this->withToken($accountantToken)
        ->getJson('/api/v1/shops/'.$shop->public_id.'/features/reports')
        ->assertOk();
    $this->get(route('seller.shops.pos', $shop))->assertForbidden();
});

test('seller cannot change permissions or grant access to owner-only settings', function () {
    [$owner, $shop, $seller, $assignment] = navigationSellerFixture();
    $payload = ['commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions_configured' => 1, 'menu_permissions' => ['shop_settings']];
    $this->actingAs($seller)->patch(route('seller.shops.sellers.update', [$shop, $assignment]), $payload)->assertForbidden();
    $this->actingAs($owner)->patch(route('seller.shops.sellers.update', [$shop, $assignment]), $payload)
        ->assertSessionHasErrors('menu_permissions.0');
});

test('seller dashboard isolates sales and frozen commissions by seller and period', function () {
    $this->travelTo(now()->setDate(2026, 10, 8)->setTime(12, 0));
    [$owner, $shop, $seller, $assignment] = navigationSellerFixture();
    foreach ([
        ['number' => 'OWN-TODAY', 'seller' => $seller->id, 'days' => 0, 'total' => '9500.00', 'commission' => '475.00', 'status' => 'paid'],
        ['number' => 'OWN-YESTERDAY', 'seller' => $seller->id, 'days' => 1, 'total' => '1000.00', 'commission' => '80.00', 'status' => 'partial'],
        ['number' => 'OTHER-SELLER', 'seller' => $owner->id, 'days' => 0, 'total' => '5000.00', 'commission' => '999.00', 'status' => 'paid'],
        ['number' => 'CANCELLED-SALE', 'seller' => $seller->id, 'days' => 0, 'total' => '5000.00', 'commission' => '999.00', 'status' => 'cancelled'],
    ] as $row) {
        Invoice::create(['shop_id' => $shop->id, 'user_id' => $row['seller'], 'salesperson_id' => $row['seller'],
            'invoice_number' => $row['number'], 'status' => $row['status'], 'subtotal' => $row['total'], 'total' => $row['total'],
            'commission_amount' => $row['commission'], 'issued_at' => now()->subDays($row['days'])]);
    }
    $assignment->update(['commission_value' => 50]);
    $this->actingAs($seller)->get(route('seller.shops.summary', $shop))->assertOk()
        ->assertViewHas('metrics', fn ($metrics) => $metrics === ['count' => 1, 'total' => 950000, 'commission' => 47500, 'average' => 950000])
        ->assertSee('Tus ganancias')->assertSee('OWN-TODAY')->assertDontSee('OTHER-SELLER')->assertDontSee('CANCELLED-SALE');
    $this->get(route('seller.shops.summary', [$shop, 'period' => 'week']))->assertOk()
        ->assertViewHas('metrics', fn ($metrics) => $metrics['count'] === 2 && $metrics['commission'] === 55500 && $metrics['total'] === 1050000)
        ->assertSee('OWN-YESTERDAY');
    $this->get(route('seller.dashboard'))->assertRedirect(route('seller.shops.summary', $shop));
    $this->actingAs($seller, 'sanctum')->getJson('/api/v1/shops/'.$shop->public_id.'/seller-summary?period=week')
        ->assertOk()->assertJsonPath('metrics.total', 1050000)->assertJsonPath('metrics.commission', 55500)
        ->assertJsonCount(2, 'sales')->assertJsonPath('sales.0.invoice_number', 'OWN-TODAY');
    $this->getJson(route('seller.shops.summary', [$shop, 'period' => 'invalid']))->assertUnprocessable();
});

test('owner and admin select menus when creating a seller and can revoke them later', function (bool $api, bool $admin) {
    [$owner, $shop] = navigationSellerFixture();
    $manager = $admin ? User::factory()->admin()->create() : $owner;
    $newSeller = User::factory()->create(['email' => 'selected-menus@example.com']);
    $payload = ['email' => $newSeller->email, 'commission_type' => 'percentage', 'commission_value' => 5,
        'menu_permissions_configured' => 1, 'menu_permissions' => ['customers']];
    if ($api) {
        $this->actingAs($manager, 'sanctum')->postJson('/api/v1/shops/'.$shop->public_id.'/sellers', $payload)
            ->assertOk()->assertJsonPath('seller.menu_permissions', ['customers']);
    } else {
        $this->actingAs($manager)->post(route('seller.shops.sellers.store', $shop), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
    }
    $assignment = ShopSeller::where('shop_id', $shop->id)->where('user_id', $newSeller->id)->sole();
    expect($assignment->menu_permissions)->toBe(['customers']);
    $this->actingAs($newSeller)->get(route('seller.shops.customers.index', $shop))->assertOk();
    $this->get(route('seller.shops.pos', $shop))->assertForbidden();
    if ($api) {
        $this->actingAs($manager, 'sanctum')->putJson('/api/v1/shops/'.$shop->public_id.'/sellers/'.$assignment->id.'/menus', ['menu_permissions' => []])
            ->assertOk()->assertJsonPath('menu_permissions', []);
    } else {
        $this->actingAs($manager)->patch(route('seller.shops.sellers.update', [$shop, $assignment]), [
            'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions_configured' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
    }
    $this->actingAs($newSeller)->get(route('seller.shops.customers.index', $shop))->assertForbidden();
    expect($assignment->fresh()->menu_permissions)->toBe([]);
})->with([[false, false], [false, true], [true, false], [true, true]]);

test('owner can deactivate a seller and revoke accountant access without deleting history', function () {
    [$owner, $shop, $seller, $assignment] = navigationSellerFixture();
    $manager = User::factory()->create(['email' => 'revoke-manager@example.com']);
    $managerMembership = ShopMember::create([
        'shop_id' => $shop->id,
        'user_id' => $manager->id,
        'role' => 'manager',
        'is_active' => true,
    ]);
    $accountant = User::factory()->create(['email' => 'revoke-accountant@example.com']);
    $membership = ShopMember::create([
        'shop_id' => $shop->id,
        'user_id' => $accountant->id,
        'role' => 'accountant',
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->delete(route('seller.shops.sellers.destroy', [$shop, $assignment]))
        ->assertRedirect()
        ->assertSessionHas('status', 'Vendedor desactivado. Su historial de ventas permanece disponible.');

    expect($assignment->fresh()->is_active)->toBeFalse();

    $this->actingAs($owner)
        ->delete(route('seller.shops.members.destroy', [$shop, $managerMembership]))
        ->assertRedirect()
        ->assertSessionHas('status', 'El acceso administrativo fue desactivado.');

    expect($managerMembership->fresh()->is_active)->toBeFalse();

    $this->actingAs($owner)
        ->delete(route('seller.shops.accountant.destroy', [$shop, $membership]))
        ->assertRedirect()
        ->assertSessionHas('status', 'El acceso del contador fue desactivado.');

    expect($membership->fresh()->is_active)->toBeFalse()
        ->and(User::find($seller->id))->not->toBeNull();
});

test('creating a seller accepts an empty menu selection and rejects owner-only menus', function () {
    [$owner, $shop] = navigationSellerFixture();
    $seller = User::factory()->create();
    $payload = ['email' => $seller->email, 'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions' => []];
    $this->actingAs($owner, 'sanctum')->postJson('/api/v1/shops/'.$shop->public_id.'/sellers', $payload)
        ->assertOk()->assertJsonPath('seller.menu_permissions', []);
    $this->postJson('/api/v1/shops/'.$shop->public_id.'/sellers', array_replace($payload, ['email' => 'invalid-permissions@example.com', 'menu_permissions' => ['sellers']]))
        ->assertUnprocessable()->assertJsonValidationErrors('menu_permissions.0');
    expect(User::where('email', 'invalid-permissions@example.com')->exists())->toBeFalse();
});
