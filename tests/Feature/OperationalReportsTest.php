<?php

use App\Models\{User, Shop, Product, Invoice, InvoiceItem, ShopSeller};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('operational report uses business date and returns the full product detail', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-10-20 02:00:00', 'UTC'));
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create(['operational_settings' => ['timezone' => 'America/Santo_Domingo']]);
    $invoice = Invoice::create(['shop_id' => $shop->id, 'user_id' => $owner->id, 'invoice_number' => 'QA-REPORT', 'status' => 'paid', 'channel' => 'pos', 'currency' => 'DOP', 'subtotal' => 2500, 'total' => 2500, 'issued_at' => '2026-10-19 18:00:00']);
    foreach (range(1, 25) as $index) {
        $product = Product::factory()->for($shop)->create(['name' => 'Producto '.$index]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100, 'total_cost_cents' => 5000]);
    }
    $response = $this->withToken($owner->createToken('reports', ['*'])->plainTextToken)
        ->getJson('/api/v1/shops/'.$shop->public_id.'/features/reports?period=today')
        ->assertOk()->assertJsonPath('module.report_context.from', '2026-10-19')
        ->assertJsonPath('module.report_context.to', '2026-10-19')
        ->assertJsonPath('module.report_context.shop_name', $shop->name)
        ->assertJsonCount(25, 'module.sections.0.rows');
    expect($response->json('module.sections.0.kpis.0.value'))->toBe('RD$ 2,500.00');
    expect(Invoice::count())->toBe(1)->and(InvoiceItem::count())->toBe(25);
});

test('operational reports never include costs for a reports-only seller or another tenant', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $seller = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create();
    ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $seller->id, 'is_active' => true, 'commission_type' => 'percentage', 'commission_value' => 0, 'menu_permissions' => ['reports']]);
    $this->withToken($seller->createToken('reports', ['*'])->plainTextToken)
        ->getJson('/api/v1/shops/'.$shop->public_id.'/features/reports?period=last_7')
        ->assertOk()->assertDontSee('Ganancia bruta')->assertDontSee('Inventario al costo')
        ->assertJsonPath('module.report_context.timezone', 'America/Santo_Domingo');
    $other = Shop::factory()->create();
    $this->getJson('/api/v1/shops/'.$other->public_id.'/features/reports')->assertNotFound();
});
