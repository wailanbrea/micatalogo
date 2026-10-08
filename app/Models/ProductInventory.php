<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ProductInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'track_inventory',
        'cost_price',
        'stock_quantity',
        'available_ml',
        'opened_bottles',
        'sold_quantity',
        'low_stock_threshold',
    ];

    protected function casts(): array
    {
        return [
            'track_inventory' => 'boolean',
            'cost_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'available_ml' => 'integer',
            'opened_bottles' => 'integer',
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

    public function isLowStock(?Product $product = null): bool
    {
        if (! $this->track_inventory || $this->stock_quantity <= 0) {
            return false;
        }

        $product ??= $this->product;
        $lowByUnits = $this->stock_quantity <= $this->low_stock_threshold;

        if (! $product || ! in_array($product->sale_unit, ['bottle', 'ml', 'decant'], true)) {
            return $lowByUnits;
        }

        $sourceInventory = $this;
        if ($product->isDecant()) {
            $sourceInventory = $product->sourceProduct?->inventory;
        }

        if (! $sourceInventory) {
            return $lowByUnits;
        }

        $availableMl = $sourceInventory->available_ml;
        if ($availableMl === null && $sourceInventory->product) {
            $sourceProduct = $sourceInventory->product;
            $availableMl = match ($sourceProduct->sale_unit) {
                'bottle' => $sourceProduct->volume_ml
                    ? $sourceInventory->stock_quantity * $sourceProduct->volume_ml
                    : null,
                'ml' => $sourceInventory->stock_quantity,
                default => null,
            };
        }

        return $lowByUnits
            || ($availableMl !== null && $availableMl <= (int) config('catalog.inventory.low_ml_alert_threshold', 200));
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
        if ($this->product?->isDecant()) {
            return 0.0; // Presentations share the source stock; do not count it twice.
        }
        if (InventoryLot::where('product_id', $this->product_id)->exists()) {
            return (float) Money::toDecimal((int) InventoryLot::where('product_id', $this->product_id)->sum('remaining_cost_cents'));
        }
        if (! $this->cost_price || $this->stock_quantity <= 0) {
            return 0.0;
        }

        return (float) Money::toDecimal(Money::toCents($this->cost_price) * $this->stock_quantity);
    }

    /**
     * Gross profit uses the price and cost captured when each sale was recorded.
     */
    public function getGrossProfitAttribute(): float
    {
        if (! $this->product) {
            return 0.0;
        }

        $invoiced = InvoiceItem::query()
            ->where('product_id', $this->product_id)
            ->whereNotNull('total_cost_cents')
            ->selectRaw('COALESCE(SUM(ROUND((line_total - tax) * 100) - general_discount_cents - total_cost_cents), 0) as total_cents')
            ->value('total_cents');
        $legacy = $this->product->inventoryMovements()
            ->reorder()
            ->where('type', 'sale')
            ->whereNull('invoice_id')
            ->whereNotNull('unit_price')
            ->whereNotNull('unit_cost')
            ->selectRaw('COALESCE(SUM(ROUND(ABS(quantity) * unit_price * 100) - COALESCE(total_cost_cents, ROUND(ABS(quantity) * unit_cost * 100))), 0) as total_cents')
            ->value('total_cents');

        $refunds = DB::table('invoice_return_items')
            ->join('invoice_items', 'invoice_items.id', '=', 'invoice_return_items.invoice_item_id')
            ->where('invoice_items.product_id', $this->product_id)->whereNotNull('invoice_items.total_cost_cents')
            ->selectRaw('COALESCE(SUM(ROUND((refund - tax_refund) * 100) - CASE WHEN restock = 1 THEN COALESCE(invoice_return_items.total_cost_cents, 0) ELSE 0 END), 0) as total_cents')
            ->value('total_cents');

        return (float) Money::toDecimal((int) $invoiced + (int) $legacy - (int) $refunds);
    }

    /**
     * Profit margin per unit in currency: sale_price - cost_price
     */
    public function getUnitMarginAttribute(): ?float
    {
        if ($this->cost_price === null || ! $this->product) {
            return null;
        }

        return (float) Money::toDecimal(
            $this->product->currentPriceCents() - Money::toCents($this->cost_price)
        );
    }

    /**
     * Gross margin percentage over the effective selling price.
     */
    public function getMarginPercentageAttribute(): ?float
    {
        if ($this->cost_price === null || ! $this->product || $this->product->currentPriceCents() <= 0) {
            return null;
        }

        $marginCents = $this->product->currentPriceCents() - Money::toCents($this->cost_price);

        return round(($marginCents / $this->product->currentPriceCents()) * 100, 1);
    }
}
