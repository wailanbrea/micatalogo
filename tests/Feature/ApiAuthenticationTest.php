<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopPaymentAccount;
use App\Models\ShopSeller;
use App\Models\User;
use App\Services\BusinessPresentationService;
use App\Services\SellerMenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

test('Android settings expose active payment accounts without exposing disabled ones', function () {
    $user = User::factory()->create(['email' => 'settings@example.com']);
    $shop = Shop::factory()->for($user)->create();
    ShopPaymentAccount::create([
        'shop_id' => $shop->id,
        'name' => 'Cuenta principal',
        'bank_name' => 'Banco de prueba',
        'account_number' => '000123',
        'account_holder' => 'BSolutions',
        'is_active' => true,
    ]);
    ShopPaymentAccount::create([
        'shop_id' => $shop->id,
        'name' => 'Cuenta desactivada',
        'is_active' => false,
    ]);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'settings@example.com',
        'password' => 'password',
        'device_name' => 'Configuración',
    ]);

    $this->withToken($login->json('access_token'))
        ->getJson('/api/v1/shops/'.$shop->public_id.'/settings')
        ->assertOk()
        ->assertJsonPath('google.target_score', 90)
        ->assertJsonPath('google.checks.0.key', 'logo')
        ->assertJsonPath('payment_accounts.0.name', 'Cuenta principal')
        ->assertJsonMissing(['name' => 'Cuenta desactivada']);
});

test('Android can upload a shop logo through the authenticated settings API', function () {
    Storage::fake('public');
    $owner = User::factory()->create(['email' => 'logo-api@example.com']);
    $shop = Shop::factory()->for($owner)->create(['logo_object_key' => null]);
    $token = $owner->createToken('BSPOS', ['shop_settings:write'])->plainTextToken;

    $response = $this->withToken($token)->post(
        "/api/v1/shops/{$shop->public_id}/media/logo",
        ['logo' => UploadedFile::fake()->image('logo.png', 500, 500)],
    );

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Logo actualizado.')
        ->assertJsonPath('logo_url', fn ($url): bool => is_string($url) && $url !== '');

    $shop->refresh();
    expect($shop->logo_object_key)->toEndWith('.webp');
    expect(Storage::disk('public')->exists($shop->logo_object_key))->toBeTrue();
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
            'business_type' => 'general_retail',
            'business_type_label' => 'Tienda general',
            'presentation' => app(BusinessPresentationService::class)->resolve($shop),
            'product_fields' => ['sku', 'barcode', 'cost_price', 'price', 'stock'],
            'categories' => ['Productos', 'Ofertas', 'Otros'],
            'capabilities' => [
                'products' => 'enabled',
                'catalog' => 'enabled',
                'inventory' => 'enabled',
                'sales' => 'enabled',
                'customers' => 'enabled',
                'credit' => 'enabled',
                'cash' => 'enabled',
                'expenses' => 'enabled',
                'finance' => 'enabled',
                'services' => 'enabled',
                'sku' => 'enabled',
                'barcode' => 'enabled',
                'public_catalog' => 'enabled',
                'decants' => 'disabled',
                'perfume_fields' => 'disabled',
                'wholesale' => 'disabled',
                'brand' => 'disabled',
                'shipping' => 'disabled',
            ],
            'profile_version' => 1,
            'quota' => [
                'plan' => 'free',
                'plan_label' => 'Gratis',
                'product_count' => 0,
                'product_limit' => 250,
                'products_remaining' => 250,
                'image_limit' => 1,
                'can_add_products' => true,
                'user_count' => 1,
                'user_limit' => 1,
                'users_remaining' => 0,
                'seller_count' => 0,
                'seller_limit' => 1,
                'sellers_remaining' => 1,
                'can_add_users' => false,
                'can_add_sellers' => true,
                'additional_seat_price_usd' => 5,
                'features' => ['catalog', 'whatsapp_orders', 'basic_inventory', 'bulk_import', 'expenses'],
            ],
            'menu_permissions' => app(SellerMenuService::class)->visibleForUser($shop, $user),
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
        ->assertExactJson(['menu_permissions' => ['sales', 'customers']]);

    $this->actingAs($seller, 'sanctum')
        ->getJson('/api/v1/shops')
        ->assertOk()
        ->assertJsonPath('0.menu_permissions', ['sales', 'customers'])
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

test('feature modules are available to an owner but obey seller menu permissions', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create();
    $seller = User::factory()->create(['email' => 'feature-seller@example.com']);
    ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'commission_type' => 'percentage',
        'commission_value' => 5,
        'is_active' => true,
        'menu_permissions' => ['sales'],
    ]);

    $this->withToken($owner->createToken('owner')->plainTextToken)
        ->getJson("/api/v1/shops/{$shop->public_id}/features/quotes")
        ->assertOk()
        ->assertJsonPath('feature_key', 'quotes')
        ->assertJsonPath('feature.title', 'Cotizaciones')
        ->assertJsonPath('module.kind', 'table');

    expect(app(SellerMenuService::class)->visibleForUser($shop, $seller))->not->toContain('quotes');

    $response = $this->actingAs($seller, 'sanctum')
        ->getJson("/api/v1/shops/{$shop->public_id}/features/quotes");
    $response->assertForbidden();
});

