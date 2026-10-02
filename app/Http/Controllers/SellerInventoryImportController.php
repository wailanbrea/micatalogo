<?php

namespace App\Http\Controllers;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Models\AttributeDefinition;
use App\Models\GlobalCategory;
use App\Models\Shop;
use App\Services\InventoryImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SellerInventoryImportController extends Controller
{
    public function create(Shop $shop): View
    {
        return view('seller.products.import', [
            'shop' => $shop,
            'rows' => [],
            'validRows' => 0,
            'invalidRows' => 0,
        ]);
    }

    public function preview(Request $request, Shop $shop, InventoryImportService $importer): View
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
        ]);

        $rows = $importer->parse($request->file('file'));

        return view('seller.products.import', [
            'shop' => $shop,
            'rows' => $rows,
            'validRows' => collect($rows)->where('valid', true)->count(),
            'invalidRows' => collect($rows)->where('valid', false)->count(),
        ]);
    }

    public function store(Request $request, Shop $shop): RedirectResponse
    {
        $request->validate(['rows' => ['required', 'json']]);
        $rows = json_decode($request->string('rows')->value(), true);
        if (! is_array($rows) || $rows === []) {
            throw ValidationException::withMessages(['rows' => 'No hay filas válidas para importar.']);
        }

        $rows = collect($rows)->filter(fn ($row) => is_array($row) && ($row['valid'] ?? false))->values();
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['rows' => 'Corrige las filas marcadas antes de importar.']);
        }

        DB::transaction(function () use ($shop, $rows): void {
            $lockedShop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            if ($lockedShop->products()->count() + $rows->count() > $lockedShop->productLimit()) {
                throw ValidationException::withMessages(['rows' => 'La importación supera el límite de productos de esta tienda.']);
            }

            $categories = $lockedShop->categories()->get()->keyBy(fn ($category) => Str::lower(Str::ascii($category->name)));
            $globalCategories = GlobalCategory::query()->where('status', 'active')->get()->keyBy(fn ($category) => Str::lower(Str::ascii($category->name)));

            foreach ($rows as $row) {
                $category = $categories->get(Str::lower(Str::ascii((string) ($row['category'] ?? ''))));
                $globalCategory = $globalCategories->get(Str::lower(Str::ascii((string) ($row['category'] ?? ''))));
                $slug = $this->uniqueSlug($lockedShop, (string) $row['name']);
                $stock = array_key_exists('stock', $row) && $row['stock'] !== null ? max(0, (int) $row['stock']) : 0;
                $trackInventory = $row['stock'] !== null || $row['cost_price'] !== null;

                $product = $lockedShop->products()->create([
                    'name' => trim((string) $row['name']),
                    'product_code' => $row['product_code'] ?? null,
                    'brand' => $row['brand'] ?? null,
                    'slug' => $slug,
                    'description' => $row['description'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'shop_category_id' => $category?->id,
                    'global_category_id' => $globalCategory?->id,
                    'price' => $row['price'],
                    'currency' => config('catalog.currency', 'DOP'),
                    'availability_status' => $trackInventory && $stock === 0 ? ProductAvailabilityStatus::OutOfStock : ProductAvailabilityStatus::Available,
                    'moderation_status' => ProductModerationStatus::Active,
                    'published_at' => now(),
                ]);

                $product->inventory()->create([
                    'track_inventory' => $trackInventory,
                    'cost_price' => $row['cost_price'] ?? null,
                    'stock_quantity' => $stock,
                    'low_stock_threshold' => 3,
                ]);

                foreach ($row['attributes'] ?? [] as $attribute) {
                    $name = trim((string) ($attribute['name'] ?? ''));
                    $value = trim((string) ($attribute['value'] ?? ''));
                    if ($name === '' || $value === '') {
                        continue;
                    }
                    $definition = AttributeDefinition::firstOrCreate([
                        'shop_id' => $lockedShop->id,
                        'shop_category_id' => $category?->id,
                        'slug' => Str::slug($name),
                    ], ['name' => $name, 'filterable' => true]);
                    $product->attributeValues()->create([
                        'attribute_definition_id' => $definition->id,
                        'value' => $value,
                    ]);
                }
            }
        });

        return to_route('seller.shops.products.index', $shop)->with('status', "Se importaron {$rows->count()} productos después de validar el archivo.");
    }

    private function uniqueSlug(Shop $shop, string $name): string
    {
        $base = Str::slug($name) ?: 'producto';
        $slug = $base;
        $suffix = 2;
        while ($shop->products()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
