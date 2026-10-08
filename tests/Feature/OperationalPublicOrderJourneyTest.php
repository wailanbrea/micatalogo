<?php

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Enums\UserPlan;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Services\CashRegisterService;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.turnstile.enabled', false);
});

test('E2E-01 registro tienda vitrina pedido whatsapp confirmacion factura stock y cierre', function () {
    $owner = app(RegistrationService::class)->create([
        'name' => 'Owner E2E-01',
        'email' => 'e2e-01@example.com',
        'business_name' => 'Tienda E2E-01',
        'business_type' => 'general_retail',
        'whatsapp_country_code' => '1809',
        'whatsapp_number' => '8295550101',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms_accepted' => '1',
    ]);
    $owner->forceFill(['email_verified_at' => now()])->save();
    $shop = $owner->shops()->sole();
    $product = Product::factory()->for($shop)->create([
        'name' => 'Producto público E2E-01',
        'price' => '300.00',
        'moderation_status' => ProductModerationStatus::Active,
        'availability_status' => ProductAvailabilityStatus::Available,
    ]);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 3,
        'sold_quantity' => 0,
        'cost_price' => '120.00',
    ]);

    expect($owner->plan)->toBe(UserPlan::Free)
        ->and($shop->business_type)->toBe('general_retail');

    $this->get(route('shops.show', $shop))
        ->assertOk()
        ->assertSee($product->name);
    $this->get(route('products.show', [$shop, $product]))
        ->assertOk()
        ->assertSee($product->name);

    $cash = app(CashRegisterService::class);
    $session = $cash->openSession($shop, $owner, '0.00', 'E2E-01');

    $created = $this->postJson(route('orders.store', $shop), [
        'items' => [['id' => $product->public_id, 'quantity' => 2]],
        'customer_name' => 'Cliente E2E-01',
        'delivery_type' => 'pickup',
        'notes' => 'Pedido de prueba aislado',
    ])->assertCreated()
        ->assertJsonPath('order_number', fn (string $value) => str_starts_with($value, 'MC-'))
        ->assertJsonPath('confirmation_url', fn (string $value) => str_contains($value, 'signature='))
        ->assertJsonPath('whatsapp_url', fn (string $value) => str_contains($value, 'wa.me/1809'));

    $order = Order::query()->where('order_number', $created->json('order_number'))->sole();
    expect($order->status)->toBe('sent_to_whatsapp')
        ->and($order->total)->toBe('600.00')
        ->and($order->items()->count())->toBe(1);

    $this->actingAs($owner)
        ->post(route('seller.shops.orders.confirm', [$shop, $order]))
        ->assertRedirect();

    $invoice = Invoice::query()->where('shop_id', $shop->id)->sole();
    expect($order->fresh()->status)->toBe('confirmed')
        ->and($order->fresh()->invoice_id)->toBe($invoice->id)
        ->and($invoice->status)->toBe('paid')
        ->and($invoice->total)->toBe('600.00')
        ->and($product->fresh()->inventory->stock_quantity)->toBe(1)
        ->and($product->fresh()->inventory->sold_quantity)->toBe(2)
        ->and($session->fresh()->calculateExpectedBalance())->toBe(60000);

    // A repeated seller confirmation is a replay, never a second invoice or stock mutation.
    $this->actingAs($owner)
        ->post(route('seller.shops.orders.confirm', [$shop, $order]))
        ->assertRedirect();

    expect(Invoice::query()->where('shop_id', $shop->id)->count())->toBe(1)
        ->and($product->fresh()->inventory->stock_quantity)->toBe(1)
        ->and($product->fresh()->inventory->sold_quantity)->toBe(2);

    $closed = $cash->closeSession($session->fresh(), $owner, '600.00', 'E2E-01');
    expect($closed->status)->toBe('closed')
        ->and($closed->difference_cents)->toBe(0);
});
