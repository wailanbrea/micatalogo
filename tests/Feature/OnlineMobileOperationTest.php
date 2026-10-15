<?php

use App\Enums\UserPlan;
use App\Models\{Invoice, MobileOperation, Product, ProductInventory, Shop, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function onlineCommand(string $type, array $request = [], ?string $resource = null): array
{
    $id = (string) Str::uuid();
    return ['type' => $type, 'client_operation_uuid' => $id, 'request' => $request + ['client_operation_uuid' => $id]]
        + ($resource ? ['resource_id' => $resource] : []);
}

beforeEach(function () {
    $this->owner = User::factory()->create(['plan' => UserPlan::Premium]);
    $this->shop = Shop::factory()->create(['user_id' => $this->owner->id]);
    $this->other = Shop::factory()->create(['user_id' => $this->owner->id]);
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'sale_unit' => 'unit', 'price' => 300]);
    ProductInventory::create(['product_id' => $this->product->id, 'track_inventory' => true, 'stock_quantity' => 20, 'cost_price' => 120]);
    $this->url = '/api/v1/shops/'.$this->shop->public_id.'/mobile-operations';
    Sanctum::actingAs($this->owner, ['pos:write']);
});

test('quotation intent conversion sale history and eligible refunds read back once under stable UUIDs', function () {
    $create = onlineCommand('quote_create', ['valid_until' => now()->addDays(2)->toDateString(),
        'items' => [['product_id' => $this->product->public_id, 'quantity' => 1, 'unit_price' => '300.00']]]);
    $quote = $this->postJson($this->url, $create)->assertCreated()->json('quote.id');
    $this->postJson($this->url, $create)->assertCreated()->assertJsonPath('quote.id', $quote);
    expect($this->shop->quotes()->count())->toBe(1);
    $convert = onlineCommand('quote_convert', [], $quote);
    $number = $this->postJson($this->url, $convert)->assertCreated()->json('invoice_number');
    $this->postJson($this->url, $convert)->assertCreated()->assertJsonPath('invoice_number', $number);
    $this->postJson($this->url, onlineCommand('quote_convert', [], $quote))->assertConflict();
    expect($this->shop->invoices()->count())->toBe(1)->and($this->product->inventory->fresh()->stock_quantity)->toBe(19)
        ->and(MobileOperation::count())->toBe(2);
    $this->getJson('/api/v1/shops/'.$this->shop->public_id.'/features/sales?period=all')->assertOk()
        ->assertJsonPath('module.rows.0.primary', $number)->assertJsonPath('module.rows.0.items.0.quantity', 1);
    $this->getJson('/api/v1/shops/'.$this->shop->public_id.'/returns/eligible')->assertOk()
        ->assertJsonPath('invoices.0.invoice_number', $number)->assertJsonPath('invoices.0.items.0.charged_line_cents', 30000);
    $this->getJson('/api/v1/shops/'.$this->other->public_id.'/features/sales?period=all')->assertOk()->assertJsonCount(0, 'module.rows');
});

test('suppliers and purchases use the envelope without duplicating receipt lots', function () {
    $supplierRequest = onlineCommand('supplier_create', ['name' => 'Suplidor QA']);
    $supplier = $this->postJson($this->url, $supplierRequest)->assertCreated()->json('supplier.id');
    $this->postJson($this->url, $supplierRequest)->assertCreated();
    $purchase = onlineCommand('purchase_create', ['type' => 'purchase_invoice', 'document_number' => 'QA-IMMEDIATE-1', 'supplier_id' => $supplier,
        'mode' => 'draft', 'currency' => 'DOP', 'items' => [['product_id' => $this->product->public_id, 'quantity' => 2, 'unit_cost' => '130.00']]]);
    $document = $this->postJson($this->url, $purchase)->assertCreated()->json('document.id');
    $this->postJson($this->url, $purchase)->assertCreated();
    $receive = onlineCommand('purchase_receive', [], $document);
    $this->postJson($this->url, $receive)->assertCreated();
    $this->postJson($this->url, $receive)->assertCreated();
    expect($this->shop->suppliers()->count())->toBe(1)->and($this->product->inventory->fresh()->stock_quantity)->toBe(22);
    $this->getJson('/api/v1/shops/'.$this->shop->public_id.'/purchases')->assertOk()->assertJsonCount(1, 'documents');
    $this->postJson('/api/v1/shops/'.$this->other->public_id.'/mobile-operations', onlineCommand('purchase_receive', [], $document))->assertNotFound();
});

