<?php

use App\Models\CommercialQuote;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\PartnerTransaction;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\PurchaseDocument;
use App\Models\Shop;
use App\Models\User;
use App\Services\CashRegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

test('cotizaciones convierten una sola vez y respetan inventario y FIFO', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $product = Product::factory()->for($shop)->create(['price' => 250]);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 5, 'cost_price' => 100, 'sold_quantity' => 0]);
    app(CashRegisterService::class)->openSession($shop, $user, '0.00');

    $response = $this->actingAs($user)->post(route('seller.shops.quotes.store', $shop), [
        'customer_name' => 'Cliente cotización',
        'items' => [['product_id' => $product->public_id, 'quantity' => 2, 'unit_price' => '250.00']],
    ]);
    $response->assertSessionHasNoErrors();
    $quote = CommercialQuote::query()->where('shop_id', $shop->id)->sole();
    expect($quote->status)->toBe('draft')->and($quote->total)->toBe('500.00');

    $this->actingAs($user)->post(route('seller.shops.quotes.convert', [$shop, 'quote' => $quote->public_id]))
        ->assertRedirect(route('seller.shops.feature', [$shop, 'feature' => 'quotes']))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', "Cotización {$quote->quote_number} convertida en venta.");

    $quote->refresh();
    expect($quote->status)->toBe('converted')
        ->and($quote->converted_invoice_id)->not->toBeNull()
        ->and(Invoice::where('shop_id', $shop->id)->count())->toBe(1)
        ->and(InventoryMovement::where('product_id', $product->id)->where('type', 'sale')->count())->toBe(1)
        ->and((int) $product->inventory()->first()->stock_quantity)->toBe(3);

    $this->actingAs($user)->post(route('seller.shops.quotes.convert', [$shop, 'quote' => $quote->public_id]))
        ->assertRedirect(route('seller.shops.feature', [$shop, 'feature' => 'quotes']))
        ->assertSessionHasErrors('quote');
    expect(Invoice::where('shop_id', $shop->id)->count())->toBe(1);
});

test('la conversión API de una cotización conserva los centavos del precio snapshot', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $product = Product::factory()->for($shop)->create(['price' => '10.29']);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 4,
        'cost_price' => '5.03',
        'sold_quantity' => 0,
    ]);
    app(CashRegisterService::class)->openSession($shop, $user, '0.00');
    $token = $user->createToken('qa-quote', ['pos:write'])->plainTextToken;

    $quoteResponse = $this->withToken($token)->postJson(
        "/api/v1/shops/{$shop->public_id}/quotes",
        [
            'customer_name' => 'Cliente centavos',
            'items' => [['product_id' => $product->public_id, 'quantity' => 2, 'unit_price' => '10.29']],
        ],
    )->assertCreated()->assertJsonPath('quote.total', '20.58');

    $quote = CommercialQuote::query()->where('shop_id', $shop->id)->sole();
    $this->withToken($token)->postJson(
        "/api/v1/shops/{$shop->public_id}/quotes/{$quote->public_id}/convert",
    )->assertOk()->assertJsonPath('message', 'Cotización convertida en venta.');

    $invoice = Invoice::query()->where('shop_id', $shop->id)->sole();
    expect((string) $invoice->total)->toBe('20.58')
        ->and((string) $invoice->items()->sole()->unit_price)->toBe('10.29')
        ->and((int) $product->fresh()->inventory->stock_quantity)->toBe(2)
        ->and($quote->fresh()->status)->toBe('converted');
});

