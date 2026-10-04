<?php

namespace App\Console\Commands;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductImageProcessingStatus;
use App\Enums\ProductModerationStatus;
use App\Jobs\ResolveProductCatalogMediaJob;
use App\Models\GlobalCategory;
use App\Models\Product;
use App\Models\Shop;
use App\Services\PlanLimitsService;
use App\Services\CatalogMediaService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportInventoryCommand extends Command
{
    protected $signature = 'catalog:import-inventory
                            {file : Ruta del JSON de inventario}
                            {shop : ID o slug de la tienda destino}
                            {--source=aromas-privity-2026-09-28 : Identificador estable de esta fuente}
                            {--dry-run : Analiza sin escribir datos}';

    protected $description = 'Importa un inventario JSON de forma idempotente y auditable';

    public function handle(PlanLimitsService $limits, CatalogMediaService $catalogMedia): int
    {
        try {
            $payload = json_decode(File::get($this->argument('file')), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            $this->error('No se pudo leer el JSON: '.$exception->getMessage());

            return self::FAILURE;
        }

        $rows = collect($payload['products'] ?? []);
        if ($rows->isEmpty()) {
            $this->error('El archivo no contiene productos.');

            return self::FAILURE;
        }

        $shop = $this->resolveShop((string) $this->argument('shop'));
        if (! $shop) {
            $this->error('No se encontró la tienda indicada.');

            return self::FAILURE;
        }

        $source = (string) $this->option('source');
        $dryRun = (bool) $this->option('dry-run');
        $sourceKeys = $rows->map(fn (array $row): string => "{$source}:".(int) ($row['source_row'] ?? 0))->values();
        $existingSourceKeys = Product::withTrashed()
            ->where('shop_id', $shop->id)
            ->whereIn('source_key', $sourceKeys)
            ->pluck('source_key');
        $newProductCount = $sourceKeys->diff($existingSourceKeys)->count();

        try {
            $limits->assertCanAddProducts($shop, $newProductCount);
        } catch (ValidationException $exception) {
            $this->error($exception->validator->errors()->first('products'));

            return self::FAILURE;
        }

        $categoryNames = $rows
            ->pluck('category')
            ->filter(fn ($category) => is_string($category) && trim($category) !== '')
            ->map(fn (string $category) => trim($category))
            ->unique()
            ->sort()
            ->values();

        $categoryNames->push('Sin categoría');
        $shopCategories = $this->resolveShopCategories($shop, $categoryNames, $dryRun);
        $globalBeautyCategory = GlobalCategory::query()->where('slug', 'belleza')->first();
        $existingNameCounts = Product::withTrashed()
            ->where('shop_id', $shop->id)
            ->pluck('name')
            ->map(fn (string $name) => $this->normalizeName($name))
            ->countBy();
        $stats = [
            'source_products' => $rows->count(),
            'created' => 0,
            'updated' => 0,
            'ambiguous' => 0,
            'failed' => 0,
            'images_confirmed' => 0,
            'images_pending' => 0,
        ];

        foreach ($rows as $row) {
            $sourceRow = (int) ($row['source_row'] ?? 0);
            $sourceKey = "{$source}:{$sourceRow}";
            $hasSourceRecord = Product::withTrashed()
                ->where('shop_id', $shop->id)
                ->where('source_key', $sourceKey)
                ->exists();
            $nameIsAmbiguous = ! $hasSourceRecord
                && $existingNameCounts->get($this->normalizeName((string) ($row['name'] ?? '')), 0) > 0;

            if ($nameIsAmbiguous) {
                $stats['ambiguous']++;
            }

            if ($dryRun) {
                $stats[$hasSourceRecord ? 'updated' : 'created']++;

                continue;
            }

            try {
                DB::transaction(function () use ($shop, $row, $source, $sourceKey, $shopCategories, $globalBeautyCategory, $catalogMedia, &$stats) {
                    $product = Product::withTrashed()
                        ->where('shop_id', $shop->id)
                        ->where('source_key', $sourceKey)
                        ->first();
                    $wasExisting = $product !== null;

                    if ($product?->trashed()) {
                        $product->restore();
                    }

                    $name = trim((string) ($row['name'] ?? 'Producto sin nombre'));
                    $sourceCategory = is_string($row['category'] ?? null) && trim($row['category']) !== ''
                        ? trim($row['category'])
                        : null;
                    $shopCategoryName = $sourceCategory ?? 'Sin categoría';
                    $isBeauty = $sourceCategory !== null
                        && (str_contains(strtolower($sourceCategory), 'perfume')
                            || str_contains(strtolower($sourceCategory), 'body')
                            || str_contains(strtolower($sourceCategory), 'íntim'));
                    $quantity = max(0, (int) ($row['quantity'] ?? 0));
                    $unitPrice = $row['unit_price'] ?? null;
                    $unitCost = $row['unit_cost'] ?? null;

                    $attributes = [
                        'name' => $name,
                        'product_code' => $row['product_code'] !== null ? trim((string) $row['product_code']) : null,
                        'barcode' => $catalogMedia->normalizeBarcode($row['barcode'] ?? null),
                        'slug' => $product?->slug ?? $this->resolveSlug($shop, $name),
                        'description' => null,
                        'source_category' => $sourceCategory,
                        'notes' => $row['notes'] !== null ? (string) $row['notes'] : null,
                        'source_created_at' => $this->parseSourceDate($row['created'] ?? null),
                        'source_key' => $sourceKey,
                        'price' => $unitPrice,
                        'currency' => 'DOP',
                        'global_category_id' => $isBeauty ? $globalBeautyCategory?->id : null,
                        'shop_category_id' => $shopCategories[$shopCategoryName] ?? null,
                        'sale_unit' => 'unit',
                        'volume_ml' => null,
                        'availability_status' => $quantity > 0
                            ? ProductAvailabilityStatus::Available
                            : ProductAvailabilityStatus::OutOfStock,
                        'moderation_status' => ProductModerationStatus::Active,
                        'published_at' => now(),
                    ];

                    if ($product) {
                        $product->update($attributes);
                    } else {
                        $product = $shop->products()->create($attributes);
                    }

                    $product->inventory()->updateOrCreate([], [
                        'track_inventory' => true,
                        'cost_price' => $unitCost,
                        'stock_quantity' => $quantity,
                        'available_ml' => null,
                        'sold_quantity' => 0,
                        'low_stock_threshold' => 3,
                    ]);

                    $hasReadyImage = $product->images()
                        ->where('processing_status', ProductImageProcessingStatus::Ready)
                        ->exists();
                    DB::table('product_import_records')->updateOrInsert(
                        ['shop_id' => $shop->id, 'source' => $source, 'source_row' => (int) $row['source_row']],
                        [
                            'product_id' => $product->id,
                            'source_key' => $sourceKey,
                            'data_status' => $wasExisting ? 'updated' : 'created',
                            'image_status' => $hasReadyImage ? 'confirmed' : 'not_found',
                            'error' => null,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );

                    $stats[$wasExisting ? 'updated' : 'created']++;
                    $stats[$hasReadyImage ? 'images_confirmed' : 'images_pending']++;

                    if ($product->barcode) {
                        ResolveProductCatalogMediaJob::dispatch($product->id)->afterCommit();
                    }
                });
            } catch (Throwable $exception) {
                $stats['failed']++;
                DB::table('product_import_records')->updateOrInsert(
                    ['shop_id' => $shop->id, 'source' => $source, 'source_row' => $sourceRow],
                    [
                        'product_id' => null,
                        'source_key' => $sourceKey,
                        'data_status' => 'failed',
                        'image_status' => 'not_found',
                        'error' => $exception->getMessage(),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        $this->table(array_keys($stats), [array_values($stats)]);

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveShop(string $identifier): ?Shop
    {
        return is_numeric($identifier)
            ? Shop::find((int) $identifier)
            : Shop::where('slug', $identifier)->first();
    }

    private function resolveShopCategories(Shop $shop, $names, bool $dryRun): array
    {
        $categories = [];

        foreach ($names as $sortOrder => $name) {
            $category = $shop->categories()->where('name', $name)->first();
            if (! $category && ! $dryRun) {
                $category = $shop->categories()->create([
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'sort_order' => $sortOrder + 1,
                    'status' => 'active',
                ]);
            }

            if ($category) {
                $categories[$name] = $category->id;
            }
        }

        return $categories;
    }

    private function resolveSlug(Shop $shop, string $name): string
    {
        $base = Str::slug($name) ?: 'producto';
        $slug = $base;
        $suffix = 2;

        while (Product::withTrashed()->where('shop_id', $shop->id)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function normalizeName(string $name): string
    {
        return Str::of($name)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->value();
    }

    private function parseSourceDate(?string $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        $months = [
            'ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4,
            'may' => 5, 'jun' => 6, 'jul' => 7, 'ago' => 8,
            'sep' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12,
        ];
        $parts = preg_split('/\s+/', trim(str_replace('.', '', strtolower($value))));
        if (count($parts) !== 3 || ! isset($months[$parts[1]])) {
            return null;
        }

        return CarbonImmutable::create((int) $parts[2], $months[$parts[1]], (int) $parts[0]);
    }
}
