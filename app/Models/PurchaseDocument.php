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

    protected $fillable = [
        'shop_id', 'supplier_id', 'user_id', 'document_number', 'invoice_date', 'due_at', 'type', 'status', 'currency',
        'exchange_rate', 'carrier', 'tracking_number', 'expected_at', 'shipping_pounds',
        'freight_amount', 'customs_amount', 'payment_status', 'parent_document_id',
        'subtotal', 'total', 'received_at', 'notes',
    ];
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'invoice_date' => 'date',
            'due_at' => 'date',
            'shipping_pounds' => 'decimal:3',
            'freight_amount' => 'decimal:2',
            'customs_amount' => 'decimal:2',
            'expected_at' => 'date',
            'received_at' => 'datetime',
        ];
    }
    public function getRouteKeyName(): string { return 'public_id'; }
    public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(PurchaseDocumentItem::class); }
    public function parentDocument(): BelongsTo { return $this->belongsTo(self::class, 'parent_document_id'); }
    public function childDocuments(): HasMany { return $this->hasMany(self::class, 'parent_document_id'); }
}
