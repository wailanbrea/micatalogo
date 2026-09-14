<?php

namespace App\Models;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductImageProcessingStatus;
use App\Enums\ProductModerationStatus;
use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = ['global_category_id', 'shop_category_id', 'name', 'slug', 'description', 'price', 'currency', 'availability_status', 'moderation_status', 'published_at'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'availability_status' => ProductAvailabilityStatus::class, 'moderation_status' => ProductModerationStatus::class, 'published_at' => 'datetime'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function globalCategory(): BelongsTo
    {
        return $this->belongsTo(GlobalCategory::class);
    }

    public function shopCategory(): BelongsTo
    {
        return $this->belongsTo(ShopCategory::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)
            ->where('processing_status', ProductImageProcessingStatus::Ready)
            ->orderBy('sort_order');
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->relationLoaded('images')) {
            return $this->images
                ->first(fn (ProductImage $image) => $image->processing_status === ProductImageProcessingStatus::Ready)
                ?->url;
        }

        return $this->primaryImage?->url;
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(ProductDailyMetric::class);
    }

    public function scopePublic(Builder $query): void
    {
        $query->where('moderation_status', ProductModerationStatus::Active)
            ->whereHas('shop', fn (Builder $shops) => $shops->where('status', 'active'));
    }

    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->whereHas('shop', fn (Builder $shop) => $shop->ownedBy($user));
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
