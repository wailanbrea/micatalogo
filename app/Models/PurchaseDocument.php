<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseDocument extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = ['shop_id', 'supplier_id', 'user_id', 'document_number', 'type', 'status', 'currency', 'subtotal', 'total', 'received_at', 'notes'];
    protected function casts(): array { return ['subtotal' => 'decimal:2', 'total' => 'decimal:2', 'received_at' => 'datetime']; }
    public function getRouteKeyName(): string { return 'public_id'; }
    public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(PurchaseDocumentItem::class); }
}
