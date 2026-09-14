<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleAccountRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $limiter = match ($request->route()?->getName()) {
            'register.store' => 'registration',
            'password.email', 'password.update' => 'password_reset',
            default => null,
        };

        if ($limiter === null) {
            return $next($request);
        }

        $configuration = config("catalog.rate_limits.account.{$limiter}");
        $key = "{$limiter}|{$request->ip()}";

        if (RateLimiter::tooManyAttempts($key, $configuration['max_attempts'])) {
            abort(429, 'Demasiadas solicitudes. Intentalo de nuevo mas tarde.');
        }

        RateLimiter::hit($key, $configuration['decay_seconds']);

        return $next($request);
    }
}
