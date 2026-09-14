<?php

namespace App\Http\Controllers;

use App\Models\GlobalCategory;
use App\Models\Shop;
use App\Services\CatalogSearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogHomeController extends Controller
{
    public function __invoke(Request $request, CatalogSearchService $searchService): View
    {
        $query = $request->string('q')->trim()->value();
        $categorySlug = $request->string('categoria')->trim()->value();
        $shipping = $request->string('shipping')->trim()->value();
        $sort = $request->string('sort')->trim()->value();
        $minPrice = $request->string('min_price')->trim()->value();
        $maxPrice = $request->string('max_price')->trim()->value();
        $stock = $request->string('stock')->trim()->value();
        $shopSlug = $request->string('tienda')->trim()->value();

        $filters = array_filter([
            'q' => $query,
            'categoria' => $categorySlug,
            'shipping' => $shipping,
            'sort' => $sort,
            'min_price' => $minPrice !== '' ? $minPrice : null,
            'max_price' => $maxPrice !== '' ? $maxPrice : null,
            'stock' => $stock,
            'shop' => $shopSlug,
            'perPage' => 20,
        ], fn ($val) => $val !== '' && $val !== null);

        $products = $searchService->search($filters);

        $categories = GlobalCategory::query()
            ->where('status', 'active')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->limit(12)
            ->get();

        $selectedCategory = $categorySlug
            ? $categories->firstWhere('slug', $categorySlug) ?? GlobalCategory::where('slug', $categorySlug)->first()
            : null;

        $shops = Shop::query()
            ->where('status', 'active')
            ->latest('id')
            ->limit(6)
            ->get();

        $allShops = Shop::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $hasActiveFilters = ! empty($query)
            || ! empty($categorySlug)
            || ! empty($shipping)
            || ! empty($minPrice)
            || ! empty($maxPrice)
            || ! empty($stock)
            || ! empty($shopSlug)
            || (! empty($sort) && $sort !== 'latest');

        return view('welcome', [
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'products' => $products,
            'shops' => $shops,
            'allShops' => $allShops,
            'searchQuery' => $query,
            'shipping' => $shipping,
            'sort' => $sort,
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice,
            'stock' => $stock,
            'shopSlug' => $shopSlug,
            'hasActiveFilters' => $hasActiveFilters,
        ]);
    }
}
