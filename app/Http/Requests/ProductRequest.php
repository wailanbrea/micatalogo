<?php

namespace App\Http\Requests;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
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
            'sale_unit' => ['nullable', Rule::in(['unit', 'bottle', 'ml', 'decant'])],
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
            'low_stock_threshold' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'attributes' => ['nullable', 'array', 'max:30'],
            'attributes.*.name' => ['nullable', 'string', 'max:100'],
            'attributes.*.value' => ['nullable', 'string', 'max:255'],
            'attributes.*.filterable' => ['nullable', 'boolean'],
        ];
    }
}
