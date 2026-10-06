<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerTransaction extends Model
{
    use HasFactory;

    protected $fillable = ['shop_id', 'partner_id', 'user_id', 'cash_movement_id', 'type', 'amount', 'notes', 'occurred_at'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'occurred_at' => 'datetime']; }
    public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
    public function partner(): BelongsTo { return $this->belongsTo(BusinessPartner::class, 'partner_id'); }
    public function cashMovement(): BelongsTo { return $this->belongsTo(CashMovement::class); }
}
