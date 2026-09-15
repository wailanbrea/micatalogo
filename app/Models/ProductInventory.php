<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'track_inventory',
        'cost_price',
        'stock_quantity',
        'sold_quantity',
        'low_stock_threshold',
    ];

    protected function casts(): array
    {
        return [
            'track_inventory' => 'boolean',
            'cost_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'sold_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isOutOfStock(): bool
    {
        return $this->track_inventory && $this->stock_quantity <= 0;
    }

    public function isLowStock(): bool
    {
        return $this->track_inventory
            && $this->stock_quantity > 0
            && $this->stock_quantity <= $this->low_stock_threshold;
    }

    public function isAvailable(): bool
    {
        return ! $this->track_inventory || $this->stock_quantity > 0;
    }

    public function status(): string
    {
        if (! $this->track_inventory) {
            return 'untracked';
        }

        if ($this->stock_quantity <= 0) {
            return 'out_of_stock';
        }

        if ($this->stock_quantity <= $this->low_stock_threshold) {
            return 'low_stock';
        }

        return 'available';
    }

    public function statusBadgeLabel(): string
    {
        return match ($this->status()) {
            'out_of_stock' => 'Agotado',
            'low_stock' => 'Stock bajo',
            'available' => 'Disponible',
            default => 'Sin control',
        };
    }

    /**
     * Total valuation of inventory at cost: stock_quantity * cost_price
     */
    public function getInventoryValueAttribute(): float
    {
        if (! $this->cost_price || $this->stock_quantity <= 0) {
            return 0.0;
        }

        return round((float) $this->cost_price * $this->stock_quantity, 2);
    }

    /**
     * Gross profit uses the price and cost captured when each sale was recorded.
     */
    public function getGrossProfitAttribute(): float
    {
        if (! $this->product) {
            return 0.0;
        }

        return round((float) $this->product->inventoryMovements()
            ->where('type', 'sale')
            ->whereNotNull('unit_price')
            ->whereNotNull('unit_cost')
            ->selectRaw('COALESCE(SUM(ABS(quantity) * (unit_price - unit_cost)), 0) as total')
            ->value('total'), 2);
    }

    /**
     * Profit margin per unit in currency: sale_price - cost_price
     */
    public function getUnitMarginAttribute(): ?float
    {
        if ($this->cost_price === null || ! $this->product) {
            return null;
        }

        return round((float) $this->product->price - (float) $this->cost_price, 2);
    }

    /**
     * Profit margin percentage over cost: ((price - cost) / cost) * 100
     */
    public function getMarginPercentageAttribute(): ?float
    {
        if (! $this->cost_price || (float) $this->cost_price <= 0 || ! $this->product) {
            return null;
        }

        $margin = (float) $this->product->price - (float) $this->cost_price;

        return round(($margin / (float) $this->cost_price) * 100, 1);
    }
}
