<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductIdentityService
{
    public function __construct(private CatalogMediaService $catalogMedia) {}

    /** @param array<string, mixed> $data */
    public function assertUnique(Shop $shop, array $data, ?Product $except = null): void
    {
        $productCode = trim((string) ($data['product_code'] ?? $data['internal_code'] ?? ''));
        if ($productCode !== '') {
            $query = $shop->products()
                ->whereNotNull('product_code')
                ->whereRaw('LOWER(product_code) = ?', [Str::lower($productCode)]);
            if ($except) {
                $query->whereKeyNot($except->id);
            }
            if ($query->exists()) {
                throw ValidationException::withMessages([
                    'product_code' => 'Este SKU ya existe en la tienda. Usa un código distinto para evitar duplicar el producto.',
                    'internal_code' => 'Este SKU ya existe en la tienda. Usa un código distinto para evitar duplicar el producto.',
                ]);
            }
        }

        $normalizedBarcode = $this->catalogMedia->normalizeBarcode($data['barcode'] ?? null);
        if ($normalizedBarcode === null) {
            return;
        }

        $query = $shop->products()->whereNotNull('barcode');
        if ($except) {
            $query->whereKeyNot($except->id);
        }
        $duplicate = $query->get(['id', 'barcode'])
            ->first(fn (Product $product): bool => $this->catalogMedia->normalizeBarcode($product->barcode) === $normalizedBarcode);

        if ($duplicate) {
            throw ValidationException::withMessages([
                'barcode' => 'Este código de barras ya existe en la tienda. Usa el producto existente o registra un código distinto.',
            ]);
        }
    }
}
