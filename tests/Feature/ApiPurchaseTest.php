<?php

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\PurchaseDocument;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\ShopSeller;
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

test('Android puede crear un suplidor y queda aislado por tienda', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $other = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $otherShop = Shop::factory()->for($other)->create();
    $token = $owner->createToken('android-suppliers', ['catalog:read', 'pos:write'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/shops/'.$shop->public_id.'/suppliers', [
            'name' => 'Distribuidora MiCatalogo',
            'invoice_currency' => 'USD',
            'phone' => '+1 809 555 0101',
            'email' => 'compras@example.test',
            'address' => 'Santo Domingo',
            'notes' => 'Entrega los lunes',
        ])
        ->assertCreated()
        ->assertJsonPath('supplier.name', 'Distribuidora MiCatalogo')
        ->assertJsonPath('supplier.invoice_currency', 'USD')
        ->assertJsonPath('supplier.phone', '+1 809 555 0101');

    expect(Supplier::where('shop_id', $shop->id)->where('name', 'Distribuidora MiCatalogo')->exists())->toBeTrue()
        ->and(Supplier::where('shop_id', $otherShop->id)->exists())->toBeFalse();

    $this->withToken($token)
        ->postJson('/api/v1/shops/'.$shop->public_id.'/suppliers', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('Android puede consultar suplidores sin conceder el módulo de contenedores', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $seller = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $supplier = Supplier::create(['shop_id' => $shop->id, 'name' => 'Suplidor solo compras', 'is_active' => true]);
    ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'commission_type' => 'percentage',
        'commission_value' => 5,
        'menu_permissions' => ['suppliers'],
        'is_active' => true,
    ]);
    $token = $seller->createToken('android-suppliers', ['catalog:read', 'pos:write'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/shops/'.$shop->public_id.'/suppliers')
        ->assertOk()
        ->assertJsonPath('suppliers.0.id', $supplier->public_id);

    $this->withToken($token)
        ->getJson('/api/v1/shops/'.$shop->public_id.'/purchases')
        ->assertForbidden();
});

test('Android puede crear una carga logística sin inventario y relacionar un contenedor', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $supplier = Supplier::create(['shop_id' => $shop->id, 'name' => 'Courier Miami', 'is_active' => true]);
    $product = Product::factory()->for($shop)->create(['price' => 900]);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 0, 'cost_price' => 0, 'sold_quantity' => 0]);
    $token = $owner->createToken('android-loads', ['catalog:read', 'pos:write'])->plainTextToken;

    $load = $this->withToken($token)
        ->postJson('/api/v1/shops/'.$shop->public_id.'/purchases', [
            'type' => 'load',
            'document_number' => 'Miami septiembre',
            'currency' => 'USD',
            'exchange_rate' => '59.50',
            'carrier' => 'Courier Miami',
            'tracking_number' => 'BL-2026-09',
            'expected_at' => '2026-10-30',
            'shipping_pounds' => '42.5',
            'freight_amount' => '120.00',
            'customs_amount' => '80.00',
            'mode' => 'draft',
            'items' => [],
        ])
        ->assertCreated()
        ->assertJsonPath('document.type', 'load')
        ->assertJsonPath('document.exchange_rate', '59.500000')
        ->assertJsonPath('document.parent_document_id', null)
        ->json('document');

    $this->withToken($token)
        ->postJson('/api/v1/shops/'.$shop->public_id.'/purchases/'.$load['id'].'/receive')
        ->assertStatus(422)
        ->assertJsonPath('message', 'Una carga se recibe a través de sus contenedores.');

    $this->withToken($token)
        ->postJson('/api/v1/shops/'.$shop->public_id.'/purchases', [
            'type' => 'container',
            'document_number' => 'Caja-001',
            'supplier_id' => $supplier->public_id,
            'parent_document_id' => $load['id'],
            'mode' => 'received',
            'items' => [['product_id' => $product->public_id, 'quantity' => 2, 'unit_cost' => '10.00']],
        ])
        ->assertCreated()
        ->assertJsonPath('document.parent_document_id', $load['id'])
        ->assertJsonPath('document.items.0.received', true);

    expect((int) $product->inventory()->first()->fresh()->stock_quantity)->toBe(2);
});

test('Android puede registrar una deuda de suplidor sin crear lotes', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $supplier = Supplier::create(['shop_id' => $shop->id, 'name' => 'Suplidor deuda móvil', 'is_active' => true]);
    $token = $owner->createToken('android-supplier-debt', ['catalog:read', 'pos:write'])->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/shops/'.$shop->public_id.'/purchases', [
        'type' => 'supplier_debt',
        'document_number' => 'DEUDA-ANDROID-001',
        'supplier_id' => $supplier->public_id,
        'amount' => '480.75',
        'currency' => 'USD',
        'invoice_date' => '2026-10-01',
        'due_at' => '2026-11-01',
        'items' => [],
    ]);

    $response->assertCreated()
        ->assertJsonPath('document.type', 'supplier_debt')
        ->assertJsonPath('document.status', 'debt')
        ->assertJsonPath('document.total', '480.75')
        ->assertJsonPath('document.items', []);
    expect(PurchaseDocument::where('shop_id', $shop->id)->sole()->items()->count())->toBe(0);
});
