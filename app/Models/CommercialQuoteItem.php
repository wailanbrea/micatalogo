<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialQuoteItem extends Model
{
    use HasFactory;

    protected $fillable = ['quote_id', 'product_id', 'product_name', 'product_code', 'sale_unit', 'volume_ml', 'quantity', 'unit_price', 'line_total'];

    protected function casts(): array { return ['unit_price' => 'decimal:2', 'line_total' => 'decimal:2', 'quantity' => 'integer', 'volume_ml' => 'integer']; }
    public function quote(): BelongsTo { return $this->belongsTo(CommercialQuote::class, 'quote_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
