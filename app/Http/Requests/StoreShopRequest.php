<?php

namespace App\Http\Requests;

use App\Models\Shop;
use Illuminate\Foundation\Http\FormRequest;

class StoreShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Shop::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'whatsapp_country_code' => preg_replace('/\D/', '', (string) $this->input('whatsapp_country_code')),
            'whatsapp_number' => preg_replace('/\D/', '', (string) $this->input('whatsapp_number')),
            'instagram' => ltrim((string) $this->input('instagram'), '@'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'slug' => ['nullable', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'whatsapp_country_code' => ['required', 'regex:/^[1-9][0-9]{0,4}$/'],
            'whatsapp_number' => ['required', 'regex:/^[1-9][0-9]{5,14}$/'],
            'offers_shipping' => ['nullable', 'boolean'],
            'instagram' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9._]+$/'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp,avif', 'max:'.((int) config('catalog.uploads.max_file_size_mb', 10) * 1024)],
        ];
    }
}