test('web y API bloquean cotizaciones vencidas o canceladas sin mutar la venta', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $product = Product::factory()->for($shop)->create(['price' => '250.00']);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 8,
        'cost_price' => '100.00',
        'sold_quantity' => 0,
    ]);
    app(CashRegisterService::class)->openSession($shop, $user, '0.00');

    $createQuote = function (string $status, $validUntil) use ($shop, $user, $product): CommercialQuote {
        $quote = CommercialQuote::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'quote_number' => 'COT-QA-'.strtoupper($status).'-'.fake()->unique()->numerify('###'),
            'status' => $status,
            'currency' => 'DOP',
            'subtotal' => '250.00',
            'discount' => '0.00',
            'tax' => '0.00',
            'total' => '250.00',
            'valid_until' => $validUntil,
        ]);
        $quote->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => '250.00',
            'line_total' => '250.00',
        ]);

        return $quote;
    };

    $expiredWeb = $createQuote('draft', today()->subDay());
    $cancelledWeb = $createQuote('cancelled', today()->addDay());
    $expiredApi = $createQuote('draft', today()->subDay());
    $cancelledApi = $createQuote('cancelled', today()->addDay());

    $this->actingAs($user)
        ->post(route('seller.shops.quotes.convert', [$shop, 'quote' => $expiredWeb->public_id]))
        ->assertRedirect(route('seller.shops.feature', [$shop, 'feature' => 'quotes']))
        ->assertSessionHasErrors('quote');
    $this->actingAs($user)
        ->post(route('seller.shops.quotes.convert', [$shop, 'quote' => $cancelledWeb->public_id]))
        ->assertRedirect(route('seller.shops.feature', [$shop, 'feature' => 'quotes']))
        ->assertSessionHasErrors('quote');

    $token = $user->createToken('qa-expired-quotes', ['pos:write'])->plainTextToken;
    $this->withToken($token)
        ->postJson("/api/v1/shops/{$shop->public_id}/quotes/{$expiredApi->public_id}/convert")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Una cotización vencida no puede convertirse en venta.');
    $this->withToken($token)
        ->postJson("/api/v1/shops/{$shop->public_id}/quotes/{$cancelledApi->public_id}/convert")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Una cotización cancelada no puede convertirse en venta.');

    expect(Invoice::where('shop_id', $shop->id)->count())->toBe(0)
        ->and(InventoryMovement::where('product_id', $product->id)->where('type', 'sale')->count())->toBe(0)
        ->and((int) $product->fresh()->inventory->stock_quantity)->toBe(8);
});

test('el detalle de una cotización cancelada no ofrece convertirla ni la muestra vigente', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $quote = CommercialQuote::create([
        'shop_id' => $shop->id,
        'user_id' => $user->id,
        'quote_number' => 'COT-QA-CANCELLED-001',
        'status' => 'cancelled',
        'currency' => 'DOP',
        'subtotal' => '250.00',
        'discount' => '0.00',
        'tax' => '0.00',
        'total' => '250.00',
        'valid_until' => today()->addDay(),
    ]);

    $this->actingAs($user)
        ->get(route('seller.shops.quotes.show', [$shop, 'quote' => $quote->public_id]))
        ->assertOk()
        ->assertSee('Cancelada')
        ->assertDontSee('Convertir en venta');
});

test('recepción de compra crea suplidor, documento y lote separado con su costo', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $product = Product::factory()->for($shop)->create(['price' => 400]);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 2, 'cost_price' => 90, 'sold_quantity' => 0]);

    $this->actingAs($user)->post(route('seller.shops.suppliers.store', $shop), ['name' => 'Suplidor FIFO', 'invoice_currency' => 'USD'])
        ->assertSessionHasNoErrors();
    $supplier = $shop->suppliers()->sole();
    expect($supplier->invoice_currency)->toBe('USD');

    $this->actingAs($user)->post(route('seller.shops.purchases.store', $shop), [
        'type' => 'purchase_invoice',
        'document_number' => 'FAC-COMPRA-001',
        'supplier_id' => $supplier->public_id,
        'product_id' => $product->public_id,
        'quantity' => 4,
        'unit_cost' => '125.5',
    ])->assertSessionHasNoErrors();

    $document = PurchaseDocument::query()->where('shop_id', $shop->id)->sole();
    expect($document->total)->toBe('502.00')
        ->and($document->items()->count())->toBe(1)
        ->and($document->items()->sole()->unit_cost)->toBe('125.50')
        ->and((int) $product->inventory()->first()->stock_quantity)->toBe(6)
        ->and($product->inventoryLots()->where('received_cost_cents', 50200)->exists())->toBeTrue();
});

