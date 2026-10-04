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
        $visibleMenus = $shop && $user
            ? app(SellerMenuService::class)->forUser($shop, $user)
            : [];

        abort_unless(in_array($menu, $visibleMenus, true), 403);

        return $next($request);
    }
}
