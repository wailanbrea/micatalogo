<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CatalogMediaService;
use Illuminate\Http\JsonResponse;

class CatalogMediaController extends Controller
{
    public function show(string $barcode, CatalogMediaService $mediaService): JsonResponse
    {
        $catalogProduct = $mediaService->resolveBarcode($barcode);
        if (! $catalogProduct) {
            return response()->json(['message' => 'El barcode no es válido.'], 422);
        }

        $catalogProduct->load('images');

        return response()->json([
            'provider' => $catalogProduct->provider,
            'barcode' => $catalogProduct->barcode,
            'status' => $catalogProduct->lookup_status,
            'product' => [
                'name' => $catalogProduct->name,
                'generic_name' => $catalogProduct->generic_name,
                'brands' => $catalogProduct->brands,
                'categories' => $catalogProduct->categories,
                'quantity' => $catalogProduct->quantity,
            ],
            'images' => $catalogProduct->images->where('processing_status', 'ready')->values()->map(fn ($image) => [
                'id' => $image->id,
                'url' => $image->url,
                'thumbnail_url' => $image->thumbnail_url,
                'license' => $image->license,
                'attribution' => $image->attribution,
            ]),
            'last_lookup_at' => $catalogProduct->last_lookup_at?->toISOString(),
        ]);
    }
}
