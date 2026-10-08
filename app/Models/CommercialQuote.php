<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialQuote extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'shop_id', 'customer_id', 'user_id', 'converted_invoice_id', 'quote_number', 'status',
        'customer_name', 'customer_phone', 'currency', 'subtotal', 'discount', 'tax', 'total',
        'valid_until', 'notes',
    ];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2', 'valid_until' => 'date'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function isExpired(): bool
    {
        return $this->valid_until?->isBefore(today()) ?? false;
    }

    public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function convertedInvoice(): BelongsTo { return $this->belongsTo(Invoice::class, 'converted_invoice_id'); }
    public function items(): HasMany { return $this->hasMany(CommercialQuoteItem::class, 'quote_id'); }
}
