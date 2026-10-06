<?php

namespace App\Providers;

use App\Models\User;
use App\Services\BusinessPresentationService;
use App\Services\BusinessProfileService;
use App\Services\SellerMenuService;
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
        // Reuse navigation policy results during one request without leaking
        // shop/user state between requests in long-running workers.
        $this->app->scoped(BusinessProfileService::class);
        $this->app->scoped(SellerMenuService::class);
        $this->app->scoped(BusinessPresentationService::class);
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

        RateLimiter::for('catalog-media', function (Request $request) {
            $maxAttempts = (int) config('catalog.rate_limits.account.catalog_media.max_attempts', 30);
            $minutes = max(1, (int) ceil(((int) config('catalog.rate_limits.account.catalog_media.decay_seconds', 60)) / 60));

            return Limit::perMinutes($minutes, $maxAttempts)->by(($request->user()?->id ?? 'guest').':'.$request->ip());
        });
    }
}
