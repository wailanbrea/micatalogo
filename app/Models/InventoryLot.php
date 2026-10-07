<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLot extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'received_quantity' => 'integer',
            'remaining_quantity' => 'integer', 'received_cost_cents' => 'integer',
            'remaining_cost_cents' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Cost per canonical inventory unit (unit, ml, etc.).
     * The stored cost is the total cost of the lot, so it must not be
     * confused with the latest ProductInventory::cost_price.
     */
    public function getReceivedUnitCostCentsAttribute(): ?int
    {
        if ($this->received_cost_cents === null || (int) $this->received_quantity <= 0) {
            return null;
        }

        return (int) round($this->received_cost_cents / $this->received_quantity);
    }

    /**
     * Cost per product sale unit. For bottles the lot is stored in ml, while
     * the user entered the acquisition cost per bottle.
     */
    public function getReceivedProductUnitCostCentsAttribute(): ?int
    {
        $unitCost = $this->received_unit_cost_cents;
        $product = $this->relationLoaded('product') ? $this->product : $this->product()->first();

        if ($unitCost === null || ! $product) {
            return $unitCost;
        }

        if ($product->sale_unit === 'bottle' && (int) $product->volume_ml > 0) {
            return (int) round($unitCost * (int) $product->volume_ml);
        }

        return $unitCost;
    }
}
