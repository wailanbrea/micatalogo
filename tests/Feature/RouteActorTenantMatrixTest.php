<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\ShopSeller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('ACL-001..015 actor and tenant matrix protects catalog finance customers and global admin routes', function () {
    $ownerA = User::factory()->create(['email' => 'owner-a@example.com']);
    $ownerB = User::factory()->create(['email' => 'owner-b@example.com']);
    $manager = User::factory()->create(['email' => 'manager-a@example.com']);
    $seller = User::factory()->create(['email' => 'seller-a@example.com']);
    $accountant = User::factory()->create(['email' => 'accountant-a@example.com']);
    $admin = User::factory()->admin()->create(['email' => 'admin-a@example.com']);
    $shopA = Shop::factory()->for($ownerA)->create(['name' => 'Tienda A']);
    $shopB = Shop::factory()->for($ownerB)->create(['name' => 'Tienda B']);

    ShopMember::create(['shop_id' => $shopA->id, 'user_id' => $manager->id, 'role' => 'manager', 'is_active' => true]);
    ShopMember::create(['shop_id' => $shopA->id, 'user_id' => $accountant->id, 'role' => 'accountant', 'is_active' => true]);
    ShopSeller::create([
        'shop_id' => $shopA->id,
        'user_id' => $seller->id,
        'commission_type' => 'percentage',
        'commission_value' => '5.00',
        'is_active' => true,
        'menu_permissions' => ['sales'],
    ]);

    expect($shopA->user_id)->toBe($ownerA->id)
        ->and($shopB->user_id)->toBe($ownerB->id);

    $as = function (User $user, array $abilities = []) {
        // Sanctum's guard is request-scoped in production; reset it between
        // sequential HTTP assertions so this matrix cannot reuse the prior actor.
        app('auth')->forgetGuards();

        return $this->withToken($user->createToken('qa-matrix-'.uniqid(), $abilities)->plainTextToken);
    };

    $catalogUrl = "/api/v1/shops/{$shopA->public_id}/catalog";
    $otherCatalogUrl = "/api/v1/shops/{$shopB->public_id}/catalog";
    $financeUrl = "/api/v1/shops/{$shopA->public_id}/finance/summary";
    $customerUrl = "/api/v1/shops/{$shopA->public_id}/customers";

    $this->getJson($catalogUrl)->assertUnauthorized();
    $as($ownerA, ['catalog:read'])->getJson($catalogUrl)->assertOk();
    $as($manager, ['catalog:read'])->getJson($catalogUrl)->assertOk();
    $as($seller, ['catalog:read'])->getJson($catalogUrl)->assertOk();
    $as($ownerB, ['catalog:read'])->getJson($catalogUrl)->assertNotFound();
    $as($seller, ['catalog:read'])->getJson($otherCatalogUrl)->assertNotFound();
    $as($admin, ['catalog:read'])->getJson($catalogUrl)->assertNotFound();

    $as($ownerA)->getJson($financeUrl)->assertOk();
    $as($manager)->getJson($financeUrl)->assertOk();
    $as($accountant)->getJson($financeUrl)->assertOk();
    $as($seller)->getJson($financeUrl)->assertForbidden();
    $as($ownerB)->getJson($financeUrl)->assertForbidden();

    $as($ownerA, ['customers:read'])->getJson($customerUrl)->assertOk();
    $as($accountant, ['customers:read'])->getJson($customerUrl)->assertForbidden();
    $as($seller, ['customers:read'])->getJson($customerUrl)->assertForbidden();

    $as($ownerA)->getJson('/api/v1/admin/shops')->assertForbidden();
    $as($ownerB)->getJson('/api/v1/admin/shops')->assertForbidden();
    $as($admin)->getJson('/api/v1/admin/shops')->assertOk();

    $revokedToken = $ownerA->createToken('qa-revocation', ['catalog:read'])->plainTextToken;
    $ownerA->update(['status' => UserStatus::Suspended]);
    app('auth')->forgetGuards();
    $this->withToken($revokedToken)
        ->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('message', 'Esta cuenta no está activa.');
    app('auth')->forgetGuards();
    $this->withToken($revokedToken)
        ->getJson($catalogUrl)
        ->assertForbidden()
        ->assertJsonPath('message', 'Esta cuenta no está activa.');

    expect($admin->role)->toBe(UserRole::Admin)
        ->and($ownerA->ownsShop($shopA))->toBeTrue()
        ->and($ownerB->ownsShop($shopA))->toBeFalse();
});

