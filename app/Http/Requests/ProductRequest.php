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
            'slug' => ['nullable', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
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
        ];
    }
}
