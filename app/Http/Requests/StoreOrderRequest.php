<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.id' => ['required', 'string', 'size:26'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'delivery_type' => ['nullable', Rule::in(['delivery', 'pickup'])],
            'delivery_at' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