test('la web registra una carga logística sin inventario y evita recibirla como compra', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $supplier = $shop->suppliers()->create(['name' => 'Courier web', 'invoice_currency' => 'USD', 'is_active' => true]);

    $this->actingAs($user)->post(route('seller.shops.purchases.store', $shop), [
        'type' => 'load',
        'document_number' => 'Miami web octubre',
        'supplier_id' => $supplier->public_id,
        'currency' => 'USD',
        'exchange_rate' => '59.50',
        'carrier' => 'Courier web',
        'tracking_number' => 'BL-WEB-001',
        'expected_at' => '2026-10-30',
        'shipping_pounds' => '12.5',
        'freight_amount' => '45.00',
        'customs_amount' => '10.00',
        'mode' => 'received',
        'notes' => 'Sin productos hasta asociar contenedores',
    ])->assertSessionHasNoErrors();

    $load = PurchaseDocument::where('shop_id', $shop->id)->sole();
    expect($load->type)->toBe('load')
        ->and($load->status)->toBe('draft')
        ->and($load->currency)->toBe('USD')
        ->and((string) $load->exchange_rate)->toBe('59.500000')
        ->and($load->items()->count())->toBe(0);

    $this->actingAs($user)->post(route('seller.shops.purchases.receive', [$shop, 'document' => $load->public_id]))
        ->assertRedirect(route('seller.shops.feature', [$shop, 'feature' => 'loads']))
        ->assertSessionHasErrors('purchase');
    expect($load->fresh()->status)->toBe('draft');
});

test('registrar una deuda de suplidor no crea inventario ni lotes', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $product = Product::factory()->for($shop)->create(['price' => 400]);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 2, 'cost_price' => 90, 'sold_quantity' => 0]);
    $supplier = $shop->suppliers()->create(['name' => 'Suplidor con deuda', 'invoice_currency' => 'USD', 'is_active' => true]);

    $this->actingAs($user)->post(route('seller.shops.purchases.debts.store', $shop), [
        'supplier_id' => $supplier->public_id,
        'amount' => '275.40',
        'currency' => 'USD',
        'invoice_date' => '2026-10-01',
        'due_at' => '2026-11-01',
        'document_number' => 'FAC-DEUDA-001',
        'notes' => 'Saldo anterior',
    ])->assertSessionHasNoErrors();

    $document = PurchaseDocument::where('shop_id', $shop->id)->sole();
    expect($document->status)->toBe('debt')
        ->and($document->payment_status)->toBe('pending')
        ->and($document->currency)->toBe('USD')
        ->and($document->total)->toBe('275.40')
        ->and($document->items()->count())->toBe(0)
        ->and((int) $product->inventory()->first()->fresh()->stock_quantity)->toBe(2)
        ->and($product->inventoryLots()->count())->toBe(0);
});

test('las compras admiten varias líneas en borrador y solo reciben inventario una vez', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $first = Product::factory()->for($shop)->create(['price' => 250]);
    $second = Product::factory()->for($shop)->create(['price' => 400]);
    ProductInventory::create(['product_id' => $first->id, 'track_inventory' => true, 'stock_quantity' => 3, 'cost_price' => 100, 'sold_quantity' => 0]);
    ProductInventory::create(['product_id' => $second->id, 'track_inventory' => true, 'stock_quantity' => 5, 'cost_price' => 200, 'sold_quantity' => 0]);

    $this->actingAs($user)->post(route('seller.shops.purchases.store', $shop), [
        'type' => 'container',
        'document_number' => 'CONT-DRAFT-001',
        'mode' => 'draft',
        'items' => [
            ['product_id' => $first->public_id, 'quantity' => 4, 'unit_cost' => '125.50'],
            ['product_id' => $second->public_id, 'quantity' => 2, 'unit_cost' => '210.00'],
        ],
    ])->assertSessionHasNoErrors();

    $document = PurchaseDocument::query()->where('shop_id', $shop->id)->sole();
    expect($document->status)->toBe('draft')
        ->and($document->items()->count())->toBe(2)
        ->and((int) $first->inventory()->first()->stock_quantity)->toBe(3)
        ->and((int) $second->inventory()->first()->stock_quantity)->toBe(5)
        ->and(InventoryMovement::where('product_id', $first->id)->count())->toBe(0);

    $this->actingAs($user)->post(route('seller.shops.purchases.receive', [$shop, 'document' => $document->public_id]))
        ->assertRedirect(route('seller.shops.feature', [$shop, 'feature' => 'containers']))
        ->assertSessionHasNoErrors();

    $document->refresh();
    expect($document->status)->toBe('received')
        ->and($document->items()->whereNotNull('inventory_movement_id')->count())->toBe(2)
        ->and((int) $first->inventory()->first()->fresh()->stock_quantity)->toBe(7)
        ->and((int) $second->inventory()->first()->fresh()->stock_quantity)->toBe(7);

    $this->actingAs($user)->post(route('seller.shops.purchases.receive', [$shop, 'document' => $document->public_id]))
        ->assertRedirect(route('seller.shops.feature', [$shop, 'feature' => 'containers']))
        ->assertSessionHasErrors('purchase');
    expect(InventoryMovement::where('product_id', $first->id)->count())->toBe(1);
});

