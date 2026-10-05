<?php

use App\Http\Middleware\EnsureApiAppVersion;
use App\Http\Middleware\EnsureSellerMenuAccess;
use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cloudflare and Apache terminate TLS before forwarding requests to PHP.
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'menu' => EnsureSellerMenuAccess::class,
            'app.version' => EnsureApiAppVersion::class,
        ]);
        $middleware->append(SecurityHeadersMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $exception): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
