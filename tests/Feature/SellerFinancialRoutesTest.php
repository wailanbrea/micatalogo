<?php

use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner can open a web cash session and record a manual movement', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->post(route('seller.shops.cash.open', $shop), [
            'opening_amount' => '100.10',
            'notes' => 'Fondo inicial QA',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'Sesión de caja abierta'));

    $session = CashRegisterSession::query()
        ->where('shop_id', $shop->id)
        ->where('user_id', $owner->id)
        ->sole();

    $this->actingAs($owner)
        ->post(route('seller.shops.cash.movement', [$shop, $session]), [
            'type' => 'cash_in',
            'amount' => '25.25',
            'notes' => 'Entrada manual QA',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Movimiento de caja registrado exitosamente.');

    expect($session->fresh()->opening_amount_cents)->toBe(10010)
        ->and(CashMovement::query()->where('cash_register_session_id', $session->id)->sole()->amount_cents)->toBe(2525);
});

test('owner can create a web expense with a partial payment and settle the remainder', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    $category = ExpenseCategory::create([
        'shop_id' => $shop->id,
        'name' => 'Servicios QA',
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->post(route('seller.shops.expenses.store', $shop), [
            'expense_category_id' => $category->id,
            'description' => 'Internet QA',
            'amount' => '100.10',
            'paid_amount' => '40.05',
            'payment_status' => 'partial',
            'payment_method' => 'bank_transfer',
            'occurred_at' => now()->toDateString(),
        ])
        ->assertRedirect()
        ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'Gasto registrado correctamente'));

    $expense = Expense::query()->where('shop_id', $shop->id)->sole();

    expect($expense->amount_cents)->toBe(10010)
        ->and($expense->amount_paid_cents)->toBe(4005)
        ->and($expense->unpaidAmountCents())->toBe(6005)
        ->and($expense->payment_status)->toBe('partial');

    $this->actingAs($owner)
        ->post(route('seller.shops.expenses.payments.store', [$shop, $expense]), [
            'amount' => '60.05',
            'payment_method' => 'card',
            'reference' => 'QA-CARD-001',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'Abono registrado correctamente'));

    expect($expense->fresh()->amount_paid_cents)->toBe(10010)
        ->and($expense->fresh()->payment_status)->toBe('paid')
        ->and($expense->fresh()->unpaidAmountCents())->toBe(0);
});

test('owner can create an expense category through the web route and duplicate names are rejected', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->post(route('seller.shops.expenses.categories.store', $shop), ['name' => 'Publicidad QA'])
        ->assertRedirect()
        ->assertSessionHas('status', 'Categoría "Publicidad QA" creada.');

    $this->actingAs($owner)
        ->post(route('seller.shops.expenses.categories.store', $shop), ['name' => 'Publicidad QA'])
        ->assertRedirect()
        ->assertSessionHasErrors('category');

    expect(ExpenseCategory::query()->where('shop_id', $shop->id)->where('name', 'Publicidad QA')->count())->toBe(1);
});
