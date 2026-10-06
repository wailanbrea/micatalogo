<?php

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\CashRegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('operational modules read existing sales and orders without duplicating data', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $product = Product::factory()->for($shop)->create(['price' => 250]);

    Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $user->id,
        'invoice_number' => 'FAC-MOD-001',
        'status' => 'paid',
        'channel' => 'pos',
        'currency' => 'DOP',
        'subtotal' => 250,
        'total' => 250,
        'issued_at' => now(),
    ]);
    Order::create([
        'shop_id' => $shop->id,
        'order_number' => 'PED-MOD-001',
        'customer_name' => 'Cliente de prueba',
        'delivery_type' => 'delivery',
        'currency' => 'DOP',
        'subtotal' => 250,
        'total' => 250,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'sales']))
        ->assertOk()
        ->assertSee('Ventas de hoy')
        ->assertSee('FAC-MOD-001')
        ->assertSee('Operativo');

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'orders']))
        ->assertOk()
        ->assertSee('PED-MOD-001')
        ->assertSee('Confirmar venta');

    expect(Invoice::where('shop_id', $shop->id)->count())->toBe(1)
        ->and(Order::where('shop_id', $shop->id)->count())->toBe(1)
        ->and(Product::where('shop_id', $shop->id)->count())->toBe(1);
});

test('all feature modules have a useful protected entry point', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'quotes']))
        ->assertOk()
        ->assertSee('Cotizaciones')
        ->assertSee('Solicitudes por atender')
        ->assertSee('Ir a Terminal');
});

test('every panel feature route renders for a shop owner', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $features = [
        'sales', 'quotes', 'orders', 'encargos', 'shipments', 'day_close',
        'containers', 'loads', 'suppliers', 'purchase_invoices', 'photos',
        'services', 'price_health', 'decants', 'attributes', 'credit',
        'inventory_adjustments', 'partners', 'reports', 'commissions',
        'authorizations', 'accountant', 'account', 'updates', 'help',
        'practice', 'support',
    ];

    foreach ($features as $feature) {
        $this->actingAs($user)
            ->get(route('seller.shops.feature', [$shop, 'feature' => $feature]))
            ->assertOk()
            ->assertSee('Operativo');
    }
});

test('day close exposes the existing cash session reconciliation flow', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $session = app(CashRegisterService::class)->openSession($shop, $user, '500.00', 'Turno de prueba');

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'day_close']))
        ->assertOk()
        ->assertSee('Sesión de caja activa')
        ->assertSee('Efectivo contado')
        ->assertSee(route('seller.shops.cash.close', [$shop, $session->public_id]));
});

test('feature modules remain tenant isolated', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $other = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'sales']))
        ->assertForbidden();
});
