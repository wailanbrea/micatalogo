<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'customer_id',
        'user_id',
        'salesperson_id',
        'invoice_number',
        'status',
        'payment_status',
        'channel',
        'currency',
        'commission_type',
        'commission_value',
        'commission_amount',
        'subtotal',
        'total',
        'discount',
        'tax',
        'issued_at',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'commission_value' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_date' => 'date',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function paidAmount(): float
    {
        return (float) Money::toDecimal((int) $this->payments()->sum('amount_cents'));
    }

    public function getPaymentStatusAttribute(): ?string
    {
        return $this->attributes['status'] ?? null;
    }

    public function setPaymentStatusAttribute(?string $value): void
    {
        if ($value !== null) {
            $this->attributes['status'] = $value;
        }
    }

    public function pendingAmount(): float
    {
        $paidCents = (int) $this->payments()->sum('amount_cents');

        return (float) Money::toDecimal(max(0, Money::toCents($this->total) - $paidCents));
    }
}