test('a shop owner can export reports through the authenticated Android API', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create();
    Product::factory()->for($shop)->create(['name' => 'Reporte móvil']);

    $this->withToken($owner->createToken('report-export')->plainTextToken)
        ->get("/api/v1/shops/{$shop->public_id}/reports/export?format=csv")
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertHeader('content-disposition');
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
        ->assertJsonPath('seller.menu_permissions', ['sales', 'products', 'printers']);

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

test('the mobile shops endpoint keeps administrators scoped to their accessible shops', function () {
    $admin = User::factory()->admin()->create();
    $owned = Shop::factory()->for($admin)->create(['name' => 'Owned shop']);
    $otherOwner = User::factory()->create();
    Shop::factory()->for($otherOwner)->create(['name' => 'Other shop']);
    $token = $admin->createToken('BSPOS', ['catalog:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/shops')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $owned->public_id);
});

test('only administrators can manage every shop from the mobile API', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create(['name' => 'Store owner', 'email' => 'owner@example.com']);
    $shop = Shop::factory()->for($owner)->create([
        'name' => 'Remote store',
        'whatsapp_country_code' => '1',
        'whatsapp_number' => '8095551234',
    ]);
    $seller = User::factory()->create();

    $this->actingAs($seller, 'sanctum')
        ->getJson('/api/v1/admin/shops')
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/admin/shops')
        ->assertOk()
        ->assertJsonPath('0.id', $shop->public_id)
        ->assertJsonPath('0.owner_email', 'owner@example.com')
        ->assertJsonPath('0.product_count', 0);

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/v1/admin/shops/{$shop->public_id}", [
            'name' => 'Updated store',
            'whatsapp_country_code' => '+1',
            'whatsapp_number' => '(809) 555-9876',
            'status' => 'suspended',
        ])
        ->assertOk()
        ->assertJsonPath('name', 'Updated store')
        ->assertJsonPath('status', 'suspended');

    expect($shop->fresh()->only(['name', 'whatsapp_country_code', 'whatsapp_number', 'status']))
        ->toBe([
            'name' => 'Updated store',
            'whatsapp_country_code' => '1',
            'whatsapp_number' => '8095559876',
            'status' => 'suspended',
        ]);
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

test('BSPOS can revoke the current API token when logging out', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Caja principal', ['catalog:read'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertUnauthorized();

    expect($user->tokens()->count())->toBe(0);
});

test('unauthenticated API requests return JSON without requiring an accept header', function () {
    $this->get('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
});
