<?php

namespace App\Http\Controllers;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Models\GlobalCategory;
use App\Models\Shop;
use App\Services\ImageProcessingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SellerBulkProductController extends Controller
{
    public function create(Shop $shop): View
    {
        $usedQuota = $shop->products()->count();
        $maxQuota = (int) config('catalog.limits.free_products_per_shop', 100);
        $remainingQuota = max(0, $maxQuota - $usedQuota);

        $categories = $shop->categories()->orderBy('name')->get();
        $globalCategories = GlobalCategory::whereNull('parent_id')->with('children')->orderBy('sort_order')->get();

        return view('seller.products.bulk', compact(
            'shop',
            'usedQuota',
            'maxQuota',
            'remainingQuota',
            'categories',
            'globalCategories'
        ));
    }

    public function store(Request $request, Shop $shop, ImageProcessingService $imageService): RedirectResponse
    {
        $request->validate([
            'products' => ['required', 'array', 'min:1', 'max:30'],
            'products.*.name' => ['required', 'string', 'max:120'],
            'products.*.price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'products.*.shop_category_id' => ['nullable', 'exists:shop_categories,id'],
            'products.*.global_category_id' => ['nullable', 'exists:global_categories,id'],
            'products.*.description' => ['nullable', 'string', 'max:2000'],
            'products.*.image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $batch = $request->input('products', []);
        $createdCount = 0;

        DB::transaction(function () use ($shop, $request, $batch, $imageService, &$createdCount) {
            $lockedShop = Shop::whereKey($shop->getKey())->lockForUpdate()->firstOrFail();
            $currentCount = $lockedShop->products()->count();
            $batchCount = count($batch);
            $maxLimit = (int) config('catalog.limits.free_products_per_shop', 100);

            if ($currentCount + $batchCount > $maxLimit) {
                throw ValidationException::withMessages([
                    'products' => "La subida de {$batchCount} productos supera el límite de {$maxLimit} productos por tienda (tienes {$currentCount}).",
                ]);
            }

            foreach ($batch as $index => $itemData) {
                $slug = $this->resolveProductSlug($lockedShop, $itemData['name']);

                $product = $lockedShop->products()->create([
                    'name' => trim($itemData['name']),
                    'slug' => $slug,
                    'price' => $itemData['price'],
                    'currency' => config('catalog.currency', 'DOP'),
                    'description' => $itemData['description'] ?? null,
                    'shop_category_id' => $itemData['shop_category_id'] ?? null,
                    'global_category_id' => $itemData['global_category_id'] ?? null,
                    'availability_status' => ProductAvailabilityStatus::Available,
                    'moderation_status' => ProductModerationStatus::Active,
                    'published_at' => now(),
                ]);

                if ($request->hasFile("products.{$index}.image")) {
                    $file = $request->file("products.{$index}.image");
                    $imageService->storeTempAndDispatch($product, $file);
                }

                $createdCount++;
            }
        });

        return redirect()
            ->route('seller.shops.products.index', $shop)
            ->with('status', "Se publicaron {$createdCount} productos exitosamente.");
    }

    private function resolveProductSlug(Shop $shop, string $name): string
    {
        $base = Str::slug($name) ?: 'producto';
        $slug = $base;
        $suffix = 2;

        while (
            $shop->products()
                ->where('slug', $slug)
                ->withTrashed()
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
