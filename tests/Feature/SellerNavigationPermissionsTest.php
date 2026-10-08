<?php

use App\Models\Invoice;
use App\Models\Shop;
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

    foreach (['shops.business', 'shops.cash.index', 'shops.inventory.index', 'shops.sellers.index', 'shops.edit'] as $route) {
        $this->get(route('seller.'.$route, $shop))->assertForbidden();
    }
    $this->get(route('admin.dashboard'))->assertForbidden();
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
