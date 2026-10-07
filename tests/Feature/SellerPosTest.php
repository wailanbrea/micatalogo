<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function webPosProductPayload(Product $product, int $quantity = 1, ?string $unitPrice = null): array
{
    return [
        'product_id' => $product->public_id,
        'quantity' => $quantity,
        'unit_price' => $unitPrice ?? number_format($product->currentPrice(), 2, '.', ''),
        'expected_sale_unit' => $product->sale_unit ?: 'unit',
        'expected_volume_ml' => $product->volume_ml,
        'expected_source_product_id' => $product->sourceProduct?->public_id,
    ];
}

test('seller can open the web POS and register a paid sale', function () {
    $user = User::factory()->create(['plan' => 'premium']);
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create(['shop_id' => $shop->id, 'price' => 275]);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 8]);

    $this->actingAs($user)
        ->get(route('seller.shops.pos', $shop))
        ->assertOk()
        ->assertSee('Punto de venta')
        ->assertSee('web-pos-checkout-title')
        ->assertSee('Detalle de la venta')
        ->assertSee('Recibido')
        ->assertSee('payment_note')
        ->assertSee('cashChange')
        ->assertSee($product->name);

    $this->actingAs($user)
        ->post(route('seller.shops.pos.store', $shop), [
            'client_sale_uuid' => (string) Str::uuid(),
            'payment_status' => 'paid',
            'sale_mode' => 'retail',
            'credit_amount' => '0.00',
            'payments' => [['method' => 'cash', 'amount' => '550.00']],
            'items' => [webPosProductPayload($product, 2)],
        ])
        ->assertRedirect(route('seller.shops.pos', $shop))
        ->assertSessionHas('status');

    $invoice = Invoice::query()->with('items')->sole();
    expect($invoice->channel)->toBe('pos')
        ->and($invoice->status)->toBe('paid')
        ->and((float) $invoice->total)->toBe(550.0)
        ->and($product->fresh()->inventory->stock_quantity)->toBe(6);
});

test('web POS sells a service without decrementing physical inventory and captures input cost', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->create(['user_id' => $user->id, 'business_type' => 'barbershop']);
    $service = Product::factory()->create([
        'shop_id' => $shop->id,
        'name' => 'Corte clásico',
        'price' => 800,
        'sale_unit' => 'service',
        'availability_status' => 'available',
    ]);
    $inventory = ProductInventory::create([
        'product_id' => $service->id,
        'track_inventory' => false,
        'stock_quantity' => 0,
        'cost_price' => 120,
        'sold_quantity' => 0,
        'low_stock_threshold' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('seller.shops.pos', $shop))
        ->assertOk()
        ->assertSee('Corte', false)
        ->assertSee('Servicio · sin inventario');

    $this->actingAs($user)
        ->post(route('seller.shops.pos.store', $shop), [
            'client_sale_uuid' => (string) Str::uuid(),
            'payment_status' => 'paid',
            'sale_mode' => 'retail',
            'credit_amount' => '0.00',
            'payments' => [['method' => 'cash', 'amount' => '1600.00']],
            'items' => [webPosProductPayload($service, 2)],
        ])
        ->assertRedirect(route('seller.shops.pos', $shop));

    expect($inventory->fresh()->stock_quantity)->toBe(0)
        ->and($inventory->fresh()->sold_quantity)->toBe(2)
        ->and((float) Invoice::query()->sole()->total)->toBe(1600.0)
        ->and(Invoice::query()->sole()->items()->sole()->total_cost_cents)->toBe(24000);
});

test('web POS credit checkout can omit a zero-value payment row', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create(['shop_id' => $shop->id, 'price' => 450]);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 4]);
    $customer = Customer::create(['shop_id' => $shop->id, 'name' => 'Cliente Crédito Web', 'credit_limit' => 1000]);

    $this->actingAs($user)
        ->post(route('seller.shops.pos.store', $shop), [
            'client_sale_uuid' => (string) Str::uuid(),
            'payment_status' => 'pending',
            'sale_mode' => 'retail',
            'credit_amount' => '450.00',
            'customer_id' => $customer->public_id,
            'items' => [webPosProductPayload($product)],
        ])
        ->assertRedirect(route('seller.shops.pos', $shop));

    expect(Invoice::query()->sole()->status)->toBe('pending')
        ->and((float) $customer->fresh()->balance)->toBe(450.0)
        ->and($product->fresh()->inventory->stock_quantity)->toBe(3);
});

