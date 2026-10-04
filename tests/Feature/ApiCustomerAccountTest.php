<?php

use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('a seller creates an idempotent customer scoped to their shop', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $payload = [
        'client_customer_uuid' => (string) Str::uuid(),
        'name' => 'Ana Perez',
        'phone' => '8095550101',
        'credit_limit' => '1500.00',
    ];
    $token = $user->createToken('BSPOS', ['customers:write'])->plainTextToken;

    $first = $this->withToken($token)
        ->postJson("/api/v1/shops/{$shop->public_id}/customers", $payload)
        ->assertCreated()
        ->assertJsonPath('name', 'Ana Perez')
        ->assertJsonPath('balance', '0.00')
        ->assertJsonPath('available_credit', '1500.00');

    $this->withToken($token)
        ->postJson("/api/v1/shops/{$shop->public_id}/customers", $payload)
        ->assertCreated()
        ->assertExactJson($first->json());

expect(Customer::query()->count())->toBe(1);
});

test('a customer created with credit profile keeps identity and contact fields', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('BSPOS', ['customers:write'])->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/shops/{$shop->public_id}/customers", [
            'client_customer_uuid' => (string) Str::uuid(),
            'first_name' => 'Laura',
            'last_name' => 'Gomez',
            'document_type' => 'cedula',
            'document_number' => '001-1234567-8',
            'phone' => '8095550199',
            'address' => 'Calle Principal 10',
            'credit_limit' => '2500.00',
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'Laura Gomez')
        ->assertJsonPath('first_name', 'Laura')
        ->assertJsonPath('document_number', '001-1234567-8')
        ->assertJsonPath('address', 'Calle Principal 10');
});

test('customer APIs require the customer read ability', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    $this->withToken($owner->createToken('BSPOS', ['catalog:read'])->plainTextToken)
        ->getJson("/api/v1/shops/{$shop->public_id}/customers")
        ->assertForbidden();
});

test('a credit sale creates one invoice charge and idempotent payments reduce the customer balance', function () {
    [$user, $shop, $product] = customerSaleFixture(stock: 5, price: 300);
    $customer = Customer::create([
        'shop_id' => $shop->id,
        'name' => 'Ana Perez',
        'credit_limit' => '1000.00',
    ]);
    $saleUuid = (string) Str::uuid();
    $token = $user->createToken('BSPOS', ['pos:write', 'customers:write'])->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/shops/{$shop->public_id}/pos-sales", customerSalePayload($saleUuid, $product, 2, $customer->public_id, '600.00'))
        ->assertCreated()
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('total', '600.00');

    $invoice = Invoice::query()->sole();
    expect($invoice->customer_id)->toBe($customer->id)
        ->and($customer->fresh()->balance)->toBe('600.00');
    $this->assertDatabaseHas('customer_account_entries', [
        'customer_id' => $customer->id,
        'invoice_id' => $invoice->id,
        'type' => 'charge',
        'amount' => '600.00',
        'balance_after' => '600.00',
    ]);

    $payment = [
        'client_transaction_uuid' => (string) Str::uuid(),
        'amount' => '250.00',
        'notes' => 'Abono en efectivo',
    ];
    $first = $this->withToken($token)
        ->postJson("/api/v1/shops/{$shop->public_id}/customers/{$customer->public_id}/payments", $payment)
        ->assertCreated()
        ->assertJsonPath('customer.balance', '350.00')
        ->assertJsonPath('entry.amount', '-250.00');

    $this->withToken($token)
        ->postJson("/api/v1/shops/{$shop->public_id}/customers/{$customer->public_id}/payments", $payment)
        ->assertCreated()
        ->assertExactJson($first->json());

    expect($customer->fresh()->balance)->toBe('350.00')
        ->and(CustomerAccountEntry::query()->count())->toBe(2);
});

test('a POS credit sale cannot exceed the customer credit limit or mutate stock', function () {
    [$user, $shop, $product] = customerSaleFixture(stock: 5, price: 300);
    $customer = Customer::create(['shop_id' => $shop->id, 'name' => 'Ana', 'credit_limit' => '500.00']);

    $this->withToken($user->createToken('BSPOS', ['pos:write'])->plainTextToken)
        ->postJson("/api/v1/shops/{$shop->public_id}/pos-sales", customerSalePayload((string) Str::uuid(), $product, 2, $customer->public_id, '600.00'))
        ->assertConflict()
        ->assertJsonPath('reason', 'credit_limit');

    expect($product->fresh()->inventory->stock_quantity)->toBe(5)
        ->and(Invoice::query()->count())->toBe(0)
        ->and(CustomerAccountEntry::query()->count())->toBe(0)
        ->and($customer->fresh()->balance)->toBe('0.00');
});

/** @return array{0: User, 1: Shop, 2: Product} */
function customerSaleFixture(int $stock, float $price): array
{
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create(['shop_id' => $shop->id, 'price' => $price, 'sale_unit' => 'unit']);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => $stock, 'sold_quantity' => 0]);

    return [$user, $shop, $product];
}

/** @return array<string, mixed> */
function customerSalePayload(string $saleUuid, Product $product, int $quantity, string $customerId, string $creditAmount): array
{
    return [
        'client_sale_uuid' => $saleUuid,
        'payment_status' => 'paid',
        'customer_id' => $customerId,
        'credit_amount' => $creditAmount,
        'items' => [[
            'product_id' => $product->public_id,
            'quantity' => $quantity,
            'unit_price' => number_format((float) $product->price, 2, '.', ''),
        ]],
    ];
}
