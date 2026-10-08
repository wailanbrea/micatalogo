<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegisterSession extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'public_id',
        'shop_id',
        'user_id',
        'opened_at',
        'closed_at',
        'opening_amount',
        'opening_amount_cents',
        'expected_closing_amount',
        'expected_closing_amount_cents',
        'counted_closing_amount',
        'counted_closing_amount_cents',
        'difference',
        'difference_cents',
        'status',
        'is_open_flag',
        'notes',
        'client_operation_uuid',
        'payload_sha256',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_amount' => 'decimal:2',
            'opening_amount_cents' => 'integer',
            'expected_closing_amount' => 'decimal:2',
            'expected_closing_amount_cents' => 'integer',
            'counted_closing_amount' => 'decimal:2',
            'counted_closing_amount_cents' => 'integer',
            'difference' => 'decimal:2',
            'difference_cents' => 'integer',
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

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class)->orderBy('occurred_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function calculateExpectedBalance(): int
    {
        $opening = $this->opening_amount_cents;
        $netMovements = (int) $this->movements()->sum('amount_cents');

        return $opening + $netMovements;
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if ($field) {
            return $this->where($field, $value)->first();
        }

        return $this->where('public_id', $value)->orWhere('id', $value)->first();
    }
}
