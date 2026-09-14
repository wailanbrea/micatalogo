<?php

namespace App\Http\Requests;

class UpdateShopRequest extends StoreShopRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('shop'));
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }
}
