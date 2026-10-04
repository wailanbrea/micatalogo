<?php

namespace App\Services;

use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductInventory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** All calls are made under InventoryService's product locks and DB transaction. */
class FifoCostService
{
    public function quantity(Product $product, ProductInventory $inventory): int
    {
        return in_array($product->sale_unit, ['bottle', 'ml'], true)
            ? (int) ($inventory->available_ml ?? ($inventory->stock_quantity * ($product->sale_unit === 'bottle' ? $product->volume_ml : 1)))
            : (int) $inventory->stock_quantity;
    }

    public function initialize(Product $product, ProductInventory $inventory): void
    {
        if (InventoryLot::where('product_id', $product->id)->exists()) {
            return;
        }
        $quantity = $this->quantity($product, $inventory);
        if ($quantity <= 0) {
            return;
        }
        $divisor = $product->sale_unit === 'bottle' ? max(1, (int) $product->volume_ml) : 1;
        $cost = $inventory->cost_price === null ? null : (int) round($quantity * (float) $inventory->cost_price * 100 / $divisor);
        $this->receive($product, $quantity, $cost, 'opening_balance', $product->created_at);
    }

    public function receive(Product $product, int $quantity, ?int $costCents, string $origin = 'receipt', mixed $receivedAt = null): InventoryLot
    {
        return InventoryLot::create([
            'product_id' => $product->id, 'received_quantity' => $quantity,
            'remaining_quantity' => $quantity, 'received_cost_cents' => $costCents,
            'remaining_cost_cents' => $costCents,
            'quantity_unit' => in_array($product->sale_unit, ['bottle', 'ml'], true) ? 'ml' : 'unit',
            'origin' => $origin, 'received_at' => $receivedAt ?? now(),
        ]);
    }

    public function consume(Product $source, int $quantity, InventoryMovement $movement): ?int
    {
        $remaining = $quantity;
        $cost = 0;
        $known = true;
        $lots = InventoryLot::where('product_id', $source->id)->where('remaining_quantity', '>', 0)
            ->orderBy('received_at')->orderBy('id')->lockForUpdate()->get();
        foreach ($lots as $lot) {
            if ($remaining === 0) {
                break;
            }
            $used = min($remaining, $lot->remaining_quantity);
            $allocatedCost = $lot->remaining_cost_cents === null ? null
                : ($used === $lot->remaining_quantity ? $lot->remaining_cost_cents
                    : intdiv($lot->remaining_cost_cents * $used, $lot->remaining_quantity));
            DB::table('inventory_lot_allocations')->insert([
                'inventory_lot_id' => $lot->id, 'inventory_movement_id' => $movement->id,
                'quantity' => $used, 'cost_cents' => $allocatedCost,
            ]);
            $lot->remaining_quantity -= $used;
            if ($allocatedCost !== null) {
                $lot->remaining_cost_cents -= $allocatedCost;
                $cost += $allocatedCost;
            } else {
                $known = false;
            }
            $lot->save();
            $remaining -= $used;
        }
        if ($remaining > 0) {
            throw new InvalidArgumentException('Stock insuficiente en los lotes; revisa el inventario físico.', 409);
        }

        return $known ? $cost : null;
    }
}
