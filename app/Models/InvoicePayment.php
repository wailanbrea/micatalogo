<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'public_id',
        'shop_id',
        'invoice_id',
        'customer_id',
        'user_id',
        'cash_register_session_id',
        'payment_method',
        'amount',
        'amount_cents',
        'reference',
        'notes',
        'received_at',
        'client_operation_uuid',
        'payload_sha256',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_cents' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashRegisterSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class);
    }

    public function methodLabel(): string
    {
        return config("catalog.payment_methods.{$this->payment_method}.label", ucfirst($this->payment_method));
    }
}