test('order envelopes preserve the existing numeric key contract and reject another shops order', function () {
    $order = $this->shop->orders()->create(['order_number' => 'QA-ENVELOPE-'.Str::uuid(), 'customer_name' => 'Comprador QA',
        'currency' => 'DOP', 'subtotal' => '300.00', 'total' => '300.00', 'status' => 'sent_to_whatsapp']);
    $order->items()->create(['product_id' => $this->product->id, 'product_name' => $this->product->name, 'quantity' => 1,
        'unit_price' => '300.00', 'line_total' => '300.00']);
    $request = onlineCommand('order_confirm', ['payment_kind' => 'cash', 'payment_method' => 'cash'], (string) $order->id);
    $this->postJson($this->url, $request)->assertCreated();
    $this->postJson($this->url, $request)->assertCreated();
    expect($this->shop->invoices()->count())->toBe(1)->and($this->product->inventory->fresh()->stock_quantity)->toBe(19);
    $this->postJson('/api/v1/shops/'.$this->other->public_id.'/mobile-operations',
        onlineCommand('order_confirm', ['payment_kind' => 'cash', 'payment_method' => 'cash'], (string) $order->id))->assertNotFound();
});

test('credit order envelopes require current credit authority even for an existing receipt', function () {
    $actor = User::factory()->create();
    $assignment = \App\Models\ShopSeller::create(['shop_id' => $this->shop->id, 'user_id' => $actor->id, 'is_active' => true,
        'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions' => ['orders', 'collections']]);
    $customer = \App\Models\Customer::create(['shop_id' => $this->shop->id, 'name' => 'QA_NAV_OrderCredit', 'credit_limit' => '1000.00', 'balance' => '0.00']);
    $order = $this->shop->orders()->create(['order_number' => 'QA_NAV_Order-'.Str::uuid(), 'customer_name' => 'QA_NAV_Buyer',
        'currency' => 'DOP', 'subtotal' => '300.00', 'total' => '300.00', 'status' => 'sent_to_whatsapp']);
    $order->items()->create(['product_id' => $this->product->id, 'product_name' => $this->product->name, 'quantity' => 1,
        'unit_price' => '300.00', 'line_total' => '300.00']);
    Sanctum::actingAs($actor, ['pos:write']);
    $request = onlineCommand('order_confirm', ['payment_kind' => 'credit', 'customer_id' => $customer->public_id], (string) $order->id);
    $this->postJson($this->url, $request)->assertForbidden();
    expect($customer->fresh()->balance)->toBe('0.00')->and($this->product->inventory->fresh()->stock_quantity)->toBe(20);
    $assignment->update(['menu_permissions' => ['orders', 'credit']]);
    $this->postJson($this->url, $request)->assertCreated();
    $assignment->update(['menu_permissions' => ['orders', 'collections']]);
    $this->postJson($this->url, $request)->assertForbidden();
    expect($customer->fresh()->balance)->toBe('300.00')->and($this->product->inventory->fresh()->stock_quantity)->toBe(19)
        ->and($this->shop->invoices()->count())->toBe(1);
});

test('partner creation and money movements replay exactly once in the authoritative cash session', function () {
    $this->postJson($this->url, onlineCommand('cash_open', ['opening_amount' => '0.00']))->assertCreated();
    $create = onlineCommand('partner_create', ['name' => 'Socio QA', 'ownership_percent' => '20']);
    $partner = $this->postJson($this->url, $create)->assertCreated()->json('partner.id');
    $this->postJson($this->url, $create)->assertCreated();
    $movement = onlineCommand('partner_transaction', ['type' => 'contribution', 'amount' => '100.00'], $partner);
    $transaction = $this->postJson($this->url, $movement)->assertCreated()->json('transaction.id');
    $this->postJson($this->url, $movement)->assertCreated()->assertJsonPath('transaction.id', $transaction);
    expect($this->shop->partners()->count())->toBe(1)->and(\App\Models\PartnerTransaction::where('shop_id', $this->shop->id)->count())->toBe(1);
    $cash = $this->getJson('/api/v1/shops/'.$this->shop->public_id.'/cash-sessions/current')->assertOk();
    expect(\App\Support\Money::toCents($cash->json('session.summary.expected_amount')))->toBe(10000);
});

test('sales export includes the complete filtered period beyond the on screen page and is tenant scoped', function () {
    for ($i = 0; $i < 105; $i++) {
        Invoice::create(['shop_id' => $this->shop->id, 'user_id' => $this->owner->id, 'invoice_number' => 'CSV-QA-'.$i,
            'channel' => 'pos', 'status' => 'paid', 'currency' => 'DOP', 'subtotal' => 1, 'discount' => 0, 'tax' => 0, 'total' => 1, 'issued_at' => now()]);
    }
    $this->getJson('/api/v1/shops/'.$this->shop->public_id.'/features/sales?period=all&q=CSV-QA')->assertOk()->assertJsonCount(100, 'module.rows');
    $csv = $this->get('/api/v1/shops/'.$this->shop->public_id.'/sales/export?period=all&q=CSV-QA')->assertOk()->streamedContent();
    expect(substr_count($csv, 'CSV-QA-'))->toBe(105);
    $otherCsv = $this->get('/api/v1/shops/'.$this->other->public_id.'/sales/export?period=all')->assertOk()->streamedContent();
    expect($otherCsv)->not->toContain('CSV-QA-');
    Sanctum::actingAs(User::factory()->create(), ['pos:write']);
    $this->get('/api/v1/shops/'.$this->shop->public_id.'/sales/export?period=all')->assertNotFound();
});
