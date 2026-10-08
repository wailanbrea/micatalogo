<?php

namespace App\Http\Requests;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Models\Product;
use App\Services\BusinessProfileService;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
{
    private function isPositiveMoney(mixed $value): bool
    {
        try {
            return Money::toCents($value) > 0;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public function authorize(): bool
    {
        $shop = $this->route('shop');
        if (! $shop || ! $this->user()->ownsShop($shop)) {
            return false;
        }

        return $this->user()->can($this->isMethod('post') ? 'create' : 'update', $this->route('product') ?? Product::class);
    }

    public function rules(): array
    {
        $shop = $this->route('shop');

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'product_code' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'regex:/^[0-9][0-9\s-]{7,31}$/'],
            'brand' => ['nullable', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0', 'lte:price', 'max:99999999.99'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:price', 'max:99999999.99'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at' => ['nullable', 'date', 'after_or_equal:sale_starts_at'],
            'sale_unit' => ['nullable', Rule::in(['unit', 'bottle', 'ml', 'decant', 'service'])],
            'is_combo' => ['nullable', 'boolean'],
            'combo_items' => ['nullable', 'array', 'max:30'],
            'combo_items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')->where('shop_id', $shop?->id),
            ],
            'combo_items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'volume_ml' => ['required_if:sale_unit,bottle,ml,decant', 'nullable', 'integer', 'min:1', 'max:100000'],
            'inventory_source_product_id' => [
                'required_if:sale_unit,decant',
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where('shop_id', $shop?->id),
            ],
            'availability_status' => ['required', Rule::enum(ProductAvailabilityStatus::class)],
            'moderation_status' => ['required', Rule::enum(ProductModerationStatus::class)],
            'global_category_id' => ['nullable', 'integer', 'exists:global_categories,id'],
            'shop_category_id' => [
                'nullable',
                'integer',
                Rule::exists('shop_categories', 'id')->where('shop_id', $shop?->id),
            ],
            'track_inventory' => ['nullable', 'boolean'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            // Zero explicitly disables the low-stock warning for a product.
            // The edit form already allowed 0, so rejecting it here made otherwise
            // valid product updates fail and silently prevented bottle metadata from
            // being saved for decant sources.
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'attributes' => ['nullable', 'array', 'max:30'],
            'attributes.*.name' => ['nullable', 'string', 'max:100'],
            'attributes.*.value' => ['nullable', 'string', 'max:255'],
            'attributes.*.filterable' => ['nullable', 'boolean'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,avif', 'max:'.((int) config('catalog.uploads.max_file_size_mb', 10) * 1024)],
            'image_source_url' => ['nullable', 'url', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $saleUnit = $this->input('sale_unit', 'unit');
            $tracksInventory = $this->boolean('track_inventory');

            if ($saleUnit === 'bottle' && $tracksInventory
                && ! $this->isPositiveMoney($this->input('cost_price'))) {
                $validator->errors()->add('cost_price', 'Indica el costo de compra de cada botella para calcular la ganancia y el costo recuperado por decants.');
            }

            if ($this->input('sale_unit') === 'decant'
                && ! app(BusinessProfileService::class)->allows($this->route('shop'), 'decants')) {
                $validator->errors()->add('sale_unit', 'Los decants no están disponibles para el tipo de negocio o plan de esta tienda.');
            }

            if ($saleUnit === 'service') {
                if (! app(BusinessProfileService::class)->allows($this->route('shop'), 'services')) {
                    $validator->errors()->add('sale_unit', 'Los servicios no están disponibles para esta tienda.');
                }
                if (! $this->isPositiveMoney($this->input('price'))) {
                    $validator->errors()->add('price', 'Indica un precio mayor que RD$ 0 para el servicio.');
                }
            }

            if ($this->boolean('is_combo')) {
                if ($saleUnit !== 'unit') {
                    $validator->errors()->add('sale_unit', 'Un combo se vende como una unidad agrupada.');
                }

                $items = collect($this->input('combo_items', []))
                    ->filter(fn ($item): bool => filled($item['product_id'] ?? null));
                if ($items->isEmpty()) {
                    $validator->errors()->add('combo_items', 'Agrega al menos un producto al combo.');
                }

                $ids = $items->pluck('product_id')->map(fn ($id): int => (int) $id)->values();
                if ($ids->isNotEmpty()) {
                    $components = $shop?->products()->with('inventory')->whereIn('id', $ids)->get()->keyBy('id');
                    foreach ($ids as $id) {
                        $component = $components->get($id);
                        if (! $component) {
                            continue;
                        }
                        if ($component->isCombo()) {
                            $validator->errors()->add('combo_items', 'No se pueden anidar combos dentro de otros combos.');
                        } elseif ($component->isService() || ! $component->inventory?->track_inventory) {
                            $validator->errors()->add('combo_items', "El producto {$component->name} debe tener inventario activo y no puede ser un servicio.");
                        }
                        if ((int) data_get($this->route('product'), 'id', 0) === $component->id) {
                            $validator->errors()->add('combo_items', 'Un combo no puede contenerse a sí mismo.');
                        }
                    }
                }
            }

            if ($saleUnit === 'decant' && $this->filled('inventory_source_product_id')) {
                $source = $this->route('shop')?->products()->with('inventory')->find($this->input('inventory_source_product_id'));
                if ($source && ($source->inventory?->cost_price === null
                    || ! $this->isPositiveMoney($source->inventory->getRawOriginal('cost_price') ?? $source->inventory->cost_price))) {
                    $validator->errors()->add('inventory_source_product_id', 'La botella seleccionada no tiene costo de compra. Regístralo en la botella fuente antes de crear el decant.');
                }
            }
        });
    }
}