test('ACL-016 every shop-scoped JSON mutation rejects an owner from another tenant before mutation', function () {
    $ownerA = User::factory()->create(['email' => 'matrix-owner-a@example.com']);
    $ownerB = User::factory()->create(['email' => 'matrix-owner-b@example.com']);
    $shopA = Shop::factory()->for($ownerA)->create(['name' => 'Matriz Tienda A']);
    $token = $ownerB->createToken('qa-cross-tenant-mutations', ['*'])->plainTextToken;

    $mutations = [
        ['POST', 'accountant-access'],
        ['POST', 'authorization-requests'],
        ['POST', 'authorization-requests/missing-request/approve'],
        ['POST', 'authorization-requests/missing-request/reject'],
        ['PUT', 'attributes/999999999'],
        ['POST', 'cash-sessions/open'],
        ['POST', 'cash-sessions/missing-session/close'],
        ['POST', 'cash-sessions/missing-session/movements'],
        ['POST', 'customers'],
        ['POST', 'customers/missing-customer/payments'],
        ['POST', 'customers/missing-customer/adjustments'],
        ['POST', 'expenses'],
        ['POST', 'expenses/missing-expense/payments'],
        ['POST', 'finance/day-close'],
        ['POST', 'inventory-import'],
        ['POST', 'inventory-import/preview'],
        ['POST', 'inventory-import/missing-session/confirm'],
        ['POST', 'media/logo'],
        ['POST', 'mobile-operations'],
        ['POST', 'orders/missing-order/confirm'],
        ['POST', 'partners'],
        ['POST', 'partners/missing-partner/transactions'],
        ['POST', 'pos-sales'],
        ['POST', 'pricing/recalculate'],
        ['POST', 'products/999999999/pricing-rule'],
        ['POST', 'products/999999999/pricing-approval'],
        ['POST', 'purchases'],
        ['POST', 'purchases/preview'],
        ['POST', 'purchases/missing-document/receive'],
        ['POST', 'quotes'],
        ['POST', 'quotes/missing-quote/convert'],
        ['POST', 'sellers'],
        ['POST', 'support/conversations'],
        ['PUT', 'sellers/missing-seller/menus'],
        ['PUT', 'settings'],
        ['PUT', 'menu-visibility'],
        ['POST', 'suppliers'],
    ];

    $canonicalSuffixes = [
        'authorization-requests/missing-request/approve' => 'authorization-requests/{authorizationRequest}/approve',
        'authorization-requests/missing-request/reject' => 'authorization-requests/{authorizationRequest}/reject',
        'attributes/999999999' => 'attributes/{attribute}',
        'cash-sessions/missing-session/close' => 'cash-sessions/{session}/close',
        'cash-sessions/missing-session/movements' => 'cash-sessions/{session}/movements',
        'customers/missing-customer/adjustments' => 'customers/{customer}/adjustments',
        'customers/missing-customer/payments' => 'customers/{customer}/payments',
        'expenses/missing-expense/payments' => 'expenses/{expense}/payments',
        'inventory-import/missing-session/confirm' => 'inventory-import/{session}/confirm',
        'orders/missing-order/confirm' => 'orders/{order}/confirm',
        'partners/missing-partner/transactions' => 'partners/{partner}/transactions',
        'products/999999999/pricing-approval' => 'products/{product}/pricing-approval',
        'products/999999999/pricing-rule' => 'products/{product}/pricing-rule',
        'purchases/missing-document/receive' => 'purchases/{document}/receive',
        'quotes/missing-quote/convert' => 'quotes/{quote}/convert',
        'sellers/missing-seller/menus' => 'sellers/{seller}/menus',
    ];
    $expectedShopMutations = array_map(
        fn (array $mutation) => [$mutation[0], $canonicalSuffixes[$mutation[1]] ?? $mutation[1]],
        $mutations,
    );

    $actualShopMutations = collect(app('router')->getRoutes())
        ->flatMap(function ($route) {
            if (! Str::startsWith($route->uri(), 'api/v1/shops/{shop}/')) {
                return [];
            }

            return collect($route->methods())
                ->filter(fn (string $method) => in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true))
                ->map(fn (string $method) => [$method, Str::after($route->uri(), 'api/v1/shops/{shop}/')])
                ->all();
        })
        ->values()
        ->all();

    expect($actualShopMutations)
        ->toEqualCanonicalizing($expectedShopMutations, 'La matriz ACL-016 debe cubrir todas las mutaciones API nuevas con alcance de tienda.');

    foreach ($mutations as [$method, $suffix]) {
        app('auth')->forgetGuards();
        $response = $this->{strtolower($method).'Json'}(
            "/api/v1/shops/{$shopA->public_id}/{$suffix}",
            [],
            ['Authorization' => 'Bearer '.$token],
        );

        expect($response->status())
            ->toBeIn([403, 404], "{$method} {$suffix} crossed tenant boundary with status {$response->status()}");
    }

    app('auth')->forgetGuards();
    $this->putJson(
        "/api/v1/admin/shops/{$shopA->public_id}",
        [],
        ['Authorization' => 'Bearer '.$token],
    )->assertForbidden();
});

test('ACL-017 the mobile feature read model rejects another tenant before module visibility checks', function () {
    $ownerA = User::factory()->create(['email' => 'feature-owner-a@example.com']);
    $ownerB = User::factory()->create(['email' => 'feature-owner-b@example.com']);
    $shopA = Shop::factory()->for($ownerA)->create(['name' => 'Feature Tienda A']);
    $token = $ownerB->createToken('qa-cross-tenant-feature', ['*'])->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/shops/{$shopA->public_id}/features/sales")
        ->assertNotFound();
});

