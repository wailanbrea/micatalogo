<?php

use App\Enums\UserPlan;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function businessFixture(): array
{
    $user = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create(['shop_id' => $shop->id, 'price' => 300, 'sale_unit' => 'unit']);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 5, 'cost_price' => 100, 'sold_quantity' => 0, 'low_stock_threshold' => 1]);

    return [$user, $shop, $product];
}

test('API catalog sends active offer price and discounted taxed sale has matching totals', function () {
    [$user, $shop, $product] = businessFixture();
    $product->update(['sale_price' => 250]);
    $token = $user->createToken('test', ['catalog:read', 'pos:write'])->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/shops/'.$shop->public_id.'/catalog')->assertOk()->assertJsonPath('products.0.price', '250.00');
    $this->withToken($token)->postJson('/api/v1/shops/'.$shop->public_id.'/pos-sales', [
        'client_sale_uuid' => (string) Str::uuid(), 'payment_status' => 'paid', 'discount' => '20.00', 'tax' => '5.00',
        'items' => [['product_id' => $product->public_id, 'quantity' => 2, 'unit_price' => '250.00', 'discount' => '10.00', 'tax' => '15.00']],
    ])->assertCreated()->assertJsonPath('total', '490.00');
    expect((float) Invoice::first()->items->first()->line_total)->toBe(505.0);
    expect($product->fresh()->inventory->gross_profit)->toBe(270.0)
        ->and((int) Invoice::first()->items->first()->general_discount_cents)->toBe(2000);
});

test('dashboard renders actual costs and order confirmation is idempotent', function () {
    [$user, $shop, $product] = businessFixture();
    $order = $shop->orders()->create(['order_number' => 'MC-TEST', 'currency' => 'DOP', 'subtotal' => 300, 'total' => 300, 'status' => 'sent_to_whatsapp']);
    $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 1, 'unit_price' => 300, 'line_total' => 300]);
    $url = route('seller.shops.orders.confirm', [$shop, $order]);
    $this->actingAs($user)->post($url)->assertRedirect();
    $this->actingAs($user)->post($url)->assertRedirect();
    expect(Invoice::count())->toBe(1)->and($product->fresh()->inventory->stock_quantity)->toBe(4);
    $this->actingAs($user)->get(route('seller.shops.business', $shop))->assertOk()->assertSee('Ventas y ganancia por producto')->assertSee($product->name);
});

test('mobile order confirmation reuses the accounting transaction and is idempotent', function () {
    [$user, $shop, $product] = businessFixture();
    $order = $shop->orders()->create([
        'order_number' => 'MC-MOBILE-ORDER',
        'currency' => 'DOP',
        'subtotal' => 300,
        'total' => 300,
        'status' => 'sent_to_whatsapp',
    ]);
    $order->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => 300,
        'line_total' => 300,
    ]);
    $token = $user->createToken('BSPOS', ['pos:write'])->plainTextToken;
    $url = "/api/v1/shops/{$shop->public_id}/orders/{$order->id}/confirm";

    $this->withToken($token)->postJson($url, [
        'payment_kind' => 'paid',
        'payment_method' => 'cash',
    ])->assertOk()
        ->assertJsonPath('order_number', 'MC-MOBILE-ORDER')
        ->assertJsonPath('invoice_number', fn (?string $value) => is_string($value) && $value !== '');

    $invoiceCount = Invoice::count();
    $invoiceNumber = Invoice::query()->sole()->invoice_number;

    $this->withToken($token)->postJson($url, [
        'payment_kind' => 'paid',
        'payment_method' => 'cash',
    ])->assertOk()->assertJsonPath('invoice_number', $invoiceNumber);

    expect(Invoice::count())->toBe($invoiceCount)
        ->and($product->fresh()->inventory->stock_quantity)->toBe(4)
        ->and($order->fresh()->status)->toBe('confirmed');
});

test('whatsapp order opens a signed payment screen and records a credit invoice', function () {
    [$user, $shop, $product] = businessFixture();
    $customer = Customer::create([
        'shop_id' => $shop->id,
        'name' => 'Cliente WhatsApp',
        'credit_limit' => 1000,
        'balance' => 0,
        'is_active' => true,
    ]);
    $order = $shop->orders()->create([
        'order_number' => 'MC-CREDIT',
        'currency' => 'DOP',
        'subtotal' => 300,
        'total' => 300,
        'status' => 'sent_to_whatsapp',
        'customer_name' => 'Cliente WhatsApp',
    ]);
    $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 1, 'unit_price' => 300, 'line_total' => 300]);

    $signedUrl = URL::temporarySignedRoute('seller.shops.orders.confirm.show', now()->addHour(), [$shop, $order]);
    $this->actingAs($user)->get($signedUrl)
        ->assertOk()
        ->assertSee('Registrar cobro')
        ->assertSee($order->order_number);

    $this->actingAs($user)->post(route('seller.shops.orders.confirm', [$shop, $order]), [
        'payment_kind' => 'credit',
        'customer_id' => $customer->id,
    ])->assertRedirect();

    $invoice = Invoice::findOrFail($order->fresh()->invoice_id);
    $this->actingAs($user)->get($signedUrl)
        ->assertOk()
        ->assertSee('Ver / descargar factura PDF')
        ->assertSee($invoice->invoice_number);
    expect($invoice->status)->toBe('pending')
        ->and($invoice->customer_id)->toBe($customer->id)
        ->and($invoice->payments()->count())->toBe(0)
        ->and((float) $customer->fresh()->balance)->toBe(300.0)
        ->and($product->fresh()->inventory->stock_quantity)->toBe(4);
});

