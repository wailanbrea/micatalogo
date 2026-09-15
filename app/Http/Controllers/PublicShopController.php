<?php

namespace App\Http\Controllers;

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
            ->with(['images' => fn ($query) => $query->orderBy('sort_order'), 'inventory'])
            ->latest('id');

        if ($selectedCategory) {
            $productsQuery->where('shop_category_id', $selectedCategory->id);
        }

        $searchQuery = $request->string('q')->trim()->value();
        if ($searchQuery !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchQuery);
            $productsQuery->where(function ($q) use ($escaped) {
                $q->where('name', 'like', "%{$escaped}%")
                    ->orWhere('description', 'like', "%{$escaped}%");
            });
        }

        $products = $productsQuery->paginate(16)->withQueryString();

        return view('shops.show', compact('shop', 'categories', 'selectedCategory', 'products', 'searchQuery'));
    }
}
