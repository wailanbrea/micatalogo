<?php

namespace App\Models;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductImageProcessingStatus;
use App\Enums\ProductModerationStatus;
use App\Traits\HasPublicId;
use Carbon\Carbon;
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

    protected $fillable = [
        'global_category_id',
        'shop_category_id',
        'name',
        'product_code',
        'barcode',
        'catalog_product_id',
        'brand',
        'slug',
        'description',
        'source_category',
        'notes',
        'source_created_at',
        'source_key',
        'price',
        'sale_price',
        'sale_starts_at',
        'sale_ends_at',
        'currency',
        'sale_unit',
        'volume_ml',
        'inventory_source_product_id',
        'availability_status',
        'moderation_status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
            'volume_ml' => 'integer',
            'source_created_at' => 'datetime',
            'availability_status' => ProductAvailabilityStatus::class,
            'moderation_status' => ProductModerationStatus::class,
            'published_at' => 'datetime',
        ];
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

    public function catalogProduct(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class);
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)
            ->where('processing_status', ProductImageProcessingStatus::Ready)
            ->orderBy('sort_order')
            ->orderBy('id');
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

    public function inventory(): HasOne
    {
        return $this->hasOne(ProductInventory::class);
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function sourceProduct(): BelongsTo
    {
        return $this->belongsTo(self::class, 'inventory_source_product_id');
    }

    public function decantProducts(): HasMany
    {
        return $this->hasMany(self::class, 'inventory_source_product_id');
    }

    public function isDecant(): bool
    {
        return $this->sale_unit === 'decant';
    }

    public function isOnSale(?Carbon $at = null): bool
    {
        if ($this->sale_price === null || $this->price === null || (float) $this->sale_price >= (float) $this->price) {
            return false;
        }

        $at ??= now();

        return (! $this->sale_starts_at || $this->sale_starts_at->lessThanOrEqualTo($at))
            && (! $this->sale_ends_at || $this->sale_ends_at->greaterThanOrEqualTo($at));
    }

    public function currentPrice(): float
    {
        return $this->isOnSale() ? (float) $this->sale_price : (float) $this->price;
    }

    public function discountPercent(): int
    {
        if (! $this->isOnSale() || ! (float) $this->price) {
            return 0;
        }

        return (int) round(100 - (((float) $this->sale_price / (float) $this->price) * 100));
    }

    public function saleUnitLabel(): string
    {
        return match ($this->sale_unit) {
            'bottle' => 'Botella completa',
            'ml' => 'Mililitro',
            'decant' => 'Decant',
            default => 'Unidad',
        };
    }

    public function stockUnitLabel(): string
    {
        return match ($this->sale_unit) {
            'bottle' => 'botellas',
            'ml' => 'ml',
            'decant' => 'decants',
            default => 'unidades',
        };
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function isInventoryTracked(): bool
    {
        return (bool) ($this->inventory?->track_inventory ?? false);
    }

    public function getInventoryStatusAttribute(): string
    {
        if (! $this->isInventoryTracked()) {
            return $this->availability_status->value;
        }

        return $this->inventory->status();
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
