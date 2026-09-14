<?php

namespace App\Http\Requests;

use App\Enums\CategoryStatus;
use App\Models\GlobalCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GlobalCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->isMethod('post') ? 'create' : 'update', $this->route('category') ?? GlobalCategory::class);
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('global_categories', 'id')],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'slug' => ['nullable', 'string', 'min:2', 'max:120'],
            'status' => ['required', Rule::enum(CategoryStatus::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $parent = GlobalCategory::find($this->integer('parent_id'));
            $category = $this->route('category');

            if ($parent && $parent->parent_id) {
                $validator->errors()->add('parent_id', 'Solo se permite un nivel de subcategorias.');
            }

            if ($parent && $category && $parent->is($category)) {
                $validator->errors()->add('parent_id', 'Una categoria no puede ser su propio padre.');
            }
        }];
    }
}
