<?php

namespace App\Http\Controllers;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Http\Requests\ProductRequest;
use App\Jobs\ResolveProductCatalogMediaJob;
use App\Models\AttributeDefinition;
use App\Models\GlobalCategory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Services\CatalogMediaService;
use App\Services\ImageProcessingService;
use App\Services\MediaStorageService;
use App\Services\PlanLimitsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SellerProductController extends Controller
{
    public function index(Request $request, Shop $shop): View
    {
        $search = $request->string('q')->value();
        $status = $request->string('status')->value();

        $products = $shop->products()
            ->with(['globalCategory', 'shopCategory', 'images', 'inventory'])
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($status, fn ($q) => $q->where('availability_status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $maxProducts = $shop->productLimit();
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
            'sourceProducts' => $shop->products()->whereIn('sale_unit', ['bottle', 'ml'])->orderBy('name')->get(),
            'attributeDefinitions' => $shop->attributeDefinitions()->get(),
        ]);
    }

    public function store(ProductRequest $request, Shop $shop, PlanLimitsService $limits, CatalogMediaService $catalogMedia): RedirectResponse
    {
        $product = DB::transaction(function () use ($request, $shop, $limits, $catalogMedia): Product {
            $lockedShop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            $max = $limits->productLimit($lockedShop);

            abort_if(
                $lockedShop->products()->count() >= $max,
                422,
                'Has alcanzado el limite maximo de productos para tu tienda.'
            );

            $validated = $request->validated();
            $validated['barcode'] = $catalogMedia->normalizeBarcode($validated['barcode'] ?? null);
            $validated['sale_unit'] ??= 'unit';
            $this->validatePresentation($validated, $lockedShop);
            $inventoryData = [
                'track_inventory' => (bool) ($validated['track_inventory'] ?? false),
                'cost_price' => isset($validated['cost_price']) && $validated['cost_price'] !== '' ? $validated['cost_price'] : null,
                'stock_quantity' => (int) ($validated['stock_quantity'] ?? 0),
                'low_stock_threshold' => (int) ($validated['low_stock_threshold'] ?? 3),
                'available_ml' => null,
            ];

            if ($inventoryData['track_inventory'] && $inventoryData['stock_quantity'] === 0) {
                $validated['availability_status'] = ProductAvailabilityStatus::OutOfStock->value;
            }

            $attributes = $this->attributes($validated, $lockedShop);

            if (($attributes['moderation_status'] ?? null) === ProductModerationStatus::Active->value) {
                $attributes['published_at'] = now();
            }

            unset($attributes['track_inventory'], $attributes['cost_price'], $attributes['stock_quantity'], $attributes['low_stock_threshold']);

            unset($attributes['attributes']);
            $product = $lockedShop->products()->create($attributes);
            $this->syncProductAttributes($product, $validated, $lockedShop);

            $inventoryData['stock_quantity'] = $this->presentationStock($product, $inventoryData['stock_quantity']);
            $inventoryData['available_ml'] = $this->availableMlForStock($product, $inventoryData['stock_quantity']);

            $product->inventory()->create($inventoryData);

            if ($inventoryData['track_inventory'] && $inventoryData['stock_quantity'] > 0) {
                $product->inventoryMovements()->create([
                    'user_id' => $request->user()->id,
                    'type' => 'restock',
                    'quantity' => $inventoryData['stock_quantity'],
                    'stock_before' => 0,
                    'stock_after' => $inventoryData['stock_quantity'],
                    'unit_price' => $product->price,
                    'unit_cost' => $inventoryData['cost_price'],
                    'notes' => 'Stock inicial al crear producto',
                    'created_at' => now(),
                ]);
            }

            return $product;
        });

        if ($product->barcode) {
            ResolveProductCatalogMediaJob::dispatch($product->id)->afterCommit();
        }

        return to_route('seller.shops.products.index', $shop)->with('status', 'Producto creado exitosamente.');
    }

    public function edit(Shop $shop, Product $product): View
    {
        $product->loadMissing(['inventory', 'attributeValues.attributeDefinition', 'catalogProduct.images']);

        return view('seller.products.form', [
            'shop' => $shop,
            'product' => $product,
            'globalCategories' => GlobalCategory::query()->where('status', 'active')->orderBy('sort_order')->orderBy('name')->get(),
            'shopCategories' => $shop->categories()->where('status', 'active')->orderBy('name')->get(),
            'sourceProducts' => $shop->products()->whereKeyNot($product->id)->whereIn('sale_unit', ['bottle', 'ml'])->orderBy('name')->get(),
            'attributeDefinitions' => $shop->attributeDefinitions()->get(),
        ]);
    }

    public function update(ProductRequest $request, Shop $shop, Product $product, CatalogMediaService $catalogMedia): RedirectResponse
    {
        $catalogProductId = $product->catalog_product_id;
        $barcode = $catalogMedia->normalizeBarcode($request->validated()['barcode'] ?? null);

        DB::transaction(function () use ($request, $shop, $product) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $validated = $request->validated();
            $validated['barcode'] = app(CatalogMediaService::class)->normalizeBarcode($validated['barcode'] ?? null);
            $validated['sale_unit'] ??= $product->sale_unit ?: 'unit';
            $this->validatePresentation($validated, $shop, $product);

            $attributes = $this->attributes($validated, $shop, $product);
            if ($product->barcode !== $validated['barcode']) {
                $attributes['catalog_product_id'] = null;
            }
            unset($attributes['track_inventory'], $attributes['cost_price'], $attributes['stock_quantity'], $attributes['low_stock_threshold'], $attributes['attributes']);
            $presentation = clone $product;
            $presentation->fill($attributes);

            $trackInventory = (bool) ($validated['track_inventory'] ?? false);
            $costPrice = isset($validated['cost_price']) && $validated['cost_price'] !== '' ? $validated['cost_price'] : null;
            $stockQuantity = $trackInventory && isset($validated['stock_quantity']) ? (int) $validated['stock_quantity'] : null;
            $lowStockThreshold = (int) ($validated['low_stock_threshold'] ?? 3);

            $inventory = $product->inventory()->lockForUpdate()->first();
            $createdInventory = $inventory === null;

            if ($createdInventory) {
                $inventory = $product->inventory()->create([
                    'track_inventory' => $trackInventory,
                    'cost_price' => $costPrice,
                    'stock_quantity' => 0,
                    'available_ml' => null,
                    'sold_quantity' => 0,
                    'low_stock_threshold' => $lowStockThreshold,
                ]);
            }

            $stockChanged = false;
            $oldStock = $inventory->stock_quantity;
            $fifo = app(\App\Services\FifoCostService::class);
            $hadLots = \App\Models\InventoryLot::where('product_id', $product->id)->exists();
            if ($hadLots && ($presentation->sale_unit !== $product->sale_unit || $presentation->volume_ml !== $product->volume_ml)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['sale_unit' => 'No cambies la unidad o volumen de un producto con lotes. Crea una presentación vinculada.']);
            }
            $oldCanonical = $fifo->quantity($product, $inventory);
            if ($inventory->track_inventory && ! $product->isDecant()) {
                $fifo->initialize($product, $inventory);
            }

            $inventory->track_inventory = $trackInventory;
            $inventory->cost_price = $costPrice;
            $inventory->low_stock_threshold = $lowStockThreshold;

            if ($stockQuantity !== null && $stockQuantity !== $oldStock) {
                $inventory->stock_quantity = $stockQuantity;
                $stockChanged = true;
            }

            $newStock = $trackInventory
                ? $this->presentationStock($presentation, $inventory->stock_quantity)
                : $inventory->stock_quantity;
            if ($newStock !== $inventory->stock_quantity) {
                $inventory->stock_quantity = $newStock;
                $stockChanged = true;
            }

            $inventory->available_ml = $this->availableMlForStock($presentation, $inventory->stock_quantity, $inventory, $product, $stockChanged);

            $inventory->save();

            if ($stockChanged) {
                $stockDelta = $inventory->stock_quantity - $oldStock;
                $adjustment = $product->inventoryMovements()->create([
                    'user_id' => $request->user()->id,
                    'type' => 'adjustment',
                    'quantity' => $stockDelta,
                    'stock_before' => $oldStock,
                    'stock_after' => $inventory->stock_quantity,
                    'unit_price' => $product->price,
                    'unit_cost' => $costPrice,
                    'notes' => 'Ajuste manual desde edición de producto',
                    'created_at' => now(),
                ]);
                if ($trackInventory && ! $presentation->isDecant()) {
                    $newCanonical = $fifo->quantity($presentation, $inventory);
                    if ($newCanonical > $oldCanonical) {
                        $fifo->receive($presentation, $newCanonical - $oldCanonical, null, 'physical_count');
                    } elseif ($newCanonical < $oldCanonical) {
                        $fifo->consume($product, $oldCanonical - $newCanonical, $adjustment);
                    }
                }
            }

            if ($trackInventory) {
                $attributes['availability_status'] = $inventory->stock_quantity === 0
                    ? ProductAvailabilityStatus::OutOfStock->value
                    : ProductAvailabilityStatus::Available->value;
            }

            if (($attributes['moderation_status'] ?? null) === ProductModerationStatus::Active->value && ! $product->published_at) {
                $attributes['published_at'] = now();
            }

            $product->update($attributes);
            $this->syncProductAttributes($product, $validated, $shop);
        });

        if ($barcode && ($barcode !== $product->barcode || ! $catalogProductId)) {
            ResolveProductCatalogMediaJob::dispatch($product->id)->afterCommit();
        }

        return to_route('seller.shops.products.index', $shop)->with('status', 'Producto actualizado.');
    }

    public function destroy(Shop $shop, Product $product): RedirectResponse
    {
        $product->delete();

        return to_route('seller.shops.products.index', $shop)->with('status', 'Producto enviado a la papelera.');
    }

    public function restore(Request $request, Shop $shop, int|string $product, PlanLimitsService $limits): RedirectResponse
    {
        DB::transaction(function () use ($shop, $product, $limits): void {
            $lockedShop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            $productModel = $lockedShop->products()->onlyTrashed()
                ->where(fn ($q) => $q->where('public_id', $product)->orWhere('id', $product))
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('restore', $productModel);
            $limits->assertCanAddProducts($lockedShop, 1);
            $productModel->restore();
        });

        return to_route('seller.shops.products.index', $shop)->with('status', 'Producto restaurado.');
    }

    public function uploadImage(Request $request, Shop $shop, Product $product, ImageProcessingService $imageService, PlanLimitsService $limits): RedirectResponse
    {
        $maxImages = $limits->imageLimit($shop);
        abort_if(
            $product->images()->count() >= $maxImages,
            422,
            "Tu plan {$shop->planLabel()} permite un máximo de {$maxImages} imágenes por producto."
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
        if ($image->source !== 'catalog' && $image->object_key && $mediaDisk->exists($image->object_key)) {
            $mediaDisk->delete($image->object_key);
        }
        if ($image->source !== 'catalog' && $image->thumbnail_object_key && $mediaDisk->exists($image->thumbnail_object_key)) {
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

    private function syncProductAttributes(Product $product, array $input, Shop $shop): void
    {
        $categoryId = $product->shop_category_id;
        $definitionIds = [];

        foreach ($input['attributes'] ?? [] as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($name === '' || $value === '') {
                continue;
            }

            $definition = AttributeDefinition::firstOrCreate(
                [
                    'shop_id' => $shop->id,
                    'shop_category_id' => $categoryId,
                    'slug' => Str::slug($name),
                ],
                [
                    'name' => $name,
                    'type' => 'text',
                    'filterable' => (bool) ($row['filterable'] ?? true),
                    'display_order' => count($definitionIds),
                ]
            );

            $definition->update([
                'name' => $name,
                'filterable' => (bool) ($row['filterable'] ?? $definition->filterable),
            ]);
            $definitionIds[] = $definition->id;

            $product->attributeValues()->updateOrCreate(
                ['attribute_definition_id' => $definition->id],
                ['value' => $value]
            );
        }

        $product->attributeValues()->when($definitionIds !== [], fn ($query) => $query->whereNotIn('attribute_definition_id', $definitionIds))->when($definitionIds === [], fn ($query) => $query)->delete();
    }

    private function validatePresentation(array $input, Shop $shop, ?Product $product = null): void
    {
        if (($input['sale_unit'] ?? 'unit') !== 'decant') {
            return;
        }

        $sourceId = (int) ($input['inventory_source_product_id'] ?? 0);
        if ($product && $sourceId === $product->id) {
            throw ValidationException::withMessages(['inventory_source_product_id' => 'Un producto no puede ser su propia botella fuente.']);
        }

        $source = $shop->products()->with('inventory')->find($sourceId);
        if (! $source || ! in_array($source->sale_unit, ['bottle', 'ml'], true)) {
            throw ValidationException::withMessages(['inventory_source_product_id' => 'Elige una botella o un producto medido en ml como fuente del decant.']);
        }

        if (! $source->volume_ml || ! $source->inventory?->track_inventory) {
            throw ValidationException::withMessages(['inventory_source_product_id' => 'La botella fuente debe tener volumen en ml y control de inventario activo.']);
        }
    }

    private function presentationStock(Product $product, int $requestedStock): int
    {
        if (! $product->isDecant()) {
            return $requestedStock;
        }

        $source = $product->sourceProduct()->with('inventory')->first();
        $availableMl = $this->availableMl($source, $source?->inventory);

        return $availableMl === null || ! $product->volume_ml
            ? 0
            : intdiv($availableMl, $product->volume_ml);
    }

    private function availableMlForStock(Product $product, int $stock, ?ProductInventory $inventory = null, ?Product $previousProduct = null, bool $forceRecalculate = false): ?int
    {
        return match ($product->sale_unit) {
            'bottle' => $forceRecalculate || $this->shouldRecalculateBottleMl($product, $inventory, $previousProduct)
                ? $stock * (int) $product->volume_ml
                : ($inventory?->available_ml ?? $stock * (int) $product->volume_ml),
            'ml' => $stock,
            default => null,
        };
    }

    private function shouldRecalculateBottleMl(Product $product, ?ProductInventory $inventory, ?Product $previousProduct): bool
    {
        return ! $inventory || $inventory->available_ml === null || ! $previousProduct || $previousProduct->volume_ml !== $product->volume_ml;
    }

    private function availableMl(?Product $product, ?ProductInventory $inventory): ?int
    {
        if (! $product || ! $inventory || ! $inventory->track_inventory) {
            return null;
        }

        return $inventory->available_ml ?? match ($product->sale_unit) {
            'bottle' => $product->volume_ml ? $inventory->stock_quantity * $product->volume_ml : null,
            'ml' => $inventory->stock_quantity,
            default => null,
        };
    }
}
