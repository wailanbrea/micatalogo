<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use HasFactory, HasPublicId;

    public const UPDATED_AT = null;

    protected $fillable = [
        'public_id',
        'cash_register_session_id',
        'shop_id',
        'user_id',
        'type',
        'amount',
        'amount_cents',
        'reference_type',
        'reference_id',
        'notes',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_cents' => 'integer',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function cashRegisterSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'sale' => 'Venta en efectivo',
            'customer_payment' => 'Cobro de cliente',
            'expense' => 'Gasto pagado',
            'supplier_payment' => 'Pago a suplidor',
            'cash_in' => 'Entrada de efectivo',
            'cash_out' => 'Salida de efectivo',
            'owner_contribution' => 'Aporte de propietario',
            'owner_withdrawal' => 'Retiro de propietario',
            'adjustment' => 'Ajuste de caja',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }
}
