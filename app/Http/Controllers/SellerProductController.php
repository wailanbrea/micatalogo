<?php

namespace App\Http\Controllers;

use App\Enums\ProductModerationStatus;
use App\Http\Requests\ProductRequest;
use App\Models\GlobalCategory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Services\ImageProcessingService;
use App\Services\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SellerProductController extends Controller
{
    public function index(Request $request, Shop $shop): View
    {
        $search = $request->string('q')->value();
        $status = $request->string('status')->value();

        $products = $shop->products()
            ->with(['globalCategory', 'shopCategory', 'images'])
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($status, fn ($q) => $q->where('availability_status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $maxProducts = (int) config('catalog.free.max_products_per_shop', 100);
        $totalProducts = $shop->products()->count();
        $trashedCount = $shop->products()->onlyTrashed()->count();

        return view('seller.products.index', [
            'shop' => $shop,
            'products' => $products,
            'search' => $search,
            'status' => $status,
            'totalProducts' => $totalProducts,
            'maxProducts' => $maxProducts,
            'trashedCount' => $trashedCount,
        ]);
    }

    public function create(Shop $shop): View
    {
        return view('seller.products.form', [
            'shop' => $shop,
            'product' => new Product(['currency' => config('catalog.currency', 'DOP')]),
            'globalCategories' => GlobalCategory::query()->where('status', 'active')->orderBy('sort_order')->orderBy('name')->get(),
            'shopCategories' => $shop->categories()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(ProductRequest $request, Shop $shop): RedirectResponse
    {
        $product = DB::transaction(function () use ($request, $shop): Product {
            $lockedShop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            $max = (int) config('catalog.free.max_products_per_shop', 100);

            abort_if(
                $lockedShop->products()->count() >= $max,
                422,
                'Has alcanzado el limite maximo de productos para tu tienda.'
            );

            $attributes = $this->attributes($request->validated(), $lockedShop);

            if (($attributes['moderation_status'] ?? null) === ProductModerationStatus::Active->value) {
                $attributes['published_at'] = now();
            }

            return $lockedShop->products()->create($attributes);
        });

        return to_route('seller.shops.products.index', $shop)->with('status', 'Producto creado exitosamente.');
    }

    public function edit(Shop $shop, Product $product): View
    {
        return view('seller.products.form', [
            'shop' => $shop,
            'product' => $product,
            'globalCategories' => GlobalCategory::query()->where('status', 'active')->orderBy('sort_order')->orderBy('name')->get(),
            'shopCategories' => $shop->categories()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function update(ProductRequest $request, Shop $shop, Product $product): RedirectResponse
    {
        $attributes = $this->attributes($request->validated(), $shop, $product);

        if (($attributes['moderation_status'] ?? null) === ProductModerationStatus::Active->value && ! $product->published_at) {
            $attributes['published_at'] = now();
        }

        $product->update($attributes);

        return to_route('seller.shops.products.index', $shop)->with('status', 'Producto actualizado.');
    }

    public function destroy(Shop $shop, Product $product): RedirectResponse
    {
        $product->delete();

        return to_route('seller.shops.products.index', $shop)->with('status', 'Producto enviado a la papelera.');
    }

    public function restore(Request $request, Shop $shop, int|string $product): RedirectResponse
    {
        $productModel = $shop->products()->onlyTrashed()
            ->where(fn ($q) => $q->where('public_id', $product)->orWhere('id', $product))
            ->firstOrFail();

        Gate::authorize('restore', $productModel);

        $productModel->restore();

        return to_route('seller.shops.products.index', $shop)->with('status', 'Producto restaurado.');
    }

    public function uploadImage(Request $request, Shop $shop, Product $product, ImageProcessingService $imageService): RedirectResponse
    {
        $maxImages = (int) config('catalog.free.max_images_per_product', 3);
        abort_if(
            $product->images()->count() >= $maxImages,
            422,
            "Tu plan gratuito permite un máximo de {$maxImages} imágenes por producto."
        );

        $request->validate([
            'image' => ['required', 'file', 'max:'.((int) config('catalog.uploads.max_file_size_mb', 10) * 1024)],
        ]);

        $file = $request->file('image');
        $imageService->storeTempAndDispatch($product, $file);

        return back()->with('status', 'Imagen subida correctamente. Se está procesando en segundo plano.');
    }

    public function destroyImage(Request $request, Shop $shop, Product $product, ProductImage $image, MediaStorageService $mediaStorage): RedirectResponse
    {
        abort_unless($image->product_id === $product->id && $product->shop_id === $shop->id, 404);

        $mediaDisk = $mediaStorage->disk();
        if ($image->object_key && $mediaDisk->exists($image->object_key)) {
            $mediaDisk->delete($image->object_key);
        }
        if ($image->thumbnail_object_key && $mediaDisk->exists($image->thumbnail_object_key)) {
            $mediaDisk->delete($image->thumbnail_object_key);
        }

        $image->delete();

        return back()->with('status', 'Imagen eliminada.');
    }

    private function attributes(array $input, Shop $shop, ?Product $product = null): array
    {
        $base = Str::slug($input['slug'] ?? $input['name']) ?: 'producto';
        $slug = $base;
        $suffix = 2;

        while (
            $shop->products()
                ->where('slug', $slug)
                ->when($product, fn ($q) => $q->whereKeyNot($product->id))
                ->withTrashed()
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return [
            ...$input,
            'slug' => $slug,
            'currency' => config('catalog.currency', 'DOP'),
        ];
    }
}
