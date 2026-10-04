<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'product_id',
        'product_name',
        'product_code',
        'sale_unit',
        'volume_ml',
        'inventory_source_product_id',
        'quantity',
        'unit_price',
        'line_total',
        'discount',
        'general_discount_cents',
        'tax',
        'total_cost_cents',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'volume_ml' => 'integer',
            'inventory_source_product_id' => 'integer',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
