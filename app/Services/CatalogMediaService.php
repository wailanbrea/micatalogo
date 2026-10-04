<?php

namespace App\Services;

use App\Models\CatalogProduct;
use App\Models\CatalogProductImage;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CatalogMediaService
{
    public function __construct(
        private OpenBeautyFactsService $provider,
        private ImageDerivativeService $derivatives,
        private MediaStorageService $mediaStorage,
    ) {}

    public function normalizeBarcode(?string $barcode): ?string
    {
        return $this->provider->normalizeBarcode($barcode);
    }

    public function resolveProduct(Product $product): ?CatalogProduct
    {
        $barcode = $this->normalizeBarcode($product->barcode);
        if (! $barcode) {
            return null;
        }

        $catalogProduct = $this->resolveBarcode($barcode);
        if ($product->catalog_product_id !== $catalogProduct?->id) {
            $product->update(['catalog_product_id' => $catalogProduct?->id]);
        }

        return $catalogProduct;
    }

    public function resolveBarcode(string $barcode): ?CatalogProduct
    {
        $barcode = $this->normalizeBarcode($barcode);
        if (! $barcode) {
            return null;
        }

        $catalogProduct = CatalogProduct::query()->firstOrNew([
            'provider' => 'open_beauty_facts',
            'barcode' => $barcode,
        ]);

        try {
            $result = $this->provider->lookup($barcode);
            if ($result['status'] !== 'found') {
                $catalogProduct->fill([
                    'lookup_status' => 'not_found',
                    'last_lookup_at' => now(),
                    'last_error' => null,
                ])->save();

                return $catalogProduct;
            }

            $payload = $result['payload'];
            $data = $result['product'];
            $catalogProduct->fill([
                'provider_product_id' => isset($data['code']) ? (string) $data['code'] : $barcode,
                'name' => $this->stringValue($data['product_name'] ?? null),
                'generic_name' => $this->stringValue($data['generic_name'] ?? null),
                'brands' => $this->stringValue($data['brands'] ?? null),
                'categories' => $this->stringValue($data['categories'] ?? null),
                'quantity' => $this->stringValue($data['quantity'] ?? null),
                'provider_payload' => $payload,
                'lookup_status' => 'found',
                'last_lookup_at' => now(),
                'last_error' => null,
            ])->save();

            $this->resolveImage($catalogProduct, $data);

            return $catalogProduct->fresh('images');
        } catch (RuntimeException $exception) {
            $catalogProduct->fill([
                'lookup_status' => 'failed',
                'last_lookup_at' => now(),
                'last_error' => $exception->getMessage(),
            ])->save();
            Log::warning('No se pudo resolver un producto en Open Beauty Facts.', [
                'barcode' => $barcode,
                'error' => $exception->getMessage(),
            ]);

            return $catalogProduct;
        }
    }

    public function attachImage(Product $product, CatalogProductImage $catalogImage): ProductImage
    {
        $catalogImage->loadMissing('catalogProduct');
        $barcode = $this->normalizeBarcode($product->barcode);

        abort_unless(
            $barcode && $catalogImage->catalogProduct?->barcode === $barcode,
            404,
            'La imagen no corresponde al barcode del producto.'
        );
        abort_unless($catalogImage->processing_status === 'ready' && $catalogImage->object_key, 422, 'La imagen aún no está lista.');

        $existing = $product->images()->where('catalog_product_image_id', $catalogImage->id)->first();
        if ($existing) {
            return $existing;
        }

        return $product->images()->create([
            'source' => 'catalog',
            'catalog_product_image_id' => $catalogImage->id,
            'object_key' => $catalogImage->object_key,
            'thumbnail_object_key' => $catalogImage->thumbnail_object_key,
            'mime_type' => $catalogImage->mime_type,
            'width' => $catalogImage->width,
            'height' => $catalogImage->height,
            'size_bytes' => $catalogImage->size_bytes,
            'checksum_sha256' => $catalogImage->checksum_sha256,
            'sort_order' => ($product->images()->max('sort_order') ?? -1) + 1,
            'processing_status' => 'ready',
        ]);
    }

    private function resolveImage(CatalogProduct $catalogProduct, array $providerProduct): void
    {
        $sourceUrl = $this->provider->imageUrl($providerProduct);
        if (! $sourceUrl) {
            return;
        }

        $sourceUrlHash = hash('sha256', $sourceUrl);
        $catalogImage = $catalogProduct->images()->firstOrNew(['source_url_hash' => $sourceUrlHash]);
        $catalogImage->fill([
            'provider_image_id' => $sourceUrl,
            'source_url' => $sourceUrl,
            'source_url_hash' => $sourceUrlHash,
            'license' => $this->stringValue($providerProduct['image_front_license'] ?? $providerProduct['image_front_lc'] ?? null),
            'attribution' => $this->stringValue($providerProduct['image_front_attribution'] ?? $providerProduct['image_front_credit'] ?? null),
            'is_primary' => true,
        ]);
        $catalogImage->save();

        if ($catalogImage->processing_status === 'ready' && $catalogImage->object_key) {
            return;
        }

        try {
            $rawBinary = $this->provider->downloadImage($sourceUrl);
            $rendered = $this->derivatives->render($rawBinary);
            $mainKey = $this->mediaStorage->buildCatalogProductObjectKey($catalogProduct->id, 'main', $rendered['checksum']);
            $thumbKey = $this->mediaStorage->buildCatalogProductObjectKey($catalogProduct->id, 'thumb', $rendered['checksum']);
            $this->derivatives->store($rendered, $mainKey, $thumbKey, $this->mediaStorage);

            $catalogImage->update([
                'object_key' => $mainKey,
                'thumbnail_object_key' => $thumbKey,
                'mime_type' => 'image/webp',
                'width' => $rendered['width'],
                'height' => $rendered['height'],
                'size_bytes' => $rendered['size_bytes'],
                'checksum_sha256' => $rendered['checksum'],
                'processing_status' => 'ready',
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            $catalogImage->update([
                'processing_status' => 'failed',
                'last_error' => $exception->getMessage(),
            ]);
            Log::warning('No se pudo procesar la imagen de Open Beauty Facts.', [
                'catalog_product_id' => $catalogProduct->id,
                'source_url' => $sourceUrl,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
