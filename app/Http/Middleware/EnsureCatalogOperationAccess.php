<?php

namespace App\Http\Middleware;

use App\Models\Product;
use App\Services\CatalogAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCatalogOperationAccess
{
    public function handle(Request $request, Closure $next, string $operation = 'write'): Response
    {
        $shop = $request->route('shop');
        $user = $request->user();
        abort_unless($shop && $user && $user->canSellAtShop($shop), 404);
        $access = app(CatalogAccessService::class);
        $product = $request->route('product');
        if ($product !== null && ! $product instanceof Product) {
            $product = $shop->products()->withTrashed()->where(fn ($query) => $query->where('public_id', $product)->orWhere('id', $product))->firstOrFail();
        }
        if ($operation === 'images') {
            abort_unless($access->canManagePhotos($shop, $user, $product), 403);
        } elseif ($operation === 'image-search') {
            $permissions = $access->payload($shop, $user);
            abort_unless(in_array(true, $permissions, true) && ($permissions['manage_products'] || $permissions['manage_services'] || $permissions['manage_photos'] || $permissions['manage_decants']), 403);
        } else {
            $input = $request->all();
            if ($request->route('combo') === true) {
                $input['is_combo'] = true;
            }
            $access->assertWrite($shop, $user, $product, $input);
        }

        return $next($request);
    }
}
