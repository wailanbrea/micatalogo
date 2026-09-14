<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->isAdmin() ? true : null;
        });

        Gate::define('admin', fn (User $user) => $user->isAdmin());

        RateLimiter::for('report', function (Request $request) {
            $maxAttempts = (int) config('catalog.rate_limits.account.reports.max_attempts', 5);
            $minutes = max(1, (int) ceil(((int) config('catalog.rate_limits.account.reports.decay_seconds', 900)) / 60));

            return Limit::perMinutes($minutes, $maxAttempts)->by($request->ip());
        });
    }
}
