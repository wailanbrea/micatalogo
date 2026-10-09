<?php

use App\Models\AttributeDefinition;
use App\Models\CommercialQuote;
use App\Models\CommercialQuoteItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\SellerMenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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

test('comisiones incluye ventas del vendedor aunque la comisión calculada sea cero', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $seller = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'salesperson_id' => $seller->id,
        'invoice_number' => 'FAC-COM-000',
        'status' => 'paid',
        'channel' => 'pos',
        'currency' => 'DOP',
        'subtotal' => 3700,
        'total' => 3700,
        'commission_amount' => 0,
        'issued_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'commissions']))
        ->assertOk()
        ->assertSee('FAC-COM-000')
        ->assertSee('Ventas comisionables')
        ->assertSee('Resumen de comisiones')
        ->assertSee('Esta quincena')
        ->assertSee('Vendedor');
});

test('encargos organiza pedidos por fecha y muestra el resumen de Puntto', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();

    Order::create([
        'shop_id' => $shop->id,
        'order_number' => 'ENC-HOY',
        'customer_name' => 'Cliente hoy',
        'delivery_at' => today(),
        'currency' => 'DOP',
        'subtotal' => 500,
        'total' => 500,
        'status' => 'sent_to_whatsapp',
    ]);
    Order::create([
        'shop_id' => $shop->id,
        'order_number' => 'ENC-ATRASADO',
        'customer_name' => 'Cliente atrasado',
        'delivery_at' => today()->subDay(),
        'currency' => 'DOP',
        'subtotal' => 750,
        'total' => 750,
        'status' => 'sent_to_whatsapp',
    ]);

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'encargos']))
        ->assertOk()
        ->assertSee('Hoy')
        ->assertSee('Mañana')
        ->assertSee('Atrasados')
        ->assertSee('ENC-HOY')
        ->assertSee('ENC-ATRASADO')
        ->assertSee(today()->format('d/m/Y'));
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

test('cotizaciones muestra vigentes, monto por convertir y vencidas en el resumen', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();

    CommercialQuote::create([
        'shop_id' => $shop->id,
        'quote_number' => 'COT-TEST-001',
        'currency' => 'DOP',
        'status' => 'draft',
        'subtotal' => 1250,
        'total' => 1250,
        'valid_until' => today()->addDays(7),
    ]);
    CommercialQuote::create([
        'shop_id' => $shop->id,
        'quote_number' => 'COT-TEST-002',
        'currency' => 'DOP',
        'status' => 'sent',
        'subtotal' => 750,
        'total' => 750,
        'valid_until' => today()->subDay(),
    ]);

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'quotes']))
        ->assertOk()
        ->assertSee('Vigentes')
        ->assertSee('Monto por convertir')
        ->assertSee('Vencidas')
        ->assertSee('RD$ 1,250.00')
        ->assertSee('Vencida');
});

test('cotizacion tiene detalle navegable y descarga pdf', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $product = Product::factory()->for($shop)->create(['name' => 'Producto para cotizar', 'price' => 425]);
    $quote = CommercialQuote::create([
        'shop_id' => $shop->id,
        'user_id' => $user->id,
        'quote_number' => 'COT-DETAIL-001',
        'currency' => 'DOP',
        'status' => 'draft',
        'subtotal' => 850,
        'total' => 850,
        'valid_until' => today()->addDays(7),
    ]);
    CommercialQuoteItem::create([
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 2,
        'unit_price' => 425,
        'line_total' => 850,
    ]);

    $this->actingAs($user)
        ->get(route('seller.shops.quotes.show', [$shop, 'quote' => $quote]))
        ->assertOk()
        ->assertSee('COT-DETAIL-001')
        ->assertSee('Descargar PDF')
        ->assertSee('Producto para cotizar');

    $this->actingAs($user)
        ->get(route('seller.shops.quotes.pdf', [$shop, 'quote' => $quote]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
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
    Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $user->id,
        'invoice_number' => 'FAC-FILTER-PENDING',
        'status' => 'pending',
        'channel' => 'pos',
        'currency' => 'DOP',
        'subtotal' => 500,
        'total' => 500,
        'issued_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'sales']).'?period=month')
        ->assertOk()
        ->assertSee('Historial de ventas')
        ->assertSee('Este mes')
        ->assertSee('FAC-FILTER-TODAY')
        ->assertDontSee('Consulta y administra')
        ->assertDontSee('FAC-FILTER-OLD');

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'sales']).'?period=all&status=credit&q=FAC-FILTER-OLD')
        ->assertOk()
        ->assertSee('FAC-FILTER-OLD')
        ->assertDontSee('FAC-FILTER-TODAY');

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'sales']).'?period=all&status=credit&q=FAC-FILTER-PENDING')
        ->assertOk()
        ->assertSee('FAC-FILTER-PENDING')
        ->assertSee('A crédito');
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
        ->assertSee('Ventas')
        ->assertSee('Servicios')
        ->assertSee('Visitas')
        ->assertSee('Enlaces')
        ->assertSee('Ver ganancias y resumen')
        ->assertSee('Abrir métricas')
        ->assertSee('Operativo');
});

