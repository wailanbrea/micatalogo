<?php

use App\Models\CommercialQuote;
use App\Models\Invoice;
use App\Models\InventoryMovement;
use App\Models\PartnerTransaction;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\PurchaseDocument;
use App\Models\Shop;
use App\Models\User;
use App\Services\CashRegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        ->assertSessionHasNoErrors();

    $quote->refresh();
    expect($quote->status)->toBe('converted')
        ->and($quote->converted_invoice_id)->not->toBeNull()
        ->and(Invoice::where('shop_id', $shop->id)->count())->toBe(1)
        ->and(InventoryMovement::where('product_id', $product->id)->where('type', 'sale')->count())->toBe(1)
        ->and((int) $product->inventory()->first()->stock_quantity)->toBe(3);

    $this->actingAs($user)->post(route('seller.shops.quotes.convert', [$shop, 'quote' => $quote->public_id]))
        ->assertSessionHasErrors('quote');
    expect(Invoice::where('shop_id', $shop->id)->count())->toBe(1);
});

test('recepción de compra crea suplidor, documento y lote separado con su costo', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $product = Product::factory()->for($shop)->create(['price' => 400]);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 2, 'cost_price' => 90, 'sold_quantity' => 0]);

    $this->actingAs($user)->post(route('seller.shops.suppliers.store', $shop), ['name' => 'Suplidor FIFO'])
        ->assertSessionHasNoErrors();
    $supplier = $shop->suppliers()->sole();

    $this->actingAs($user)->post(route('seller.shops.purchases.store', $shop), [
        'type' => 'purchase_invoice',
        'document_number' => 'FAC-COMPRA-001',
        'supplier_id' => $supplier->public_id,
        'product_id' => $product->public_id,
        'quantity' => 4,
        'unit_cost' => '125.50',
    ])->assertSessionHasNoErrors();

    $document = PurchaseDocument::query()->where('shop_id', $shop->id)->sole();
    expect($document->total)->toBe('502.00')
        ->and($document->items()->count())->toBe(1)
        ->and((int) $product->inventory()->first()->stock_quantity)->toBe(6)
        ->and($product->inventoryLots()->where('received_cost_cents', 50200)->exists())->toBeTrue();
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
