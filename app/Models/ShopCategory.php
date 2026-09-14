<?php

namespace App\Models;

use App\Enums\CategoryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'sort_order', 'status'];

    protected function casts(): array
    {
        return ['status' => CategoryStatus::class];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->whereHas('shop', fn (Builder $shop) => $shop->ownedBy($user));
    }
}
