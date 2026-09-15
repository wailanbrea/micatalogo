<?php

namespace App\Http\Controllers;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Models\Shop;
use App\Services\MetricRecordingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicShopController extends Controller
{
    public function show(Request $request, Shop $shop, MetricRecordingService $metricService): View
    {
        abort_unless($shop->status === 'active', 404);

        $metricService->recordShopPageView($shop, $request);

        $selectedCategorySlug = $request->query('categoria');
        $searchQuery = $request->string('q')->trim()->value();
        $minPrice = $request->filled('min_price') && is_numeric($request->query('min_price')) ? (float) $request->query('min_price') : null;
        $maxPrice = $request->filled('max_price') && is_numeric($request->query('max_price')) ? (float) $request->query('max_price') : null;
        $stock = $request->query('stock');
        $sort = $request->query('sort', 'latest');

        $categories = $shop->categories()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->withCount(['products' => function ($query) {
                $query->where('moderation_status', ProductModerationStatus::Active);
            }])
            ->get();

        $selectedCategory = null;
        if ($selectedCategorySlug) {
            $selectedCategory = $categories->firstWhere('slug', $selectedCategorySlug);
        }

        $productsQuery = $shop->products()
            ->where('moderation_status', ProductModerationStatus::Active)
            ->with(['images' => fn ($query) => $query->orderBy('sort_order'), 'inventory']);

        if ($selectedCategory) {
            $productsQuery->where('shop_category_id', $selectedCategory->id);
        }

        if ($searchQuery !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchQuery);
            $productsQuery->where(function ($q) use ($escaped) {
                $q->where('name', 'like', "%{$escaped}%")
                    ->orWhere('description', 'like', "%{$escaped}%");
            });
        }

        if ($minPrice !== null && $minPrice >= 0) {
            $productsQuery->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null && $maxPrice > 0) {
            $productsQuery->where('price', '<=', $maxPrice);
        }

        if ($stock === 'available') {
            $productsQuery->where(function ($query) {
                $query->whereHas('inventory', fn ($inventory) => $inventory
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

        if ($sort === 'price_asc') {
            $productsQuery->orderBy('price', 'asc');
        } elseif ($sort === 'price_desc') {
            $productsQuery->orderBy('price', 'desc');
        } elseif ($sort === 'name_asc') {
            $productsQuery->orderBy('name', 'asc');
        } else {
            $productsQuery->latest('id');
        }

        $hasActiveFilters = $searchQuery !== ''
            || $selectedCategory !== null
            || $minPrice !== null
            || $maxPrice !== null
            || $stock === 'available'
            || ($sort && $sort !== 'latest');

        $products = $productsQuery->paginate(16)->withQueryString();

        return view('shops.show', compact(
            'shop',
            'categories',
            'selectedCategory',
            'products',
            'searchQuery',
            'minPrice',
            'maxPrice',
            'stock',
            'sort',
            'hasActiveFilters'
        ));
    }
}
