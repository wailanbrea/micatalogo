<?php

use App\Enums\UserStatus;
use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the Android update manifest is publicly available', function () {
    config()->set('bspos.android_update', [
        'version_code' => 6,
        'version_name' => '1.0.5',
        'minimum_supported_version_code' => 6,
        'apk_url' => 'https://micatalogo.bsolutions.dev/downloads/bspos-1.0.5.apk',
        'apk_sha256' => str_repeat('a', 64),
        'release_notes' => 'Actualizacion de seguridad.',
    ]);

    $this->getJson('/api/v1/app-updates/android')
        ->assertOk()
        ->assertExactJson(config('bspos.android_update'));
});

test('the API rejects Android clients below the configured minimum version', function () {
    config()->set('bspos.android_update.minimum_supported_version_code', 7);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'old-client@example.com',
        'password' => 'password',
    ], ['X-MiCatalogo-Version-Code' => '6'])
        ->assertStatus(426)
        ->assertJsonPath('update_required', true)
        ->assertJsonPath('minimum_supported_version_code', 7);
});

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
        ->assertJsonPath('user.role', 'seller')
        ->assertJsonStructure(['access_token']);

    $token = $login->json('access_token');

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'name' => 'Caja principal',
    ]);
    expect($user->tokens()->latest('id')->first()->abilities)->toBe(['catalog:read', 'pos:write', 'customers:read', 'customers:write']);

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertExactJson([
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'seller',
        ]);

    $this->withToken($token)
        ->getJson('/api/v1/shops')
        ->assertOk()
        ->assertExactJson([[
            'id' => $shop->public_id,
            'name' => $shop->name,
            'slug' => $shop->slug,
            'quota' => [
                'plan' => 'free',
                'plan_label' => 'Gratis',
                'product_count' => 0,
                'product_limit' => 100,
                'products_remaining' => 100,
                'image_limit' => 3,
                'can_add_products' => true,
            ],
            'menu_permissions' => array_keys(config('bspos.seller_menu_options')),
            'can_manage_sellers' => true,
            'sellers' => [],
        ]]);
});

test('a shop owner can configure the menus returned to an assigned seller', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create();
    $seller = User::factory()->create(['email' => 'assigned@example.com']);
    $assignment = ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'commission_type' => 'percentage',
        'commission_value' => 5,
        'is_active' => true,
    ]);
    $ownerToken = $owner->createToken('test', ['catalog:read'])->plainTextToken;

    $this->withToken($ownerToken)
        ->putJson("/api/v1/shops/{$shop->public_id}/sellers/{$assignment->id}/menus", [
            'menu_permissions' => ['sales', 'customers'],
        ])
        ->assertOk()
        ->assertExactJson(['menu_permissions' => ['sales', 'products', 'printers', 'customers']]);

    $this->actingAs($seller, 'sanctum')
        ->getJson('/api/v1/shops')
        ->assertOk()
        ->assertJsonPath('0.menu_permissions', ['sales', 'products', 'printers', 'customers'])
        ->assertJsonPath('0.can_manage_sellers', false);

    $sellerToken = $seller->createToken('test', ['catalog:read'])->plainTextToken;

    $this->withToken($sellerToken)
        ->putJson("/api/v1/shops/{$shop->public_id}/sellers/{$assignment->id}/menus", [
            'menu_permissions' => ['settings'],
        ])
        ->assertForbidden();

    $this->withToken($sellerToken)
        ->postJson("/api/v1/shops/{$shop->public_id}/sellers", [
            'email' => 'not-allowed@example.com',
            'commission_type' => 'percentage',
            'commission_value' => '5',
        ])
        ->assertForbidden();
});

test('a shop owner can create a seller from the Android API', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create();
    $token = $owner->createToken('android')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/shops/{$shop->public_id}/sellers", [
            'email' => 'new-seller@example.com',
            'commission_type' => 'percentage',
            'commission_value' => '7.5',
        ])
        ->assertCreated()
        ->assertJsonPath('seller.email', 'new-seller@example.com')
        ->assertJsonPath('seller.menu_permissions', app(\App\Services\SellerMenuService::class)->assignableKeys());

    $seller = User::query()->where('email', 'new-seller@example.com')->firstOrFail();
    $this->assertDatabaseHas('shop_sellers', [
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'commission_type' => 'percentage',
        'commission_value' => '7.50',
        'is_active' => true,
    ]);
});

test('the API identifies an administrator role', function () {
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => UserRole::Admin,
    ]);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertOk();

    $this->withToken($login->json('access_token'))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('role', 'admin');
});

test('an authenticated user can update their profile', function () {
    $user = User::factory()->create(['email' => 'profile@example.com']);
    $token = $user->createToken('profile-test')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/me', [
            'name' => 'Perfil actualizado',
            'email' => 'updated-profile@example.com',
        ])
        ->assertOk()
        ->assertJsonPath('name', 'Perfil actualizado')
        ->assertJsonPath('email', 'updated-profile@example.com');

    expect($user->fresh()->name)->toBe('Perfil actualizado')
        ->and($user->fresh()->email)->toBe('updated-profile@example.com');
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
