<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateShopRequest extends StoreShopRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('shop'));
    }

    public function rules(): array
    {
        $rules = [
            ...parent::rules(),
            'remove_logo' => ['nullable', 'boolean'],
            'remove_cover' => ['nullable', 'boolean'],
        ];

        if ($this->user()?->isAdmin()) {
            $rules += [
                'status' => ['required', Rule::in(['active', 'suspended'])],
                'discovery_enabled' => ['nullable', 'boolean'],
                'product_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            ];
        }

        return $rules;
    }
}
