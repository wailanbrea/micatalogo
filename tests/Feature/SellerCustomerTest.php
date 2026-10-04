<?php

use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a seller can create and view customers in their shop', function () {
    $seller = User::factory()->create();
    $shop = Shop::factory()->for($seller)->create();

    $this->actingAs($seller)->post(route('seller.shops.customers.store', $shop), [
        'name' => 'Ana Perez',
        'phone' => '8095550101',
        'credit_limit' => '1500.00',
    ])->assertRedirect();

    $this->assertDatabaseHas('customers', [
        'shop_id' => $shop->id,
        'name' => 'Ana Perez',
        'credit_limit' => '1500.00',
        'balance' => '0.00',
    ]);
    $this->actingAs($seller)->get(route('seller.shops.customers.index', $shop))
        ->assertOk()
        ->assertSee('Clientes y cuentas por cobrar')
        ->assertSee('Ana Perez');
});

test('a seller records credit and payment without allowing an overpayment', function () {
    $seller = User::factory()->create();
    $shop = Shop::factory()->for($seller)->create();
    $customer = Customer::create([
        'shop_id' => $shop->id,
        'name' => 'Ana Perez',
        'credit_limit' => '500.00',
    ]);

    $this->actingAs($seller)->post(route('seller.shops.customers.charge', [$shop, $customer]), [
        'amount' => '300.00',
        'notes' => 'Venta fiada',
    ])->assertRedirect();

    $this->actingAs($seller)->post(route('seller.shops.customers.payment', [$shop, $customer]), [
        'amount' => '125.00',
        'notes' => 'Abono en caja',
    ])->assertRedirect();

    expect($customer->fresh()->balance)->toBe('175.00')
        ->and(CustomerAccountEntry::query()->count())->toBe(2);

    $this->actingAs($seller)->from(route('seller.shops.customers.index', $shop))
        ->post(route('seller.shops.customers.payment', [$shop, $customer]), [
            'amount' => '200.00',
        ])
        ->assertRedirect(route('seller.shops.customers.index', $shop))
        ->assertSessionHasErrors('payment');

    expect($customer->fresh()->balance)->toBe('175.00');
});

test('a seller cannot manage customers from another shop', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create();
    $customer = Customer::create(['shop_id' => $shop->id, 'name' => 'Ana', 'credit_limit' => 100]);
    $otherSeller = User::factory()->create();

    $this->actingAs($otherSeller)->get(route('seller.shops.customers.index', $shop))->assertForbidden();
    $this->actingAs($otherSeller)->post(route('seller.shops.customers.payment', [$shop, $customer]), ['amount' => '10.00'])->assertForbidden();
});
