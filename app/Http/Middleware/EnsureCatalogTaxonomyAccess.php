<?php

namespace App\Http\Middleware;

use App\Services\CatalogTaxonomyAccessService;
use Closure;
use Illuminate\Http\Request;

class EnsureCatalogTaxonomyAccess
{
    public function handle(Request $request, Closure $next)
    {
        $shop = $request->route('shop');
        abort_unless($shop && $request->user() && $request->user()->canSellAtShop($shop), 404);
        abort_unless(app(CatalogTaxonomyAccessService::class)->canOpen($shop, $request->user()), 403);
        return $next($request);
    }
}
