<?php

use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function navigationPurchaseActor(string $permission): array
{
    $shop = Shop::factory()->for(User::factory()->create(['plan' => 'pro']))->create();
    $actor = User::factory()->create();
    ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $actor->id, 'is_active' => true,
        'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions' => [$permission]]);
    $product = Product::factory()->for($shop)->create(['name' => 'QA_NAV_PurchaseItem', 'price' => '100.00']);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 5, 'cost_price' => '10.00']);

    return [$shop, $actor, $product];
}

test('invoice only can create list and receive its document without inheriting containers or loads', function () {
    [$shop, $actor, $product] = navigationPurchaseActor('purchase_invoices');
    $this->withToken($actor->createToken('nav-purchase')->plainTextToken);
    $url = '/api/v1/shops/'.$shop->public_id.'/purchases';
    $body = ['type' => 'purchase_invoice', 'document_number' => 'QA_NAV_Invoice', 'mode' => 'draft',
        'items' => [['product_id' => $product->public_id, 'quantity' => 1, 'unit_cost' => '12.00']]];
    $id = $this->postJson($url, $body)->assertCreated()->json('document.id');
    $shop->purchaseDocuments()->create(['user_id' => $shop->user_id, 'type' => 'container', 'document_number' => 'QA_NAV_Container', 'status' => 'draft', 'currency' => 'DOP', 'payment_status' => 'pending', 'subtotal' => 0, 'total' => 0]);
    $this->getJson($url)->assertOk()->assertJsonCount(1, 'documents')->assertJsonPath('documents.0.id', $id);
    // Receiving the draft affects stock once.
    $this->postJson($url.'/'.$id.'/receive')->assertOk();
    $this->postJson($url.'/'.$id.'/receive')->assertUnprocessable();
    expect($product->inventory->fresh()->stock_quantity)->toBe(6);
    $body['type'] = 'container';
    $this->postJson($url, $body)->assertForbidden();
    $body['type'] = 'load';
    $this->postJson($url, $body)->assertForbidden();
});

test('container permission cannot receive a purchase invoice by spoofing its request type', function () {
    [$shop, $actor, $product] = navigationPurchaseActor('containers');
    $invoice = $shop->purchaseDocuments()->create(['user_id' => $shop->user_id, 'type' => 'purchase_invoice', 'document_number' => 'QA_NAV_ProtectedInvoice', 'status' => 'draft', 'currency' => 'DOP', 'payment_status' => 'pending', 'subtotal' => 0, 'total' => 0]);
    $this->withToken($actor->createToken('nav-container')->plainTextToken)
        ->postJson('/api/v1/shops/'.$shop->public_id.'/purchases/'.$invoice->public_id.'/receive', ['type' => 'container'])->assertForbidden();
    expect($invoice->fresh()->status)->toBe('draft')->and($product->inventory->fresh()->stock_quantity)->toBe(5);
});

test('durable purchase envelopes authorize the actual type and preserve replay identity', function () {
    [$shop, $actor, $product] = navigationPurchaseActor('purchase_invoices');
    $id = (string) Str::uuid();
    $body = ['type' => 'purchase_create', 'client_operation_uuid' => $id, 'request' => [
        'client_operation_uuid' => $id, 'type' => 'purchase_invoice', 'document_number' => 'QA_NAV_DurableInvoice', 'mode' => 'draft',
        'items' => [['product_id' => $product->public_id, 'quantity' => 1, 'unit_cost' => '12.00']],
    ]];
    $url = '/api/v1/shops/'.$shop->public_id.'/mobile-operations';
    $this->withToken($actor->createToken('nav-envelope', ['pos:write'])->plainTextToken)->postJson($url, $body)->assertCreated();
    $this->postJson($url, $body)->assertCreated();
    expect($shop->purchaseDocuments()->count())->toBe(1)->and($product->inventory->fresh()->stock_quantity)->toBe(5);
});
