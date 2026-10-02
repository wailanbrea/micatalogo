<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shops = $request->user()
            ->shops()
            ->orderBy('name')
            ->get(['public_id', 'name', 'slug'])
            ->map(fn ($shop) => [
                'id' => $shop->public_id,
                'name' => $shop->name,
                'slug' => $shop->slug,
            ])
            ->values();

        return response()->json($shops);
    }
}