test('leer factura prepara líneas emparejadas sin crear documentos ni tocar inventario', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $product = Product::factory()->for($shop)->create([
        'name' => 'Perfume Azul',
        'product_code' => 'SKU-AZUL',
        'price' => 2500,
    ]);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 4, 'cost_price' => 900, 'sold_quantity' => 0]);

    $file = UploadedFile::fake()->createWithContent(
        'factura-suplidor.csv',
        "Producto;SKU;Cantidad;Costo unitario\nPerfume Azul;SKU-AZUL;3;950,50\nProducto nuevo;SKU-NUEVO;2;1200\n",
    );

    $response = $this->actingAs($user)->postJson(route('seller.shops.purchases.preview', $shop), ['file' => $file]);

    $response->assertOk()
        ->assertJsonPath('counts.total', 2)
        ->assertJsonPath('counts.matched', 1)
        ->assertJsonPath('rows.0.product_id', $product->public_id)
        ->assertJsonPath('rows.0.quantity', 3)
        ->assertJsonPath('rows.0.unit_cost', '950.50')
        ->assertJsonPath('rows.0.match_type', 'product_code')
        ->assertJsonPath('rows.1.product_id', null)
        ->assertJsonPath('rows.1.valid', false);

    expect(PurchaseDocument::where('shop_id', $shop->id)->count())->toBe(0)
        ->and(InventoryMovement::where('product_id', $product->id)->count())->toBe(0)
        ->and((int) $product->inventory()->first()->fresh()->stock_quantity)->toBe(4);
});

test('leer una foto de factura no inventa datos y devuelve una revisión clara', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('seller.shops.purchases.preview', $shop), ['file' => UploadedFile::fake()->image('factura.jpg')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);

    expect(PurchaseDocument::where('shop_id', $shop->id)->count())->toBe(0);
});

test('movimiento de socio exige caja y queda enlazado al movimiento contable', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $this->actingAs($user)->post(route('seller.shops.partners.store', $shop), ['name' => 'Socio Uno', 'ownership_percent' => 50])
        ->assertSessionHasNoErrors();
    $partner = $shop->partners()->sole();

    $this->actingAs($user)->post(route('seller.shops.partners.transactions.store', [$shop, 'partner' => $partner->public_id]), ['type' => 'contribution', 'amount' => '1000'])
        ->assertSessionHasErrors('partner');

    app(CashRegisterService::class)->openSession($shop, $user, '0.00');
    $this->actingAs($user)->post(route('seller.shops.partners.transactions.store', [$shop, 'partner' => $partner->public_id]), ['type' => 'contribution', 'amount' => '1000', 'notes' => 'Capital inicial'])
        ->assertSessionHasNoErrors();

    $transaction = PartnerTransaction::query()->where('partner_id', $partner->id)->sole();
    expect($transaction->type)->toBe('contribution')
        ->and($transaction->cash_movement_id)->not->toBeNull()
        ->and((float) $shop->cashMovements()->where('type', 'owner_contribution')->sum('amount'))->toBe(1000.0);
});

test('los dominios comerciales no aceptan productos de otra tienda', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $other = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $otherProduct = Product::factory()->for(Shop::factory()->for($other)->create())->create();

    $this->actingAs($owner)->post(route('seller.shops.quotes.store', $shop), [
        'items' => [['product_id' => $otherProduct->public_id, 'quantity' => 1]],
    ])->assertSessionHasErrors('items');

    expect(CommercialQuote::where('shop_id', $shop->id)->count())->toBe(0);
});
