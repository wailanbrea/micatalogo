<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopSeller extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'user_id',
        'commission_type',
        'commission_value',
        'is_active',
        'menu_permissions',
    ];

    protected function casts(): array
    {
        return [
            'commission_value' => 'decimal:2',
            'is_active' => 'boolean',
            'menu_permissions' => 'array',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
