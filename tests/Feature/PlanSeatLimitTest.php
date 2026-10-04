<?php

use App\Enums\UserPlan;
use App\Models\Shop;
use App\Models\User;
use App\Services\PlanLimitsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seller invitations respect the plan seller limit without creating overflow accounts', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Free]);
    $shop = Shop::factory()->for($owner)->create();
    $payload = fn (string $email): array => [
        'email' => $email,
        'commission_type' => 'percentage',
        'commission_value' => '5',
    ];

    $this->actingAs($owner)->post(route('seller.shops.sellers.store', $shop), $payload('seller1@example.com'))->assertRedirect();
    $this->actingAs($owner)->post(route('seller.shops.sellers.store', $shop), $payload('seller2@example.com'))->assertSessionHasErrors('email');

    expect($shop->sellers()->where('is_active', true)->count())->toBe(1)
        ->and(User::where('email', 'seller2@example.com')->exists())->toBeFalse();
});

test('administrative invitations respect the plan user limit', function () {
    $owner = User::factory()->create(['plan' => UserPlan::Premium]);
    $shop = Shop::factory()->for($owner)->create();

    $this->actingAs($owner)->post(route('seller.shops.members.store', $shop), [
        'email' => 'manager1@example.com',
    ])->assertRedirect();

    $this->actingAs($owner)->post(route('seller.shops.members.store', $shop), [
        'email' => 'manager2@example.com',
    ])->assertRedirect();
    $this->actingAs($owner)->post(route('seller.shops.members.store', $shop), [
        'email' => 'manager3@example.com',
    ])->assertSessionHasErrors('email');

    expect($shop->members()->where('is_active', true)->count())->toBe(2)
        ->and(User::where('email', 'manager3@example.com')->exists())->toBeFalse();
});

test('additional seller seats expand only the seller quota', function () {
    $owner = User::factory()->create([
        'plan' => UserPlan::Free,
        'additional_seller_seats' => 1,
    ]);
    $shop = Shop::factory()->for($owner)->create();
    $payload = fn (string $email): array => [
        'email' => $email,
        'commission_type' => 'percentage',
        'commission_value' => '5',
    ];

    $this->actingAs($owner)->post(route('seller.shops.sellers.store', $shop), $payload('seller1@example.com'))->assertRedirect();
    $this->actingAs($owner)->post(route('seller.shops.sellers.store', $shop), $payload('seller2@example.com'))->assertRedirect();

    expect(app(PlanLimitsService::class)->sellerCount($shop->fresh()))->toBe(2);
});
