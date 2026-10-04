<?php

namespace App\Http\Controllers;

use App\Jobs\ResolveProductCatalogMediaJob;
use App\Models\CatalogProductImage;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CatalogMediaService;
use Illuminate\Http\RedirectResponse;

class ProductCatalogMediaController extends Controller
{
    public function resolve(Shop $shop, Product $product): RedirectResponse
    {
        abort_unless($product->shop_id === $shop->id, 404);

        if ($product->barcode) {
            ResolveProductCatalogMediaJob::dispatch($product->id);
        }

        return back()->with('status', 'La consulta de Open Beauty Facts quedó en cola.');
    }

    public function use(Shop $shop, Product $product, int $catalogImage, CatalogMediaService $mediaService): RedirectResponse
    {
        abort_unless($product->shop_id === $shop->id, 404);
        $image = CatalogProductImage::query()->findOrFail($catalogImage);
        abort_if($product->images()->count() >= $shop->imageLimit(), 422, 'Has alcanzado el límite de imágenes de este producto.');

        $mediaService->attachImage($product, $image);

        return back()->with('status', 'La imagen externa se agregó sin reemplazar las fotos existentes.');
    }
}
