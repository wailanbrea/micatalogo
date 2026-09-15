<?php

namespace App\Http\Controllers;

use App\Enums\ProductModerationStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Services\MetricRecordingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicProductController extends Controller
{
    public function show(Request $request, Shop $shop, Product $product, MetricRecordingService $metricService): View
    {
        abort_unless($shop->status === 'active' && $product->shop_id === $shop->id && $product->moderation_status === ProductModerationStatus::Active, 404);

        $metricService->recordProductPageView($product, $request);

        $product->loadMissing(['images' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'), 'inventory']);

        $relatedQuery = $shop->products()
            ->where('id', '!=', $product->id)
            ->where('moderation_status', ProductModerationStatus::Active)
            ->with(['images' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'), 'inventory']);

        if ($product->shop_category_id) {
            $sameCategory = (clone $relatedQuery)
                ->where('shop_category_id', $product->shop_category_id)
                ->latest('id')
                ->take(4)
                ->get();

            $relatedProducts = $sameCategory->isNotEmpty()
                ? $sameCategory
                : $relatedQuery->latest('id')->take(4)->get();
        } else {
            $relatedProducts = $relatedQuery->latest('id')->take(4)->get();
        }

        return view('products.show', compact('shop', 'product', 'relatedProducts'));
    }
}