test('an appliance shop presents appliance terminology and completes a POS sale', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->create([
        'user_id' => $user->id,
        'business_type' => 'appliance_store',
    ]);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'name' => 'Nevera Samsung Frost Free',
        'brand' => 'Samsung',
        'product_code' => 'NEV-SAM-001',
        'price' => 38900,
    ]);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 3]);

    $this->actingAs($user)
        ->get(route('seller.shops.pos', $shop))
        ->assertOk()
        ->assertSee('Buscar electrodoméstico, marca, modelo o código')
        ->assertSee('Nevera Samsung Frost Free');

    $this->actingAs($user)
        ->post(route('seller.shops.pos.store', $shop), [
            'client_sale_uuid' => (string) Str::uuid(),
            'payment_status' => 'paid',
            'sale_mode' => 'retail',
            'credit_amount' => '0.00',
            'payments' => [['method' => 'cash', 'amount' => '38900.00']],
            'items' => [webPosProductPayload($product)],
        ])
        ->assertRedirect(route('seller.shops.pos', $shop))
        ->assertSessionHas('status');

    expect($product->fresh()->inventory->stock_quantity)->toBe(2)
        ->and(Invoice::query()->sole()->total)->toBe('38900.00');
});

test('web POS supports wholesale mixed payment and customer credit', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create(['shop_id' => $shop->id, 'price' => 300, 'wholesale_price' => 220]);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 8]);
    $customer = Customer::create(['shop_id' => $shop->id, 'name' => 'Cliente Web', 'credit_limit' => 1000]);

    $this->actingAs($user)
        ->post(route('seller.shops.pos.store', $shop), [
            'client_sale_uuid' => (string) Str::uuid(),
            'payment_status' => 'paid',
            'sale_mode' => 'wholesale',
            'credit_amount' => '240.00',
            'customer_id' => $customer->public_id,
            'payments' => [['method' => 'card', 'amount' => '200.00']],
            'items' => [webPosProductPayload($product, 2, '220.00')],
        ])
        ->assertRedirect(route('seller.shops.pos', $shop));

    $invoice = Invoice::query()->sole();
    expect($invoice->sale_mode)->toBe('wholesale')
        ->and($invoice->status)->toBe('partial')
        ->and((float) $invoice->total)->toBe(440.0)
        ->and((float) $customer->fresh()->balance)->toBe(240.0)
        ->and($product->fresh()->inventory->stock_quantity)->toBe(6);
});

test('web POS sells a decant and reports when its source bottle is recovered', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $source = Product::factory()->create([
        'shop_id' => $shop->id,
        'name' => 'Perfume base 100 ml',
        'price' => 1200,
        'sale_unit' => 'bottle',
        'volume_ml' => 100,
    ]);
    ProductInventory::create([
        'product_id' => $source->id,
        'track_inventory' => true,
        'stock_quantity' => 1,
        'available_ml' => 100,
        'cost_price' => 300,
    ]);
    $decant = Product::factory()->create([
        'shop_id' => $shop->id,
        'name' => 'Perfume base · Decant 5 ml',
        'price' => 50,
        'sale_unit' => 'decant',
        'volume_ml' => 5,
        'inventory_source_product_id' => $source->id,
    ]);
    ProductInventory::create([
        'product_id' => $decant->id,
        'track_inventory' => true,
        'stock_quantity' => 20,
    ]);

    $this->actingAs($user)
        ->get(route('seller.shops.pos', $shop))
        ->assertOk()
        ->assertSee('Nueva venta')
        ->assertSee('Productos')
        ->assertSee('Decants')
        ->assertSee('is_decant');

    $source->load('sourceProduct');
    $decant->load('sourceProduct');
    $this->actingAs($user)
        ->post(route('seller.shops.pos.store', $shop), [
            'client_sale_uuid' => (string) Str::uuid(),
            'payment_status' => 'paid',
            'sale_mode' => 'retail',
            'credit_amount' => '0.00',
            'payments' => [['method' => 'cash', 'amount' => '300.00']],
            'items' => [webPosProductPayload($decant, 6)],
        ])
        ->assertRedirect(route('seller.shops.pos', $shop))
        ->assertSessionHas('bottle_recovery', function (array $recovery): bool {
            return count($recovery) === 1
                && $recovery[0]['covered'] === true
                && $recovery[0]['just_covered'] === true;
        });

    expect($source->fresh()->inventory->available_ml)->toBe(70)
        ->and($decant->fresh()->inventory->stock_quantity)->toBe(14)
        ->and((float) Invoice::query()->sole()->total)->toBe(300.0);
});
