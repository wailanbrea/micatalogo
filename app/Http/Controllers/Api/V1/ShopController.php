<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\PlanLimitsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request, PlanLimitsService $limits): JsonResponse
    {
        $shops = Shop::query()
            ->where(function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id)
                    ->orWhereHas('sellers', fn ($sellers) => $sellers
                        ->where('user_id', $request->user()->id)
                        ->where('is_active', true));
            })
            ->with('user')
            ->withCount('products')
            ->orderBy('name')
            ->get(['id', 'user_id', 'public_id', 'name', 'slug'])
            ->map(fn ($shop) => [
                'id' => $shop->public_id,
                'name' => $shop->name,
                'slug' => $shop->slug,
                'quota' => $limits->shopQuota($shop, $shop->products_count),
            ])
            ->values();

        return response()->json($shops);
    }
}
