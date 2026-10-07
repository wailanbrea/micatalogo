<?php

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductInventory;
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
        ->assertSee('Nueva cotización')
        ->assertSee('Ir a Terminal');
});

test('sales module supports Puntto-style period, search and status filters', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();

    Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $user->id,
        'invoice_number' => 'FAC-FILTER-TODAY',
        'status' => 'paid',
        'channel' => 'pos',
        'currency' => 'DOP',
        'subtotal' => 250,
        'total' => 250,
        'issued_at' => now(),
    ]);
    Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $user->id,
        'invoice_number' => 'FAC-FILTER-OLD',
        'status' => 'credit',
        'channel' => 'pos',
        'currency' => 'DOP',
        'subtotal' => 400,
        'total' => 400,
        'issued_at' => now()->subDays(35),
    ]);

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'sales']).'?period=month')
        ->assertOk()
        ->assertSee('Historial de ventas')
        ->assertSee('Este mes')
        ->assertSee('FAC-FILTER-TODAY')
        ->assertDontSee('FAC-FILTER-OLD');

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'sales']).'?period=all&status=credit&q=FAC-FILTER-OLD')
        ->assertOk()
        ->assertSee('FAC-FILTER-OLD')
        ->assertDontSee('FAC-FILTER-TODAY');
});

test('reports expose current inventory value and navigation actions', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $product = Product::factory()->for($shop)->create(['name' => 'Producto rentable', 'price' => 500]);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 3,
        'available_ml' => null,
        'cost_price' => 200,
        'sold_quantity' => 0,
        'low_stock_threshold' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'reports']))
        ->assertOk()
        ->assertSee('Reportes')
        ->assertSee('Inventario al costo')
        ->assertSee('Ver ganancias y resumen')
        ->assertSee('Abrir métricas')
        ->assertSee('Operativo');
});

test('photos module exposes a guided manager for pending product images', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    Product::factory()->for($shop)->create(['name' => 'Producto sin fotografía', 'product_code' => 'PHOTO-001']);

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'photos']))
        ->assertOk()
        ->assertSee('Gestor de fotografías')
        ->assertSee('Buscar sugerencias')
        ->assertSee('Producto sin fotografía')
        ->assertSee('Buscar producto o código');
});

test('every panel feature route renders for a shop owner', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create(['business_type' => 'perfume_store']);
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
        ->assertSee('Ventas cobradas')
        ->assertSee('Abonos recibidos')
        ->assertSee('Devoluciones')
        ->assertSee('Deberías tener')
        ->assertSee(route('seller.shops.cash.close', [$shop, $session->public_id]));
});

test('decants expose the guided presentation and shared inventory actions', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $source = Product::factory()->for($shop)->create([
        'name' => 'Botella fuente',
        'sale_unit' => 'bottle',
        'volume_ml' => 100,
    ]);
    ProductInventory::create([
        'product_id' => $source->id,
        'track_inventory' => true,
        'stock_quantity' => 1,
        'available_ml' => 100,
        'cost_price' => 1000,
        'sold_quantity' => 0,
        'low_stock_threshold' => 1,
    ]);
    Product::factory()->for($shop)->create([
        'name' => 'Decant 5 ml',
        'sale_unit' => 'decant',
        'volume_ml' => 5,
        'inventory_source_product_id' => $source->id,
    ]);

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'decants']))
        ->assertOk()
        ->assertSee('Crear presentación decant')
        ->assertSee('Ver inventario compartido')
        ->assertSee('100 ml de origen')
        ->assertSee('5 ml · 0 listos');
});

test('barber shops hide and protect the decants module', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create(['business_type' => 'barbershop']);

    expect(app(\App\Services\SellerMenuService::class)->visibleForUser($shop, $user))->not->toContain('decants');

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'decants']))
        ->assertForbidden();
});

test('feature modules remain tenant isolated', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $other = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'sales']))
        ->assertForbidden();
});
