<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Shop;
use App\Services\PlanLimitsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function show(Request $request, Shop $shop, PlanLimitsService $limits): JsonResponse
    {
        abort_unless($request->user()->canSellAtShop($shop), 404);

        $categories = $shop->categories()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($category) => [
                'id' => (string) $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'sort_order' => $category->sort_order,
                'status' => $category->status->value,
                'updated_at' => $category->updated_at->toISOString(),
            ])
            ->values();

        $products = $shop->products()
            ->with(['inventory', 'sourceProduct', 'primaryImage'])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => $this->productPayload($product))
            ->values();

        return response()->json([
            'shop' => [
                'id' => $shop->public_id,
                'name' => $shop->name,
                'slug' => $shop->slug,
                'quota' => $limits->shopQuota($shop, $products->count()),
            ],
            'categories' => $categories,
            'products' => $products,
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(Product $product): array
    {
        $inventory = $product->inventory;

        return [
            'id' => $product->public_id,
            'category_id' => $product->shop_category_id ? (string) $product->shop_category_id : null,
            'source_product_id' => $product->sourceProduct?->public_id,
            'name' => $product->name,
            'internal_code' => $product->product_code,
            'barcode' => $product->barcode,
            'brand' => $product->brand,
            'description' => $product->description,
            'image_url' => $product->primaryImage?->url,
            'thumbnail_url' => $product->primaryImage?->thumbnail_url,
            'price' => $product->price,
            'currency' => $product->currency,
            'sale_unit' => $product->sale_unit,
            'volume_ml' => $product->volume_ml,
            'availability_status' => $product->inventory_status,
            'moderation_status' => $product->moderation_status->value,
            'inventory' => [
                'track_inventory' => $inventory?->track_inventory ?? false,
                'stock_quantity' => $inventory?->stock_quantity,
                'available_ml' => $inventory?->available_ml,
                'cost_price' => $inventory?->cost_price,
                'low_stock_threshold' => $inventory?->low_stock_threshold,
            ],
            'updated_at' => $product->updated_at->toISOString(),
        ];
    }
}
