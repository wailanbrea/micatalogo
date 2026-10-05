<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryImportSession extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'shop_id',
        'user_id',
        'public_id',
        'original_filename',
        'status',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'rows_payload',
        'missing_categories',
        'mapping',
        'summary',
        'expires_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'invalid_rows' => 'integer',
            'rows_payload' => 'array',
            'missing_categories' => 'array',
            'mapping' => 'array',
            'summary' => 'array',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }
}