test('ACL-018 every tenant-scoped web mutation rejects an owner from another tenant at runtime', function () {
    $ownerA = User::factory()->create(['email' => 'web-matrix-owner-a@example.com']);
    $ownerB = User::factory()->create(['email' => 'web-matrix-owner-b@example.com']);
    $shopA = Shop::factory()->for($ownerA)->create(['name' => 'Web Matriz Tienda A']);

    $routes = collect(app('router')->getRoutes())
        ->filter(function ($route): bool {
            if ($route->uri() !== 'panel/tiendas/{shop}'
                && ! Str::startsWith($route->uri(), 'panel/tiendas/{shop}/')) {
                return false;
            }

            return collect($route->methods())
                ->intersect(['POST', 'PUT', 'PATCH', 'DELETE'])
                ->isNotEmpty();
        })
        ->values();

    expect($routes)->toHaveCount(60, 'La matriz runtime debe cubrir todas las mutaciones web por tienda.');

    $placeholderValues = [
        'shop' => $shopA->public_id,
        'product' => 'missing-qa-product',
        'attribute' => 'missing-qa-attribute',
        'category' => 'missing-qa-category',
        'customer' => 'missing-qa-customer',
        'expense' => 'missing-qa-expense',
        'session' => 'missing-qa-session',
        'quote' => 'missing-qa-quote',
        'supplier' => 'missing-qa-supplier',
        'document' => 'missing-qa-document',
        'partner' => 'missing-qa-partner',
        'seller' => 'missing-qa-seller',
        'member' => 'missing-qa-member',
        'paymentAccount' => 'missing-qa-payment-account',
        'order' => 'missing-qa-order',
        'authorizationRequest' => 'missing-qa-authorization',
        'image' => 'missing-qa-image',
        'catalogImage' => 'missing-qa-catalog-image',
        'feature' => 'missing-qa-feature',
    ];

    foreach ($routes as $route) {
        $uri = preg_replace_callback('/\{([^}]+)\}/', function (array $match) use ($placeholderValues): string {
            $parameter = trim($match[1], '?');

            return $placeholderValues[$parameter] ?? 'missing-qa-parameter';
        }, $route->uri());

        foreach (array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) as $method) {
            app('auth')->forgetGuards();
            $response = $this->actingAs($ownerB)->call($method, '/'.$uri, [], [], [], [
                'HTTP_ACCEPT' => 'text/html',
            ]);

            expect($response->status())->toBeIn(
                [403, 404],
                $method.' '.$route->uri().' crossed the web tenant boundary with status '.$response->status(),
            );
        }
    }
});

test('ACL-019 every global admin mutation rejects a normal user and reaches the admin boundary', function () {
    $seller = User::factory()->create(['email' => 'admin-matrix-seller@example.com']);
    $admin = User::factory()->admin()->create(['email' => 'admin-matrix-admin@example.com']);

    $routes = collect(app('router')->getRoutes())
        ->filter(function ($route): bool {
            if (! Str::startsWith($route->uri(), 'admin/')) {
                return false;
            }

            return collect($route->methods())
                ->intersect(['POST', 'PUT', 'PATCH', 'DELETE'])
                ->isNotEmpty();
        })
        ->values();

    expect($routes)->toHaveCount(10, 'La matriz runtime debe cubrir todas las mutaciones administrativas globales.');

    $placeholderValues = [
        'category' => 'missing-qa-global-category',
        'product' => 'missing-qa-global-product',
        'report' => 'missing-qa-global-report',
        'support' => 'missing-qa-global-support',
        'shop' => 'missing-qa-global-shop',
        'user' => 'missing-qa-global-user',
    ];

    foreach ($routes as $route) {
        $uri = preg_replace_callback('/\{([^}]+)\}/', function (array $match) use ($placeholderValues): string {
            $parameter = trim($match[1], '?');

            return $placeholderValues[$parameter] ?? 'missing-qa-parameter';
        }, $route->uri());

        foreach (array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) as $method) {
            app('auth')->forgetGuards();
            $sellerResponse = $this->actingAs($seller)
                ->call($method, '/'.$uri, [], [], [], ['HTTP_ACCEPT' => 'text/html']);

            expect($sellerResponse->status())->toBeIn(
                [403, 404],
                $method.' '.$route->uri().' exposed an admin mutation with status '.$sellerResponse->status(),
            );

            app('auth')->forgetGuards();
            $response = $this->actingAs($admin)
                ->call($method, '/'.$uri, [], [], [], ['HTTP_ACCEPT' => 'text/html']);

            expect($response->status())->toBeIn(
                [200, 302, 404, 422],
                $method.' '.$route->uri().' blocked an administrator with status '.$response->status(),
            );
        }
    }
});
