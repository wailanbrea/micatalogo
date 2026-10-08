<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyClosure extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'public_id',
        'shop_id',
        'user_id',
        'business_date',
        'expected_cash_cents',
        'counted_cash_cents',
        'difference_cents',
        'sales_cash_cents',
        'debt_collections_cash_cents',
        'other_inflows_cash_cents',
        'expenses_cash_cents',
        'cash_out_cents',
        'status',
        'notes',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'expected_cash_cents' => 'integer',
            'counted_cash_cents' => 'integer',
            'difference_cents' => 'integer',
            'sales_cash_cents' => 'integer',
            'debt_collections_cash_cents' => 'integer',
            'other_inflows_cash_cents' => 'integer',
            'expenses_cash_cents' => 'integer',
            'cash_out_cents' => 'integer',
            'closed_at' => 'datetime',
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
