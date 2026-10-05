<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'public_id',
        'shop_id',
        'user_id',
        'expense_category_id',
        'cash_register_session_id',
        'description',
        'amount',
        'amount_cents',
        'amount_paid',
        'amount_paid_cents',
        'payment_status',
        'payment_method',
        'reference',
        'occurred_at',
        'notes',
        'client_operation_uuid',
        'payload_sha256',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_cents' => 'integer',
            'amount_paid' => 'decimal:2',
            'amount_paid_cents' => 'integer',
            'occurred_at' => 'datetime',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function cashRegisterSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ExpensePayment::class);
    }

    public function unpaidAmountCents(): int
    {
        return max(0, (int) $this->amount_cents - (int) $this->amount_paid_cents);
    }

    public function unpaidAmount(): float
    {
        return $this->unpaidAmountCents() / 100.0;
    }

    public function statusLabel(): string
    {
        return match ($this->payment_status) {
            'paid' => 'Pagado',
            'partial' => 'Parcial',
            'pending' => 'Pendiente',
            default => ucfirst($this->payment_status),
        };
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if ($field) {
            return $this->where($field, $value)->first();
        }

        return $this->where('public_id', $value)->orWhere('id', $value)->first();
    }
}
