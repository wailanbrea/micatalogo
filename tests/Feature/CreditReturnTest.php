<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use App\Services\CustomerAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('credit sale return restocks inventory, reduces customer debt, and voids invoice when fully returned', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $token = $user->createToken('test', ['pos:write'])->plainTextToken;

    $product = Product::factory()->for($shop)->create([
        'price' => '500.00',
        'sale_unit' => 'unit',
    ]);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 5,
        'sold_quantity' => 2,
        'cost_price' => '250.00',
    ]);

    $customer = Customer::create([
        'shop_id' => $shop->id,
        'name' => 'Cliente Prueba',
        'credit_limit' => '5000.00',
        'balance' => '0.00',
        'is_active' => true,
    ]);

    // Create a credit sale of 2 units (total 1000.00)
    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'invoice_number' => 'INV-CREDIT-001',
        'status' => 'pending',
        'channel' => 'pos',
        'currency' => 'DOP',
        'subtotal' => '1000.00',
        'total' => '1000.00',
        'discount' => '0.00',
        'tax' => '0.00',
        'issued_at' => now(),
    ]);

    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 2,
        'unit_price' => '500.00',
        'subtotal' => '1000.00',
        'total' => '1000.00',
        'line_total' => '1000.00',
        'tax' => '0.00',
    ]);

    // Record initial credit charge
    app(CustomerAccountService::class)->recordInvoiceCharge($customer, $invoice, '1000.00', $user->id);
    expect((float) $customer->fresh()->balance)->toBe(1000.00);

    // Return 2 units
    $returnPayload = [
        'client_operation_uuid' => (string) Str::uuid(),
        'type' => 'return',
        'invoice_id' => $invoice->id,
        'notes' => 'Devolución completa de venta a crédito',
        'items' => [
            [
                'product_id' => $product->public_id,
                'quantity' => 2,
                'refund_price' => '500.00',
                'refund_total' => '1000.00',
                'restock' => true,
            ],
        ],
    ];

    $response = $this->withToken($token)
        ->postJson("/api/v1/shops/{$shop->public_id}/mobile-operations", $returnPayload)
        ->assertCreated()
        ->assertJsonPath('total', '1000.00');

    // Customer balance should be 0.00
    expect((float) $customer->fresh()->balance)->toBe(0.00);

    // Invoice status should be void
    expect($invoice->fresh()->status)->toBe('void');

    // Adjustment entry should be created
    $this->assertDatabaseHas('customer_account_entries', [
        'customer_id' => $customer->id,
        'invoice_id' => $invoice->id,
        'type' => 'adjustment',
        'amount' => '-1000.00',
        'balance_after' => '0.00',
    ]);
});

test('credit sale partial return reduces debt partially and keeps status pending', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $token = $user->createToken('test', ['pos:write'])->plainTextToken;

    $product = Product::factory()->for($shop)->create([
        'price' => '500.00',
        'sale_unit' => 'unit',
    ]);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 5,
        'sold_quantity' => 2,
        'cost_price' => '250.00',
    ]);

    $customer = Customer::create([
        'shop_id' => $shop->id,
        'name' => 'Cliente Prueba',
        'credit_limit' => '5000.00',
        'balance' => '0.00',
        'is_active' => true,
    ]);

    // Create a credit sale of 2 units (total 1000.00)
    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'invoice_number' => 'INV-CREDIT-002',
        'status' => 'pending',
        'channel' => 'pos',
        'currency' => 'DOP',
        'subtotal' => '1000.00',
        'total' => '1000.00',
        'discount' => '0.00',
        'tax' => '0.00',
        'issued_at' => now(),
    ]);

    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 2,
        'unit_price' => '500.00',
        'subtotal' => '1000.00',
        'total' => '1000.00',
        'line_total' => '1000.00',
        'tax' => '0.00',
    ]);

    app(CustomerAccountService::class)->recordInvoiceCharge($customer, $invoice, '1000.00', $user->id);

    // Return only 1 unit
    $returnPayload = [
        'client_operation_uuid' => (string) Str::uuid(),
        'type' => 'return',
        'invoice_id' => $invoice->id,
        'notes' => 'Devolución parcial',
        'items' => [
            [
                'product_id' => $product->public_id,
                'quantity' => 1,
                'refund_price' => '500.00',
                'refund_total' => '500.00',
                'restock' => true,
            ],
        ],
    ];

    $this->withToken($token)
        ->postJson("/api/v1/shops/{$shop->public_id}/mobile-operations", $returnPayload)
        ->assertCreated()
        ->assertJsonPath('total', '500.00');

    // Customer balance should be 500.00
    expect((float) $customer->fresh()->balance)->toBe(500.00);

    // Invoice status should remain pending
    expect($invoice->fresh()->status)->toBe('pending');

    // Adjustment entry should be created for -500.00
    $this->assertDatabaseHas('customer_account_entries', [
        'customer_id' => $customer->id,
        'invoice_id' => $invoice->id,
        'type' => 'adjustment',
        'amount' => '-500.00',
        'balance_after' => '500.00',
    ]);
});

test('sale_cancel operation clears pending debt and marks invoice void', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();
    $token = $user->createToken('test', ['pos:write'])->plainTextToken;

    $product = Product::factory()->for($shop)->create([
        'price' => '300.00',
        'sale_unit' => 'unit',
    ]);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 10,
        'sold_quantity' => 2,
        'cost_price' => '150.00',
    ]);

    $customer = Customer::create([
        'shop_id' => $shop->id,
        'name' => 'Cliente Prueba',
        'credit_limit' => '5000.00',
        'balance' => '0.00',
        'is_active' => true,
    ]);

    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'invoice_number' => 'INV-CANCEL-001',
        'status' => 'pending',
        'channel' => 'pos',
        'currency' => 'DOP',
        'subtotal' => '600.00',
        'total' => '600.00',
        'discount' => '0.00',
        'tax' => '0.00',
        'issued_at' => now(),
    ]);

    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 2,
        'unit_price' => '300.00',
        'subtotal' => '600.00',
        'total' => '600.00',
        'line_total' => '600.00',
        'tax' => '0.00',
    ]);

    app(CustomerAccountService::class)->recordInvoiceCharge($customer, $invoice, '600.00', $user->id);
    expect((float) $customer->fresh()->balance)->toBe(600.00);

    $cancelPayload = [
        'client_operation_uuid' => (string) Str::uuid(),
        'type' => 'sale_cancel',
        'invoice_id' => $invoice->id,
        'notes' => 'Venta a crédito por error',
    ];

    $this->withToken($token)
        ->postJson("/api/v1/shops/{$shop->public_id}/mobile-operations", $cancelPayload)
        ->assertCreated()
        ->assertJsonPath('status', 'void');

    expect((float) $customer->fresh()->balance)->toBe(0.00);
    expect($invoice->fresh()->status)->toBe('void');
});
