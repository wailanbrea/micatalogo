<?php

namespace App\Models;

use App\Enums\ProductImageProcessingStatus;
use App\Services\MediaStorageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = ['object_key', 'thumbnail_object_key', 'mime_type', 'width', 'height', 'size_bytes', 'checksum_sha256', 'sort_order', 'processing_status'];

    protected function casts(): array
    {
        return ['processing_status' => ProductImageProcessingStatus::class];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getUrlAttribute(): string
    {
        return app(MediaStorageService::class)->url($this->object_key);
    }

    public function getThumbnailUrlAttribute(): string
    {
        $key = $this->thumbnail_object_key ?? $this->object_key;

        return app(MediaStorageService::class)->url($key);
    }
}
