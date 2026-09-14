<?php

namespace App\Models;

use App\Services\MediaStorageService;
use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = ['name', 'slug', 'description', 'logo_object_key', 'whatsapp_country_code', 'whatsapp_number', 'offers_shipping', 'instagram', 'status'];

    protected function casts(): array
    {
        return ['offers_shipping' => 'boolean'];
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_object_key) {
            return null;
        }

        return app(MediaStorageService::class)->url($this->logo_object_key);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ShopCategory::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(ShopDailyMetric::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }
}
