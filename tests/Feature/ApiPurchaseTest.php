<?php

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\PurchaseDocument;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Android puede crear una compra en borrador y recibirla una sola vez', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $supplier = Supplier::create(['shop_id' => $shop->id, 'name' => 'Suplidor Android', 'is_active' => true]);
    $first = Product::factory()->for($shop)->create(['price' => 250]);
    $second = Product::factory()->for($shop)->create(['price' => 400]);
    ProductInventory::create(['product_id' => $first->id, 'track_inventory' => true, 'stock_quantity' => 2, 'cost_price' => 100, 'sold_quantity' => 0]);
    ProductInventory::create(['product_id' => $second->id, 'track_inventory' => true, 'stock_quantity' => 5, 'cost_price' => 200, 'sold_quantity' => 0]);
    $token = $owner->createToken('android-purchases', ['catalog:read', 'pos:write'])->plainTextToken;

    $workspace = $this->withToken($token)
        ->getJson('/api/v1/shops/'.$shop->public_id.'/purchases')
        ->assertOk()
        ->assertJsonPath('suppliers.0.id', $supplier->public_id)
        ->json();
    expect(collect($workspace['products'])->pluck('id'))->toContain($first->public_id);

    $draft = $this->withToken($token)->postJson('/api/v1/shops/'.$shop->public_id.'/purchases', [
        'type' => 'container',
        'document_number' => 'ANDROID-DRAFT-001',
        'supplier_id' => $supplier->public_id,
        'mode' => 'draft',
        'items' => [
            ['product_id' => $first->public_id, 'quantity' => 4, 'unit_cost' => '125.50'],
            ['product_id' => $second->public_id, 'quantity' => 2, 'unit_cost' => '210.00'],
        ],
    ]);
    $draft->assertCreated()->assertJsonPath('document.status', 'draft')->assertJsonPath('document.items.0.quantity', 4);

    $document = PurchaseDocument::where('shop_id', $shop->id)->sole();
    expect((int) $first->inventory()->first()->stock_quantity)->toBe(2)
        ->and(InventoryMovement::where('product_id', $first->id)->count())->toBe(0);

    $this->withToken($token)
        ->postJson('/api/v1/shops/'.$shop->public_id.'/purchases/'.$document->public_id.'/receive')
        ->assertOk()
        ->assertJsonPath('document.status', 'received');

    expect((int) $first->inventory()->first()->fresh()->stock_quantity)->toBe(6)
        ->and((int) $second->inventory()->first()->fresh()->stock_quantity)->toBe(7)
        ->and(InventoryMovement::where('product_id', $first->id)->count())->toBe(1);

    $this->withToken($token)
        ->postJson('/api/v1/shops/'.$shop->public_id.'/purchases/'.$document->public_id.'/receive')
        ->assertStatus(422)
        ->assertJsonPath('message', 'Esta compra ya fue recibida y no puede duplicar sus lotes.');
});

test('Android no puede recibir un documento de otra tienda', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $other = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $otherShop = Shop::factory()->for($other)->create();
    $product = Product::factory()->for($otherShop)->create();
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 2, 'cost_price' => 100, 'sold_quantity' => 0]);
    $document = PurchaseDocument::create([
        'shop_id' => $otherShop->id,
        'user_id' => $other->id,
        'document_number' => 'OTHER-001',
        'type' => 'container',
        'status' => 'draft',
        'currency' => 'DOP',
        'subtotal' => '0.00',
        'total' => '0.00',
    ]);
    $token = $owner->createToken('android-purchases', ['catalog:read', 'pos:write'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/shops/'.$shop->public_id.'/purchases/'.$document->public_id.'/receive')
        ->assertNotFound();
});
