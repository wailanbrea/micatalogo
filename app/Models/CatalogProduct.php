<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'barcode',
        'provider_product_id',
        'name',
        'generic_name',
        'brands',
        'categories',
        'quantity',
        'provider_payload',
        'lookup_status',
        'last_lookup_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'provider_payload' => 'array',
            'last_lookup_at' => 'datetime',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(CatalogProductImage::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
