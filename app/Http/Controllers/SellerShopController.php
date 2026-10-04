<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShopRequest;
use App\Http\Requests\UpdateShopRequest;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\User;
use App\Services\ImageProcessingService;
use App\Services\MediaStorageService;
use App\Services\PlanLimitsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SellerShopController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $viewAll = $user->isAdmin() && $request->query('view') === 'all';

        $query = $viewAll
            ? Shop::with(['user', 'products'])->withCount('products')
            : Shop::query()
                ->where(function ($query) use ($user): void {
                    $query->where('user_id', $user->id)
                         ->orWhereHas('members', fn ($members) => $members
                             ->where('user_id', $user->id)
                             ->where('is_active', true))
                         ->orWhereHas('sellers', fn ($sellers) => $sellers
                            ->where('user_id', $user->id)
                            ->where('is_active', true));
                })
                ->with(['user', 'products'])
                ->withCount('products');

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->where(function ($q) use ($escaped): void {
                $q->where('name', 'like', "%{$escaped}%")
                    ->orWhere('slug', 'like', "%{$escaped}%")
                    ->orWhere('whatsapp_number', 'like', "%{$escaped}%")
                    ->orWhereHas('user', function ($uq) use ($escaped): void {
                        $uq->where('name', 'like', "%{$escaped}%")
                            ->orWhere('email', 'like', "%{$escaped}%");
                    });
            });
        }

        $status = $request->query('status');
        if (in_array($status, ['active', 'suspended'], true)) {
            $query->where('status', $status);
        }

        $shipping = $request->query('shipping');
        if ($shipping === 'yes') {
            $query->where('offers_shipping', true);
        } elseif ($shipping === 'no') {
            $query->where('offers_shipping', false);
        }

        $sort = $request->query('sort');
        if ($sort === 'name_asc') {
            $query->orderBy('name', 'asc');
        } elseif ($sort === 'name_desc') {
            $query->orderBy('name', 'desc');
        } elseif ($sort === 'products_desc') {
            $query->orderByDesc('products_count');
        } elseif ($sort === 'oldest') {
            $query->oldest();
        } else {
            $query->latest();
        }

        $shops = $query->paginate(12)->withQueryString();

        if ($viewAll) {
            $storageByShop = ProductImage::query()
                ->join('products', 'products.id', '=', 'product_images.product_id')
                ->whereIn('products.shop_id', $shops->getCollection()->map->getKey())
                ->selectRaw('products.shop_id, COALESCE(SUM(product_images.size_bytes), 0) as storage_bytes, COUNT(product_images.id) as storage_image_count')
                ->groupBy('products.shop_id')
                ->get()
                ->keyBy('shop_id');

            $shops->getCollection()->each(function (Shop $shop) use ($storageByShop): void {
                $storage = $storageByShop->get($shop->getKey());
                $storageBytes = (int) ($storage?->storage_bytes ?? 0);

                $shop->setAttribute('storage_bytes', $storageBytes);
                $shop->setAttribute('storage_mb', round($storageBytes / (1024 * 1024), 2));
                $shop->setAttribute('storage_image_count', (int) ($storage?->storage_image_count ?? 0));
            });
        }

        $hasActiveFilters = $search !== ''
            || in_array($status, ['active', 'suspended'], true)
            || in_array($shipping, ['yes', 'no'], true)
            || ($sort && $sort !== 'latest');

        return view('seller.dashboard', [
            'shops' => $shops,
            'viewAll' => $viewAll,
            'ownedCount' => Shop::ownedBy($user)->count(),
            'totalCount' => Shop::count(),
            'search' => $search,
            'status' => $status,
            'shipping' => $shipping,
            'sort' => $sort,
            'hasActiveFilters' => $hasActiveFilters,
        ]);
    }

    public function create(): View
    {
        return view('seller.shops.form', ['shop' => new Shop]);
    }

    public function store(StoreShopRequest $request, ImageProcessingService $imageService, MediaStorageService $mediaStorage, PlanLimitsService $limits): RedirectResponse
    {
        $shop = DB::transaction(function () use ($request, $limits): Shop {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $activeShops = $user->shops()->where('status', 'active')->count();

            $limit = $limits->activeShopLimit($user);
            abort_if($activeShops >= $limit, 422, "Tu plan {$limits->planFor($user)->label()} permite {$limit} tienda activa.");

            return $user->shops()->create($this->attributes($request->validated()));
        });

        if ($request->hasFile('logo')) {
            $logoKey = $imageService->processAndStoreShopLogo($shop, $request->file('logo'), $mediaStorage);
            $shop->update(['logo_object_key' => $logoKey]);
        }

        if ($request->hasFile('cover')) {
            $coverKey = $imageService->processAndStoreShopCover($shop, $request->file('cover'), $mediaStorage);
            $shop->update(['cover_object_key' => $coverKey]);
        }

        return to_route('seller.shops.edit', $shop)->with('status', 'Tienda creada.');
    }

    public function edit(Shop $shop): View
    {
        return view('seller.shops.form', compact('shop'));
    }

    public function update(UpdateShopRequest $request, Shop $shop, ImageProcessingService $imageService, MediaStorageService $mediaStorage): RedirectResponse
    {
        $attributes = $this->attributes($request->validated(), $shop);

        if ($request->boolean('remove_logo')) {
            $imageService->deleteShopLogo($shop, $mediaStorage);
            $attributes['logo_object_key'] = null;
        } elseif ($request->hasFile('logo')) {
            $attributes['logo_object_key'] = $imageService->processAndStoreShopLogo($shop, $request->file('logo'), $mediaStorage);
        }

        if ($request->boolean('remove_cover')) {
            $imageService->deleteShopCover($shop, $mediaStorage);
            $attributes['cover_object_key'] = null;
        } elseif ($request->hasFile('cover')) {
            $attributes['cover_object_key'] = $imageService->processAndStoreShopCover($shop, $request->file('cover'), $mediaStorage);
        }

        $shop->update($attributes);

        return to_route('seller.shops.edit', $shop)->with('status', 'Cambios guardados.');
    }

    public function destroy(Shop $shop): RedirectResponse
    {
        $shop->delete();

        return to_route('seller.dashboard')->with('status', 'Tienda eliminada.');
    }

    private function attributes(array $input, ?Shop $shop = null): array
    {
        return [
            ...$input,
            'slug' => $this->availableSlug($input['slug'] ?: $input['name'], $shop),
            'instagram' => $input['instagram'] ?: null,
            'offers_shipping' => $input['offers_shipping'] ?? false,
        ];
    }

    private function availableSlug(string $value, ?Shop $shop = null): string
    {
        $base = Str::slug($value) ?: 'tienda';
        $slug = $base;
        $suffix = 2;
        while (Shop::withTrashed()
            ->where('slug', $slug)
            ->when($shop, fn ($shops) => $shops->whereKeyNot($shop))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
