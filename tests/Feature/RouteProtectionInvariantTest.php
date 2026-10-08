<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('every mutating route is authenticated or explicitly classified as public infrastructure', function () {
    $publicMutationUris = [
        'api/v1/auth/login',
        'login',
        'register',
        'forgot-password',
        'reset-password',
        'email/verification-code',
        'email/verify-code',
        'invitacion/vendedor/{user}/{hash}/{shop}',
        'livewire-5c589461/update',
        'livewire-5c589461/upload-file',
        'reportar',
        'soporte',
        'storage/{path}',
        'tienda/{shop}/pedido',
    ];

    $violations = [];

    foreach (app('router')->getRoutes() as $route) {
        $methods = array_map('strtoupper', $route->methods());
        if (! array_intersect($methods, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            continue;
        }

        $middleware = $route->gatherMiddleware();
        $hasAuthorizationBoundary = collect($middleware)->contains(function (string $entry): bool {
            return Str::contains($entry, [
                'Authenticate',
                'auth:',
                'auth',
                'Authorize',
                'abilities:',
                'menu:',
                'verified',
                'signed',
            ]);
        });

        if (! $hasAuthorizationBoundary && ! in_array($route->uri(), $publicMutationUris, true)) {
            $violations[] = [
                'method' => implode('|', $methods),
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'middleware' => $middleware,
            ];
        }
    }

    expect($violations)->toBe([]);
});

test('every tenant-scoped web mutation declares a shop authorization boundary', function () {
    $violations = [];

    foreach (app('router')->getRoutes() as $route) {
        if ($route->uri() !== 'panel/tiendas/{shop}'
            && ! Str::startsWith($route->uri(), 'panel/tiendas/{shop}/')) {
            continue;
        }

        $methods = array_map('strtoupper', $route->methods());
        if (! array_intersect($methods, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            continue;
        }

        $middleware = $route->gatherMiddleware();
        $hasShopBoundary = collect($middleware)->contains(function (string $entry): bool {
            $hasShopAuthorization = (
                Str::startsWith($entry, ['Illuminate\\Auth\\Middleware\\Authorize:', 'can:'])
                && Str::contains($entry, ',shop')
            );
            $hasMenuAuthorization = Str::startsWith(
                $entry,
                ['App\\Http\\Middleware\\EnsureSellerMenuAccess:', 'menu:'],
            );

            return $hasShopAuthorization || $hasMenuAuthorization;
        });

        if (! $hasShopBoundary) {
            $violations[] = [
                'method' => implode('|', $methods),
                'uri' => $route->uri(),
                'middleware' => $middleware,
            ];
        }
    }

    expect($violations)->toBe([]);
});