test('reports export csv and xlsx without mutating the shop', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    Product::factory()->for($shop)->create(['name' => 'Exportable']);

    $csv = $this->actingAs($user)->get(route('seller.shops.reports.export', [$shop, 'format' => 'csv']));
    $csv->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload('micatalogo-reportes-'.$shop->slug.'-'.now()->startOfMonth()->toDateString().'-'.now()->toDateString().'.csv');

    $xlsx = $this->actingAs($user)->get(route('seller.shops.reports.export', [$shop, 'format' => 'xlsx']));
    $xlsx->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertDownload('micatalogo-reportes-'.$shop->slug.'-'.now()->startOfMonth()->toDateString().'-'.now()->toDateString().'.xlsx');

    expect(Product::where('shop_id', $shop->id)->count())->toBe(1);
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

test('services module exposes Puntto-style offers without inventory', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create(['business_type' => 'barbershop']);
    Product::factory()->for($shop)->create([
        'name' => 'Corte clásico',
        'sale_unit' => 'service',
        'price' => 800,
        'description' => 'Corte con acabado y lavado.',
    ]);

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'services']))
        ->assertOk()
        ->assertSee('Servicios')
        ->assertSee('Nuevo servicio')
        ->assertSee('Corte clásico')
        ->assertSee('Sin costo de insumos')
        ->assertSee('Servicio sin inventario');

    $this->actingAs($user)
        ->get(route('seller.shops.products.create', $shop).'?sale_unit=service')
        ->assertOk()
        ->assertSee('Servicio (sin inventario)')
        ->assertSee('Servicio sin inventario');
});

test('services module searches by name, code or description', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create(['business_type' => 'barbershop']);
    Product::factory()->for($shop)->create([
        'name' => 'Corte clásico',
        'product_code' => 'CORTE-001',
        'sale_unit' => 'service',
        'price' => 800,
        'description' => 'Lavado y acabado.',
    ]);
    Product::factory()->for($shop)->create([
        'name' => 'Diseño de barba',
        'product_code' => 'BARBA-001',
        'sale_unit' => 'service',
        'price' => 500,
    ]);

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'services']).'?q=CORTE-001')
        ->assertOk()
        ->assertSee('Corte clásico')
        ->assertDontSee('Diseño de barba')
        ->assertSee('1 servicio(s) encontrados');
});

test('encargos supports date filters and routes confirmation through payment review', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    Order::create([
        'shop_id' => $shop->id,
        'order_number' => 'ENC-FILTRO-HOY',
        'customer_name' => 'Cliente hoy',
        'delivery_at' => today(),
        'currency' => 'DOP',
        'subtotal' => 600,
        'total' => 600,
        'status' => 'sent_to_whatsapp',
    ]);
    Order::create([
        'shop_id' => $shop->id,
        'order_number' => 'ENC-FILTRO-ATRASADO',
        'customer_name' => 'Cliente atrasado',
        'delivery_at' => today()->subDay(),
        'currency' => 'DOP',
        'subtotal' => 900,
        'total' => 900,
        'status' => 'sent_to_whatsapp',
    ]);

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'encargos']).'?status=overdue')
        ->assertOk()
        ->assertSee('ENC-FILTRO-ATRASADO')
        ->assertDontSee('ENC-FILTRO-HOY')
        ->assertSee('Confirmar venta')
        ->assertSee('/pedidos/', false);
});

