<?php

namespace App\Http\Controllers;

use App\Enums\ProductImageProcessingStatus;
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

        $readyImages = fn ($query) => $query
            ->where('processing_status', ProductImageProcessingStatus::Ready)
            ->orderBy('sort_order')
            ->orderBy('id');

        $product->loadMissing([
            'images' => $readyImages,
            'inventory',
            'decantProducts.inventory',
            'sourceProduct.inventory',
            'sourceProduct.decantProducts.inventory',
            'attributeValues.attributeDefinition',
        ]);

        $decantOptions = $product->isDecant()
            ? ($product->sourceProduct?->decantProducts ?? collect())
            : $product->decantProducts;
        $decantOptions = $decantOptions
            ->filter(fn (Product $decant) => $decant->moderation_status === ProductModerationStatus::Active
                && (! $decant->inventory?->track_inventory || $this->availableDecantQuantity($decant) > 0))
            ->sortBy('volume_ml')
            ->values();
        $decantOptions->each(fn (Product $decant) => $decant->setAttribute(
            'public_available_decants',
            $this->availableDecantQuantity($decant),
        ));

        $relatedQuery = $shop->products()
            ->where('id', '!=', $product->id)
            ->where('moderation_status', ProductModerationStatus::Active)
            ->with(['images' => $readyImages, 'inventory']);

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

        return view('products.show', compact('shop', 'product', 'relatedProducts', 'decantOptions'));
    }

    private function availableDecantQuantity(Product $decant): int
    {
        if (! $decant->inventory?->track_inventory) {
            return $decant->availability_status === \App\Enums\ProductAvailabilityStatus::Available ? 10000 : 0;
        }

        $source = $decant->sourceProduct;
        $sourceInventory = $source?->inventory;

        if ($source && $sourceInventory?->track_inventory && (int) $decant->volume_ml > 0) {
            $availableMl = $sourceInventory->reserved_decant_ml ?? $sourceInventory->available_ml;
            if ($availableMl === null) {
                $availableMl = match ($source->sale_unit) {
                    'bottle' => (int) $source->volume_ml * (int) $sourceInventory->stock_quantity,
                    'ml' => (int) $sourceInventory->stock_quantity,
                    default => 0,
                };
            }

            return intdiv(max(0, (int) $availableMl), (int) $decant->volume_ml);
        }

        return max(0, (int) ($decant->inventory?->stock_quantity ?? 0));
    }
}
