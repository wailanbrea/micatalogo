<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseDocumentItem extends Model
{
    use HasFactory;

    protected $fillable = ['purchase_document_id', 'product_id', 'inventory_movement_id', 'product_name', 'quantity', 'unit_cost', 'line_total'];
    protected function casts(): array { return ['quantity' => 'integer', 'unit_cost' => 'decimal:2', 'line_total' => 'decimal:2']; }
    public function purchaseDocument(): BelongsTo { return $this->belongsTo(PurchaseDocument::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function inventoryMovement(): BelongsTo { return $this->belongsTo(InventoryMovement::class); }
}