test('attributes module can rename and retire definitions without deleting the workspace entry', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $attribute = AttributeDefinition::create([
        'shop_id' => $shop->id,
        'name' => 'Marca',
        'slug' => 'marca',
        'filterable' => true,
        'required' => false,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'attributes']))
        ->assertOk()
        ->assertSee('Cambiar nombre')
        ->assertSee('Retirar');

    $this->actingAs($user)
        ->post(route('seller.shops.attributes.update', [$shop, 'attribute' => $attribute]), [
            'name' => 'Marca comercial',
            'filterable' => 1,
            'required' => 1,
            'is_active' => 1,
        ])
        ->assertRedirect();

    expect($attribute->fresh()->name)->toBe('Marca comercial')
        ->and($attribute->fresh()->required)->toBeTrue();

    $this->actingAs($user)
        ->post(route('seller.shops.attributes.update', [$shop, 'attribute' => $attribute]), [
            'name' => 'Marca comercial',
            'is_active' => 0,
        ])
        ->assertRedirect();

    expect($attribute->fresh()->is_active)->toBeFalse();
});

test('price health exposes Puntto-compatible states and real margins', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();

    $lowCost = Product::factory()->for($shop)->create(['name' => 'Vende por debajo del costo', 'price' => 100]);
    ProductInventory::create(['product_id' => $lowCost->id, 'track_inventory' => true, 'stock_quantity' => 3, 'cost_price' => 120, 'sold_quantity' => 0]);

    $lowMargin = Product::factory()->for($shop)->create(['name' => 'Margen corto', 'price' => 100]);
    ProductInventory::create(['product_id' => $lowMargin->id, 'track_inventory' => true, 'stock_quantity' => 3, 'cost_price' => 80, 'sold_quantity' => 0]);

    $noPrice = Product::factory()->for($shop)->create(['name' => 'Sin precio', 'price' => 0]);
    ProductInventory::create(['product_id' => $noPrice->id, 'track_inventory' => true, 'stock_quantity' => 3, 'cost_price' => 20, 'sold_quantity' => 0]);

    $doubtfulCost = Product::factory()->for($shop)->create(['name' => 'Costo dudoso', 'price' => 100]);
    ProductInventory::create(['product_id' => $doubtfulCost->id, 'track_inventory' => true, 'stock_quantity' => 3, 'cost_price' => 0, 'sold_quantity' => 0]);

    $suggestion = Product::factory()->for($shop)->create(['name' => 'Con sugerencia', 'price' => 100]);
    ProductInventory::create(['product_id' => $suggestion->id, 'track_inventory' => true, 'stock_quantity' => 3, 'cost_price' => 40, 'sold_quantity' => 0]);
    DB::table('product_price_rules')->insert([
        'product_id' => $suggestion->id,
        'margin_percent' => 40,
        'round_step_cents' => 100,
        'auto_increase' => false,
        'pending_price' => 120,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/shops/{$shop->public_id}/features/price_health")
        ->assertOk()
        ->assertJsonPath('module.kpis.0.label', 'Bajo costo')
        ->assertJsonPath('module.kpis.1.label', 'Margen bajo')
        ->assertJsonPath('module.kpis.2.label', 'Sin precio')
        ->assertJsonPath('module.kpis.3.label', 'Sugerencias');

    $rows = collect($response->json('module.rows'))->keyBy('primary');
    expect($rows['Vende por debajo del costo']['status'])->toBe('Bajo costo')
        ->and($rows['Margen corto']['status'])->toBe('Margen bajo')
        ->and($rows['Sin precio']['status'])->toBe('Sin precio')
        ->and($rows['Costo dudoso']['status'])->toBe('Costo dudoso')
        ->and($rows['Con sugerencia']['status'])->toBe('Sugerencia')
        ->and($rows['Margen corto']['margin_percent'])->toBe('20.0 %');

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'price_health']).'?status=low_margin')
        ->assertOk()
        ->assertSee('Margen corto')
        ->assertDontSee('Vende por debajo del costo')
        ->assertSee('Revisión de precios');

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/shops/{$shop->public_id}/features/price_health?status=cost_doubtful")
        ->assertOk()
        ->assertJsonPath('module.filters.status', 'cost_doubtful')
        ->assertJsonPath('module.rows.0.primary', 'Costo dudoso');
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
        $response = $this->actingAs($user)
            ->get(route('seller.shops.feature', [$shop, 'feature' => $feature]))
            ->assertOk()
            ->assertSee('Operativo');

        if ($feature === 'credit') {
            $response->assertSee('Por cobrar')->assertSee('Pagados');
        }
    }
});

