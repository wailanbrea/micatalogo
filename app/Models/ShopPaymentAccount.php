<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopPaymentAccount extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'shop_id', 'name', 'bank_name', 'account_number', 'account_holder',
        'instructions', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
