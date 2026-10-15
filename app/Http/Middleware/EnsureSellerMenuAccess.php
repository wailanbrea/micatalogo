<?php

namespace App\Http\Middleware;

use App\Services\SellerMenuService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSellerMenuAccess
{
    public function handle(Request $request, Closure $next, string $menu): Response
    {
        $shop = $request->route('shop');
        $user = $request->user();
        if ($request->is('api/*') && $shop && $user) {
            // Keep inaccessible tenants hidden, including deactivated sellers.
            abort_unless($user->isAdmin() || $user->canSellAtShop($shop) || $user->isActiveShopAccountant($shop), 404);
        }
        $visibleMenus = $shop && $user
            ? app(SellerMenuService::class)->visibleForUser($shop, $user)
            : [];

        // A union is used only for shared read contexts; mutations retain their individual key.
        abort_unless(array_intersect(explode('|', $menu), $visibleMenus) !== [], 403);

        return $next($request);
    }
}
