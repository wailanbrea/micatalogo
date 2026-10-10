<?php

use App\Models\CommercialQuote;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('quote read model contains real customer validity and item snapshots for the native detail screen', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $quote = CommercialQuote::create(['shop_id' => $shop->id, 'quote_number' => 'COT-STYLE-001', 'status' => 'draft',
        'customer_name' => 'Cliente de prueba aislada', 'valid_until' => now()->addDays(7), 'subtotal' => '300.00', 'total' => '300.00']);
    $quote->items()->create(['product_name' => 'Artículo conservado', 'product_code' => 'ITEM-1', 'quantity' => 2,
        'unit_price' => '150.00', 'line_total' => '300.00', 'sale_unit' => 'unit']);
    $this->actingAs($user)->getJson('/api/v1/shops/'.$shop->public_id.'/features/quotes')->assertOk()
        ->assertJsonPath('module.rows.0.customer_name', 'Cliente de prueba aislada')
        ->assertJsonPath('module.rows.0.item_count', 2)
        ->assertJsonPath('module.rows.0.items.0.name', 'Artículo conservado')
        ->assertJsonPath('module.rows.0.pdf_available', true);
    expect(DB::table('invoices')->count())->toBe(0);
});

test('authenticated quote pdf enforces tenant scope and does not convert or charge the quote', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $quote = CommercialQuote::create(['shop_id' => $shop->id, 'quote_number' => 'COT-PDF-001', 'status' => 'draft', 'subtotal' => '0.00', 'total' => '0.00']);
    $response = $this->actingAs($user)->get('/api/v1/shops/'.$shop->public_id.'/quotes/'.$quote->public_id.'/pdf');
    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF')
        ->and(DB::table('invoices')->count())->toBe(0)
        ->and($quote->fresh()->status)->toBe('draft');
    $other = User::factory()->create(['plan' => 'pro']);
    $otherShop = Shop::factory()->for($other)->create();
    $this->actingAs($other)->get('/api/v1/shops/'.$otherShop->public_id.'/quotes/'.$quote->public_id.'/pdf')->assertNotFound();
});
