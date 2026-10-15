<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Shop;
use App\Services\PlanLimitsService;
use App\Services\BusinessCapabilityService;
use App\Services\BusinessPresentationService;
use App\Services\DecantInventoryService;
use App\Services\SellerMenuService;
use App\Services\ShopRecordSyncService;
use App\Services\CatalogAccessService;
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
        DecantInventoryService $decants,
    ): JsonResponse
    {
        abort_unless($request->user()->canSellAtShop($shop), 404);
        $snapshotAccess = app(CatalogAccessService::class)->snapshotAccess($shop, $request->user());
        abort_unless($snapshotAccess['physical'] || $snapshotAccess['services'] || $snapshotAccess['decants'], 403);

        // Cost is an internal financial datum. Keep the response shape stable
        // for Android, but never expose it to a seller without finance access.
        $canViewSensitiveFinance = $menus->canManage($shop, $request->user())
            || $request->user()->isAdmin()
            || $request->user()->ownsShop($shop)
            || in_array('finance', $menus->visibleForUser($shop, $request->user()), true);

        $recordSync = app(ShopRecordSyncService::class);
        $taxonomy = app(\App\Services\CatalogTaxonomyAccessService::class);
        $categoryMetadata = $recordSync->categoryMetadata($shop);
        $categories = $shop->categories()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn ($category) => $taxonomy->canReadCategory($category, $request->user()))
            ->map(fn ($category) => [
                'id' => (string) $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'sort_order' => $category->sort_order,
                'status' => $category->status->value,
                'updated_at' => $category->updated_at->toISOString(),
                'client_record_uuid' => ($categoryMetadata[$category->id] ?? null)?->record_uuid,
                'description' => ($categoryMetadata[$category->id] ?? null)?->payload['description'] ?? null,
                'icon' => ($categoryMetadata[$category->id] ?? null)?->payload['icon'] ?? 'category_default',
                'is_deleted' => ($categoryMetadata[$category->id] ?? null)?->payload['is_deleted'] ?? false,
                'scope' => $taxonomy->categoryScope($category),
                'revision' => ($categoryMetadata[$category->id] ?? null)?->payload['revision'] ?? null,
                'permissions' => ['update' => $taxonomy->canModifyCategory($category, $request->user())],
            ])
            ->values();

        $productUnits = $recordSync->productUnits($shop);
        $routeSync = app(\App\Services\ShopRouteSyncService::class);
        $routeStock = $routeSync->stock($shop);
        $products = $shop->products()
            ->when(! ($snapshotAccess['physical'] && $snapshotAccess['services'] && $snapshotAccess['decants']), fn ($query) => $query->where(function ($types) use ($snapshotAccess): void {
                if ($snapshotAccess['physical']) {
                    $types->orWhereNull('sale_unit')->orWhereNotIn('sale_unit', ['service', 'decant']);
                }
                if ($snapshotAccess['services']) {
                    $types->orWhere('sale_unit', 'service');
                }
                if ($snapshotAccess['decants']) {
                    $types->orWhere('sale_unit', 'decant');
                }
            }))
            ->with(['inventory', 'sourceProduct', 'primaryImage', 'comboItems.component.inventory'])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($canViewSensitiveFinance, $productUnits, $routeStock, $decants) {
                $payload = $this->productPayload($product, $canViewSensitiveFinance, $decants);
                $reserved = $routeStock->where('product_id', $product->public_id)->sum('quantity');
                if ($reserved > 0) $payload['inventory']['stock_quantity'] = max(0, $payload['inventory']['stock_quantity'] - $reserved);
                return [...$payload, 'unit_uuid' => $productUnits[$product->public_id] ?? null];
            })
            ->values();

        return response()->json([
            'shop' => [
                'id' => $shop->public_id,
                'name' => $shop->name,
                'slug' => $shop->slug,
                ...$capabilities->payload($shop),
                'presentation' => $presentation->resolve($shop),
                'quota' => $limits->shopQuota($shop, $snapshotAccess['physical'] && $snapshotAccess['services'] && $snapshotAccess['decants'] ? $products->count() : $shop->products()->count()),
                'taxonomy_permissions' => $taxonomy->payload($shop, $request->user()),
            ],
            'categories' => $categories,
            'products' => $products,
            'store_records' => array_merge($recordSync->snapshot($shop, $request->user()),
                $snapshotAccess['routes'] ? $routeSync->snapshot($shop, $canViewSensitiveFinance) : []),
            'catalog_scope' => array_intersect_key($snapshotAccess, array_flip(['physical', 'services', 'decants'])),
            'route_stock' => $snapshotAccess['routes'] ? $routeStock->map(fn ($row) => $canViewSensitiveFinance ? $row : array_replace($row, ['unit_cost_cents' => 0]))->all() : [],
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(Product $product, bool $canViewSensitiveFinance = false, ?DecantInventoryService $decants = null): array
    {
        $inventory = $product->inventory;
        $stockQuantity = $product->isCombo() ? $product->comboAvailableQuantity() : $inventory?->stock_quantity;
        if ($decants && $product->isDecant() && $product->sourceProduct && $decants->managed($product->sourceProduct)) {
            $stockQuantity = $decants->sellable($product);
        }

        return [
            'id' => $product->public_id,
            'slug' => $product->slug,
            'category_id' => $product->shop_category_id ? (string) $product->shop_category_id : null,
            'source_product_id' => $product->sourceProduct?->public_id,
            'source_product_name' => $product->sourceProduct?->name,
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
                'reserved_decant_ml' => $inventory?->reserved_decant_ml,
                'opened_bottles' => $inventory?->opened_bottles,
                'cost_price' => $canViewSensitiveFinance ? $inventory?->cost_price : null,
                'low_stock_threshold' => $inventory?->low_stock_threshold,
            ],
            'updated_at' => $product->updated_at->toISOString(),
        ];
    }
}
