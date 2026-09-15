<?php

namespace App\Services;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CatalogSearchService
{
    /**
     * Search products with relevance ranking and filters.
     *
     * @param  array{q?: ?string, category?: ?string, categoria?: ?string, shipping?: ?string, sort?: ?string, min_price?: ?numeric, max_price?: ?numeric, stock?: ?string, shop?: ?string, perPage?: ?int}  $filters
     */
    public function search(array $filters = []): LengthAwarePaginator
    {
        $term = isset($filters['q']) ? trim(strip_tags((string) $filters['q'])) : '';
        $categorySlug = $filters['categoria'] ?? $filters['category'] ?? null;
        $shipping = $filters['shipping'] ?? null;
        $sort = $filters['sort'] ?? null;
        $minPrice = isset($filters['min_price']) && is_numeric($filters['min_price']) ? (float) $filters['min_price'] : null;
        $maxPrice = isset($filters['max_price']) && is_numeric($filters['max_price']) ? (float) $filters['max_price'] : null;
        $stock = $filters['stock'] ?? null;
        $shopFilter = $filters['tienda'] ?? $filters['shop'] ?? null;
        $perPage = (int) ($filters['perPage'] ?? 20);

        $query = Product::query()
            ->with(['shop', 'images' => fn ($q) => $q->orderBy('sort_order')])
            ->where('moderation_status', ProductModerationStatus::Active)
            ->whereHas('shop', function ($shopQuery) use ($shipping, $shopFilter): void {
                $shopQuery->where('status', 'active');

                if ($shipping === 'available') {
                    $shopQuery->where('offers_shipping', true);
                } elseif ($shipping === 'unavailable') {
                    $shopQuery->where('offers_shipping', false);
                }

                if ($shopFilter) {
                    $shopQuery->where(function ($sq) use ($shopFilter): void {
                        $sq->where('slug', $shopFilter)->orWhere('public_id', $shopFilter);
                    });
                }
            });

        if ($categorySlug) {
            $query->whereHas('globalCategory', function ($catQuery) use ($categorySlug): void {
                $catQuery->where('slug', $categorySlug)
                    ->orWhere('id', $categorySlug)
                    ->orWhereHas('parent', fn ($p) => $p->where('slug', $categorySlug));
            });
        }

        if ($minPrice !== null && $minPrice >= 0) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null && $maxPrice > 0) {
            $query->where('price', '<=', $maxPrice);
        }

        if ($stock === 'available') {
            $query->where(function ($products) {
                $products->whereHas('inventory', fn ($inventory) => $inventory
                    ->where('track_inventory', true)
                    ->where('stock_quantity', '>', 0))
                    ->orWhere(function ($untracked) {
                        $untracked->where('availability_status', ProductAvailabilityStatus::Available)
                            ->where(function ($inventory) {
                                $inventory->whereDoesntHave('inventory')
                                    ->orWhereHas('inventory', fn ($inventory) => $inventory->where('track_inventory', false));
                            });
                    });
            });
        }

        if ($term !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);

            $query->where(function ($searchGroup) use ($escaped): void {
                $searchGroup->where('products.name', 'like', "%{$escaped}%")
                    ->orWhere('products.description', 'like', "%{$escaped}%")
                    ->orWhereHas('shop', function ($shopQ) use ($escaped): void {
                        $shopQ->where('name', 'like', "%{$escaped}%");
                    });
            });

            if (! in_array($sort, ['price_asc', 'price_desc', 'name_asc'], true)) {
                $query->orderByRaw('
                    CASE
                        WHEN LOWER(products.name) = LOWER(?) THEN 1
                        WHEN LOWER(products.name) LIKE LOWER(?) THEN 2
                        WHEN LOWER(products.name) LIKE LOWER(?) THEN 3
                        ELSE 4
                    END ASC
                ', [$term, "{$escaped}%", "%{$escaped}%"]);
            }
        }

        if ($sort === 'price_asc') {
            $query->orderBy('price', 'asc');
        } elseif ($sort === 'price_desc') {
            $query->orderBy('price', 'desc');
        } elseif ($sort === 'name_asc') {
            $query->orderBy('products.name', 'asc');
        } else {
            $query->latest('products.id');
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