test('whatsapp order confirmation records a mixed payment method and exact credit', function () {
    [$user, $shop, $product] = businessFixture();
    $customer = Customer::create(['shop_id' => $shop->id, 'name' => 'Cliente Mixto', 'credit_limit' => 1000, 'is_active' => true]);
    $order = $shop->orders()->create(['order_number' => 'MC-MIXED', 'currency' => 'DOP', 'subtotal' => 300, 'total' => 300, 'status' => 'sent_to_whatsapp']);
    $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 1, 'unit_price' => 300, 'line_total' => 300]);

    $this->actingAs($user)->post(route('seller.shops.orders.confirm', [$shop, $order]), [
        'payment_kind' => 'mixed',
        'payment_method' => 'card',
        'credit_amount' => 100,
        'customer_id' => $customer->id,
        'reference' => 'POS-4821',
    ])->assertRedirect();

    $invoice = Invoice::findOrFail($order->fresh()->invoice_id);
    expect($invoice->status)->toBe('partial')
        ->and((float) $invoice->payments()->sum('amount'))->toBe(200.0)
        ->and($invoice->payments()->first()->payment_method)->toBe('card')
        ->and($invoice->payments()->first()->reference)->toBe('POS-4821')
        ->and((float) $customer->fresh()->balance)->toBe(100.0);
});

test('price proposal requires owner Pro and rejects stale approval', function () {
    [$user, $shop, $product] = businessFixture();
    $url = route('seller.shops.pricing.rule', [$shop, $product]);
    $this->actingAs($user)->post($url, ['margin_percent' => 40, 'round_step' => 10, 'auto_increase' => 1])->assertRedirect();
    DB::table('product_price_rules')->update(['pending_price' => 200]);
    $this->actingAs($user)->post(route('seller.shops.pricing.approve', [$shop, $product]), ['expected_price' => 150])->assertSessionHasErrors('pricing');
    expect((float) $product->fresh()->price)->toBe(300.0);
    $this->actingAs($user)->post(route('seller.shops.pricing.approve', [$shop, $product]), ['expected_price' => 200])->assertRedirect();
    expect((float) $product->fresh()->price)->toBe(200.0);
    $user->update(['plan' => UserPlan::Free]);
    $this->actingAs($user)->post($url, ['margin_percent' => 40, 'round_step' => 10])->assertSessionHasErrors('pricing');
});

test('ads are shown only inside free catalogs', function () {
    [$user, $shop, $product] = businessFixture();
    config(['catalog.ads.enabled' => true]);
    $this->get(route('home'))->assertOk()->assertDontSee('aria-label="Espacio publicitario"', false);
    $this->get(route('shops.show', $shop))->assertOk()->assertDontSee('aria-label="Espacio publicitario"', false);
    $user->update(['plan' => UserPlan::Free]);
    $this->get(route('shops.show', $shop))->assertOk()->assertSee('aria-label="Espacio publicitario"', false);
});

test('business and earnings dashboard renders styled kpis and sections', function () {
    [$user, $shop, $product] = businessFixture();

    $response = $this->actingAs($user)->get(route('seller.shops.business', $shop));

    $response->assertOk()
        ->assertSee('Control del negocio')
        ->assertSee('Ventas hoy')
        ->assertSee('Ventas del período')
        ->assertSee('Por cobrar')
        ->assertSee('Stock bajo')
        ->assertSee('Agotados')
        ->assertSee('Ventas y ganancia por producto')
        ->assertSee('Lotes de inventario')
        ->assertSee('Reglas de precio · Pro')
        ->assertSee('Pedidos pendientes de confirmar');
});

test('business dashboard links pending orders to the existing orders module', function () {
    [$user, $shop, $product] = businessFixture();
    $order = $shop->orders()->create([
        'order_number' => 'MC-PENDING',
        'currency' => 'DOP',
        'subtotal' => 300,
        'total' => 300,
        'status' => 'sent_to_whatsapp',
    ]);
    $order->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => 300,
        'line_total' => 300,
    ]);

    $this->actingAs($user)
        ->get(route('seller.shops.business', $shop))
        ->assertOk()
        ->assertSee(route('seller.shops.feature', [$shop, 'orders'], false), false);
});
