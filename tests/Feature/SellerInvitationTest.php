<?php

use App\Models\Shop;
use App\Models\User;
use App\Notifications\SellerInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('a shop owner can invite a new seller by email', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create(['name' => 'BSolutions']);

    $this->actingAs($owner)
        ->post(route('seller.shops.sellers.store', $shop), [
            'email' => 'new-seller@example.com',
            'commission_type' => 'percentage',
            'commission_value' => '7.50',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Se creó la cuenta de New Seller y se envió la invitación a new-seller@example.com.');

    $seller = User::query()->where('email', 'new-seller@example.com')->sole();

    expect($seller->hasVerifiedEmail())->toBeFalse()
        ->and(Hash::check('password', $seller->password))->toBeFalse();
    $this->assertDatabaseHas('shop_sellers', [
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'commission_type' => 'percentage',
        'commission_value' => 7.5,
        'is_active' => true,
    ]);
    Notification::assertSentTo($seller, SellerInvitationNotification::class);
});

test('an invited seller creates a password and can then use BSPOS credentials', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create();
    $seller = User::factory()->unverified()->create(['password' => Hash::make('temporary-password')]);
    $shop->sellers()->create([
        'user_id' => $seller->id,
        'commission_type' => 'fixed',
        'commission_value' => 10,
        'is_active' => true,
    ]);
    $url = URL::temporarySignedRoute('seller.invitation.show', now()->addHour(), [
        'user' => $seller,
        'hash' => sha1($seller->getEmailForVerification()),
        'shop' => $shop,
    ]);

    $this->get($url)
        ->assertOk()
        ->assertSee('Crea tu contraseña')
        ->assertSee($shop->name);

    $activationUrl = URL::temporarySignedRoute('seller.invitation.activate', now()->addHour(), [
        'user' => $seller,
        'hash' => sha1($seller->getEmailForVerification()),
        'shop' => $shop,
    ]);

    $this->post($activationUrl, [
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])->assertRedirect('/panel');

    $seller = $seller->fresh();
    expect($seller->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('new-secure-password', $seller->password))->toBeTrue();

    $this->postJson('/api/v1/auth/login', [
        'email' => $seller->email,
        'password' => 'new-secure-password',
        'device_name' => 'BSPOS',
    ])->assertOk()->assertJsonStructure(['access_token']);
});
