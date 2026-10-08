<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Shop;
use App\Services\PlanLimitsService;
use App\Services\BusinessCapabilityService;
use App\Services\BusinessPresentationService;
use App\Services\SellerMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function show(
        Request $request,
        Shop $shop,
        PlanLimitsService $limits,
        BusinessCapabilityService $capabilities,
        BusinessPresentationService $presentation,
        SellerMenuService $menus,
    ): JsonResponse
    {
        abort_unless($request->user()->canSellAtShop($shop), 404);

        // Cost is an internal financial datum. Keep the response shape stable
        // for Android, but never expose it to a seller without finance access.
        $canViewSensitiveFinance = $menus->canManage($shop, $request->user())
            || $request->user()->isAdmin()
            || $request->user()->ownsShop($shop)
            || in_array('finance', $menus->visibleForUser($shop, $request->user()), true);

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
            ->with(['inventory', 'sourceProduct', 'primaryImage', 'comboItems.component.inventory'])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => $this->productPayload($product, $canViewSensitiveFinance))
            ->values();

        return response()->json([
            'shop' => [
                'id' => $shop->public_id,
                'name' => $shop->name,
                'slug' => $shop->slug,
                ...$capabilities->payload($shop),
                'presentation' => $presentation->resolve($shop),
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
    private function productPayload(Product $product, bool $canViewSensitiveFinance = false): array
    {
        $inventory = $product->inventory;
        $stockQuantity = $product->isCombo() ? $product->comboAvailableQuantity() : $inventory?->stock_quantity;

        return [
            'id' => $product->public_id,
            'slug' => $product->slug,
            'category_id' => $product->shop_category_id ? (string) $product->shop_category_id : null,
            'source_product_id' => $product->sourceProduct?->public_id,
            'name' => $product->name,
            'internal_code' => $product->product_code,
            'barcode' => $product->barcode,
            'brand' => $product->brand,
            'description' => $product->description,
            'image_url' => $product->primaryImage?->url,
            'thumbnail_url' => $product->primaryImage?->thumbnail_url,
            'price' => number_format($product->currentPrice(), 2, '.', ''),
            'regular_price' => $product->price,
            'wholesale_price' => $product->wholesale_price,
            'currency' => $product->currency,
            'sale_unit' => $product->sale_unit,
            'is_combo' => $product->isCombo(),
            'combo_items' => $product->isCombo() ? $product->comboItems->map(fn ($item): array => [
                'product_id' => $item->component?->public_id,
                'name' => $item->component?->name,
                'quantity' => (int) $item->quantity,
            ])->values()->all() : [],
            'volume_ml' => $product->volume_ml,
            'availability_status' => $product->inventory_status,
            'moderation_status' => $product->moderation_status->value,
            'inventory' => [
                'track_inventory' => $product->isCombo() || ($inventory?->track_inventory ?? false),
                'stock_quantity' => $stockQuantity,
                'available_ml' => $inventory?->available_ml,
                'opened_bottles' => $inventory?->opened_bottles,
                'cost_price' => $canViewSensitiveFinance ? $inventory?->cost_price : null,
                'low_stock_threshold' => $inventory?->low_stock_threshold,
            ],
            'updated_at' => $product->updated_at->toISOString(),
        ];
    }
}
