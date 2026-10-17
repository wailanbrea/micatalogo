<?php

namespace App\Services;

use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\ProductInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Converts legacy unit lots to milliliters while retaining all cost totals. */
class LegacyBottleFifoMigration
{
    public function canConvert(Product $product, ?ProductInventory $inventory, int $volumeMl): bool
    {
        if ($volumeMl < 1 || ! $inventory?->track_inventory) {
            return false;
        }

        $lots = InventoryLot::query()->where('product_id', $product->id)->get();
        if ($lots->isEmpty()) {
            return true;
        }

        if ((int) $lots->sum('remaining_quantity') !== (int) $inventory->stock_quantity) {
            return false;
        }

        $allocations = DB::table('inventory_lot_allocations')
            ->whereIn('inventory_lot_id', $lots->modelKeys())
            ->get(['inventory_lot_id', 'quantity', 'cost_cents'])
            ->groupBy('inventory_lot_id');

        foreach ($lots as $lot) {
            if ($lot->quantity_unit !== 'unit'
                || (int) $lot->received_quantity < 1
                || (int) $lot->remaining_quantity < 0
                || (int) $lot->remaining_quantity > (int) $lot->received_quantity
                || (int) $lot->received_quantity > intdiv(PHP_INT_MAX, $volumeMl)) {
                return false;
            }

            $lotAllocations = $allocations->get($lot->id, collect());
            if ((int) $lotAllocations->sum('quantity') !== (int) $lot->received_quantity - (int) $lot->remaining_quantity) {
                return false;
            }

            if ($lot->received_cost_cents === null) {
                if ($lot->remaining_cost_cents !== null || $lotAllocations->contains(fn ($row) => $row->cost_cents !== null)) {
                    return false;
                }
            } elseif ($lot->remaining_cost_cents === null
                || $lotAllocations->contains(fn ($row) => $row->cost_cents === null)
                || (int) $lot->remaining_cost_cents + (int) $lotAllocations->sum('cost_cents') !== (int) $lot->received_cost_cents) {
                return false;
            }

            foreach ($lotAllocations as $allocation) {
                if ((int) $allocation->quantity > intdiv(PHP_INT_MAX, $volumeMl)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** Convert every eligible lot and allocation atomically; costs are untouched. */
    public function convert(Product $product, ProductInventory $inventory, int $volumeMl): int
    {
        $lots = InventoryLot::query()->where('product_id', $product->id)->lockForUpdate()->get();
        if ($lots->isEmpty()) {
            return 0;
        }

        if (! $this->canConvert($product, $inventory, $volumeMl)) {
            throw ValidationException::withMessages([
                'sale_unit' => 'Los lotes históricos no cuadran con el stock o sus costos; no se cambió la presentación.',
            ]);
        }

        $allocations = DB::table('inventory_lot_allocations')
            ->whereIn('inventory_lot_id', $lots->modelKeys())
            ->lockForUpdate()
            ->get(['id', 'quantity']);

        foreach ($allocations as $allocation) {
            DB::table('inventory_lot_allocations')->where('id', $allocation->id)->update([
                'quantity' => (int) $allocation->quantity * $volumeMl,
            ]);
        }
        foreach ($lots as $lot) {
            $lot->forceFill([
                'received_quantity' => (int) $lot->received_quantity * $volumeMl,
                'remaining_quantity' => (int) $lot->remaining_quantity * $volumeMl,
                'quantity_unit' => 'ml',
            ])->save();
        }

        return $lots->count();
    }
}
