<?php

use App\Enums\UserStatus;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a verified active seller can connect BSPOS and retrieve only their shops', function () {
    $user = User::factory()->create(['email' => 'seller@example.com']);
    $shop = Shop::factory()->create(['user_id' => $user->id]);
    Shop::factory()->create();

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'SELLER@EXAMPLE.COM',
        'password' => 'password',
        'device_name' => 'Caja principal',
    ]);

    $login
        ->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', (string) $user->id)
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonStructure(['access_token']);

    $token = $login->json('access_token');

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'name' => 'Caja principal',
    ]);
    expect($user->tokens()->latest('id')->first()->abilities)->toBe(['catalog:read', 'pos:write']);

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertExactJson([
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);

    $this->withToken($token)
        ->getJson('/api/v1/shops')
        ->assertOk()
        ->assertExactJson([[
            'id' => $shop->public_id,
            'name' => $shop->name,
            'slug' => $shop->slug,
        ]]);
});

test('BSPOS connection rejects invalid inactive and unverified accounts', function () {
    $active = User::factory()->create(['email' => 'active@example.com']);
    $suspended = User::factory()->create([
        'email' => 'suspended@example.com',
        'status' => UserStatus::Suspended,
    ]);
    $unverified = User::factory()->unverified()->create(['email' => 'unverified@example.com']);

    $this->postJson('/api/v1/auth/login', [
        'email' => $active->email,
        'password' => 'incorrect-password',
    ])->assertUnauthorized();

    $this->postJson('/api/v1/auth/login', [
        'email' => $suspended->email,
        'password' => 'password',
    ])->assertForbidden();

    $this->postJson('/api/v1/auth/login', [
        'email' => $unverified->email,
        'password' => 'password',
    ])->assertForbidden();
});

test('BSPOS tokens stop working when the account becomes suspended', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Caja principal', ['catalog:read'])->plainTextToken;
    $user->update(['status' => UserStatus::Suspended]);

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('message', 'Esta cuenta no está activa.');
});

test('unauthenticated API requests return JSON without requiring an accept header', function () {
    $this->get('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
});
