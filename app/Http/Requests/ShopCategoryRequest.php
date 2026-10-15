<?php

namespace App\Http\Requests;

use App\Enums\CategoryStatus;
use App\Models\ShopCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShopCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shop = $this->route('shop');
        return $shop && $this->user()->can($this->isMethod('post') ? 'create' : 'update', $this->route('category')
            ?? [ShopCategory::class, $shop, $this->input('scope')]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'slug' => ['nullable', 'string', 'min:2', 'max:120'],
            'status' => ['required', Rule::enum(CategoryStatus::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'description' => ['nullable', 'string', 'max:2000'],
            'scope' => ['sometimes', 'string', 'in:shared,product,service'],
            'expected_revision' => ['sometimes', 'nullable', 'uuid'],
            'client_operation_uuid' => ['sometimes', 'uuid'],
            'record_id' => ['sometimes', 'uuid'],
        ];
    }
}