test('authorizations shows the Puntto empty state and lets an owner decide a seller request', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $seller = User::factory()->create(['email' => 'authorization-seller@example.com']);
    $shop = Shop::factory()->for($owner)->create();
    ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'commission_type' => 'percentage',
        'commission_value' => 5,
        'is_active' => true,
        'menu_permissions' => ['sales'],
    ]);

    $this->actingAs($owner)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'authorizations']))
        ->assertOk()
        ->assertSee('Nada pendiente')
        ->assertSee('Cuando alguien pida permiso te llegará al teléfono y aparecerá aquí.');

    $this->actingAs($seller, 'sanctum')
        ->postJson("/api/v1/shops/{$shop->public_id}/authorization-requests", [
            'action' => 'Aprobar descuento especial',
            'context' => ['invoice' => 'FAC-001', 'amount' => '1500.00'],
        ])
        ->assertCreated()
        ->assertJsonPath('request.status', 'pending');

    $authorization = $shop->authorizationRequests()->firstOrFail();
    $this->actingAs($owner)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'authorizations']))
        ->assertOk()
        ->assertSee('Aprobar descuento especial')
        ->assertSee('authorization-seller@example.com');

    $this->actingAs($owner)
        ->post(route('seller.shops.authorizations.approve', [$shop, $authorization]))
        ->assertRedirect()
        ->assertSessionHas('status', 'Solicitud aprobada.');

    expect($authorization->fresh()->status)->toBe('approved')
        ->and($authorization->fresh()->decided_by)->toBe($owner->id);
});

test('authorization decisions are available to the mobile owner API', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $seller = User::factory()->create(['email' => 'mobile-authorization-seller@example.com']);
    $shop = Shop::factory()->for($owner)->create();
    ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'commission_type' => 'percentage',
        'commission_value' => 5,
        'is_active' => true,
        'menu_permissions' => ['sales'],
    ]);

    $request = $this->actingAs($seller, 'sanctum')
        ->postJson("/api/v1/shops/{$shop->public_id}/authorization-requests", ['action' => 'Aplicar descuento', 'context' => ['invoice' => 'FAC-002']])
        ->assertCreated()
        ->json('request');

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/shops/{$shop->public_id}/authorization-requests/{$request['id']}/approve")
        ->assertOk()
        ->assertJsonPath('request.status', 'approved')
        ->assertJsonPath('message', 'Solicitud aprobada.');
});

test('owner can reject an authorization request from the web module', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $seller = User::factory()->create(['email' => 'web-reject-authorization-seller@example.com']);
    $shop = Shop::factory()->for($owner)->create();
    ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'commission_type' => 'percentage',
        'commission_value' => 5,
        'is_active' => true,
        'menu_permissions' => ['sales'],
    ]);

    $this->actingAs($seller, 'sanctum')
        ->postJson("/api/v1/shops/{$shop->public_id}/authorization-requests", [
            'action' => 'Aplicar descuento especial',
            'context' => ['invoice' => 'FAC-REJECT-001'],
        ])
        ->assertCreated();

    $authorization = $shop->authorizationRequests()->firstOrFail();

    $this->actingAs($owner)
        ->post(route('seller.shops.authorizations.reject', [$shop, $authorization]))
        ->assertRedirect()
        ->assertSessionHas('status', 'Solicitud rechazada.');

    expect($authorization->fresh()->status)->toBe('rejected')
        ->and($authorization->fresh()->decided_by)->toBe($owner->id);
});

