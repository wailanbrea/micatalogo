<?php

namespace App\Models;

use App\Services\MediaStorageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'catalog_product_id',
        'provider_image_id',
        'source_url',
        'source_url_hash',
        'object_key',
        'thumbnail_object_key',
        'mime_type',
        'width',
        'height',
        'size_bytes',
        'checksum_sha256',
        'license',
        'attribution',
        'processing_status',
        'is_primary',
        'last_error',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function catalogProduct(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class);
    }

    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function getUrlAttribute(): ?string
    {
        return $this->object_key ? app(MediaStorageService::class)->url($this->object_key) : null;
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        $key = $this->thumbnail_object_key ?? $this->object_key;

        return $key ? app(MediaStorageService::class)->url($key) : null;
    }
}