test('day close exposes the simple date-based cash reconciliation flow', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('seller.shops.feature', [$shop, 'feature' => 'day_close']))
        ->assertOk()
        ->assertSee('Cierre diario')
        ->assertSee('Efectivo contado')
        ->assertSee('Ventas cobradas')
        ->assertSee('Abonos recibidos')
        ->assertSee('Devoluciones')
        ->assertSee('Efectivo esperado del día')
        ->assertSee(route('seller.shops.daily-close.store', $shop));
});

test('day close can close an open cash session without recording an optional arqueo', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $session = app(CashRegisterService::class)->openSession($shop, $user, '500.00');

    $this->actingAs($user)
        ->post(route('seller.shops.cash.close', [$shop, $session->public_id]), [
            'counted_amount' => '',
            'notes' => 'Cierre sin arqueo',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'sin arqueo'));

    expect($session->fresh()->status)->toBe('closed')
        ->and($session->fresh()->counted_closing_amount_cents)->toBeNull()
        ->and($session->fresh()->difference_cents)->toBeNull();
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
        ->assertSee('Abrir botella')
        ->assertSee('Preparar decant')
        ->assertSee('Ver inventario compartido')
        ->assertSee('Botellas y recuperación del costo')
        ->assertSee('En recuperación')
        ->assertSee('100 ml de origen')
        ->assertSee('5 ml · 0 listos');
});

test('opening a bottle moves sealed stock to decant preparation without duplicating ml or FIFO cost', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $source = Product::factory()->for($shop)->create([
        'name' => 'Perfume para abrir',
        'sale_unit' => 'bottle',
        'volume_ml' => 100,
    ]);
    $decant = Product::factory()->for($shop)->create([
        'name' => 'Perfume preparado 5 ml',
        'sale_unit' => 'decant',
        'volume_ml' => 5,
        'inventory_source_product_id' => $source->id,
        'price' => 100,
    ]);
    ProductInventory::create([
        'product_id' => $source->id,
        'track_inventory' => true,
        'stock_quantity' => 2,
        'available_ml' => 200,
        'cost_price' => 1000,
        'sold_quantity' => 0,
        'low_stock_threshold' => 1,
    ]);
    ProductInventory::create([
        'product_id' => $decant->id,
        'track_inventory' => true,
        'stock_quantity' => 40,
        'sold_quantity' => 0,
        'low_stock_threshold' => 2,
    ]);

    $this->actingAs($user)
        ->post(route('seller.shops.inventory.open-bottle', [$shop, $source]), ['quantity' => 1])
        ->assertRedirect()
        ->assertSessionHas('status');

    $source->refresh();
    expect($source->inventory->stock_quantity)->toBe(1)
        ->and($source->inventory->opened_bottles)->toBe(1)
        ->and($source->inventory->available_ml)->toBe(200)
        ->and($decant->fresh()->inventory->stock_quantity)->toBe(40);

    $this->actingAs($user)
        ->post(route('seller.shops.inventory.sale', [$shop, $decant]), ['quantity' => 2])
        ->assertRedirect();

    $source->refresh();
    expect($source->inventory->stock_quantity)->toBe(1)
        ->and($source->inventory->opened_bottles)->toBe(1)
        ->and($source->inventory->available_ml)->toBe(190)
        ->and($decant->fresh()->inventory->stock_quantity)->toBe(38);

    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $source->id,
        'type' => 'opening',
        'quantity' => -1,
        'stock_before' => 2,
        'stock_after' => 1,
    ]);
});

test('barber shops hide and protect the decants module', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create(['business_type' => 'barbershop']);

    expect(app(SellerMenuService::class)->visibleForUser($shop, $user))->not->toContain('decants');

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
