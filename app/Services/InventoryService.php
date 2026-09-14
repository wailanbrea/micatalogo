<?php

namespace App\Services;

use App\Enums\ProductAvailabilityStatus;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    /**
     * Record an external or direct sale of units.
     *
     * @throws InvalidArgumentException
     */
    public function recordSale(Product $product, int $quantity, ?string $notes = null, ?int $userId = null): InventoryMovement
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('La cantidad vendida debe ser mayor a 0.');
        }

        return DB::transaction(function () use ($product, $quantity, $notes, $userId) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $inventory = ProductInventory::where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if (! $inventory?->track_inventory) {
                throw new InvalidArgumentException('Activa el control de inventario para registrar movimientos de este producto.');
            }

            if ($inventory->stock_quantity < $quantity) {
                throw new InvalidArgumentException(
                    "Stock insuficiente. Solo quedan {$inventory->stock_quantity} unidades disponibles para {$product->name}."
                );
            }

            $before = $inventory->stock_quantity;
            $after = $before - $quantity;

            $inventory->stock_quantity = $after;
            $inventory->sold_quantity += $quantity;
            $inventory->save();

            // Sincronizar estado de disponibilidad si llega a 0
            if ($after === 0 && $product->availability_status !== ProductAvailabilityStatus::OutOfStock) {
                $product->availability_status = ProductAvailabilityStatus::OutOfStock;
                $product->save();
            }

            return InventoryMovement::create([
                'product_id' => $product->id,
                'type' => 'sale',
                'quantity' => -$quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'notes' => $notes ?: 'Venta registrada',
                'user_id' => $userId,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Record restock of units into inventory.
     *
     * @throws InvalidArgumentException
     */
    public function recordRestock(Product $product, int $quantity, ?string $notes = null, ?int $userId = null): InventoryMovement
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('La cantidad a reponer debe ser mayor a 0.');
        }

        return DB::transaction(function () use ($product, $quantity, $notes, $userId) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $inventory = ProductInventory::where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if (! $inventory?->track_inventory) {
                throw new InvalidArgumentException('Activa el control de inventario para registrar movimientos de este producto.');
            }

            $before = $inventory->stock_quantity;
            $after = $before + $quantity;

            $inventory->stock_quantity = $after;
            $inventory->save();

            // Si estaba agotado y ahora tiene stock, restaurar a disponible
            if ($after > 0 && $product->availability_status === ProductAvailabilityStatus::OutOfStock) {
                $product->availability_status = ProductAvailabilityStatus::Available;
                $product->save();
            }

            return InventoryMovement::create([
                'product_id' => $product->id,
                'type' => 'restock',
                'quantity' => $quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'notes' => $notes ?: 'Reposición de inventario',
                'user_id' => $userId,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Adjust physical stock to an exact count.
     *
     * @throws InvalidArgumentException
     */
    public function adjustStock(Product $product, int $newStock, ?string $notes = null, ?int $userId = null): InventoryMovement
    {
        if ($newStock < 0) {
            throw new InvalidArgumentException('El stock real no puede ser negativo.');
        }

        return DB::transaction(function () use ($product, $newStock, $notes, $userId) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $inventory = ProductInventory::where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if (! $inventory?->track_inventory) {
                throw new InvalidArgumentException('Activa el control de inventario para registrar movimientos de este producto.');
            }

            $before = $inventory->stock_quantity;
            $after = $newStock;
            $diff = $after - $before;

            $inventory->stock_quantity = $after;
            // El ajuste NO altera la cantidad vendida
            $inventory->save();

            if ($after === 0 && $product->availability_status !== ProductAvailabilityStatus::OutOfStock) {
                $product->availability_status = ProductAvailabilityStatus::OutOfStock;
                $product->save();
            } elseif ($after > 0 && $product->availability_status === ProductAvailabilityStatus::OutOfStock) {
                $product->availability_status = ProductAvailabilityStatus::Available;
                $product->save();
            }

            return InventoryMovement::create([
                'product_id' => $product->id,
                'type' => 'adjustment',
                'quantity' => $diff,
                'stock_before' => $before,
                'stock_after' => $after,
                'notes' => $notes ?: 'Ajuste de inventario físico',
                'user_id' => $userId,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Get aggregate inventory summary, financial valuation, and key lists for a shop.
     */
    public function getShopInventorySummary(Shop $shop): array
    {
        $products = $shop->products()
            ->with(['inventory', 'images', 'primaryImage', 'shopCategory'])
            ->get();

        $trackedProducts = $products->filter(fn (Product $p) => $p->isInventoryTracked());

        $totalAvailable = $trackedProducts->sum(fn (Product $p) => $p->inventory->stock_quantity);
        $totalSold = $trackedProducts->sum(fn (Product $p) => $p->inventory->sold_quantity);

        // Valuación financiera del inventario
        $totalInventoryValue = $trackedProducts->sum(fn (Product $p) => $p->inventory->inventory_value);
        $totalGrossProfit = $trackedProducts->sum(fn (Product $p) => $p->inventory->gross_profit);

        $lowStockProducts = $trackedProducts->filter(function (Product $p) {
            return $p->inventory->stock_quantity > 0
                && $p->inventory->stock_quantity <= $p->inventory->low_stock_threshold;
        })->sortBy('inventory.stock_quantity')->values();

        $outOfStockProducts = $trackedProducts->filter(function (Product $p) {
            return $p->inventory->stock_quantity <= 0;
        })->values();

        $topSellingProducts = $trackedProducts->filter(function (Product $p) {
            return $p->inventory->sold_quantity > 0;
        })->sortByDesc('inventory.sold_quantity')->take(5)->values();

        // Movimientos recientes globales de la tienda
        $recentMovements = InventoryMovement::whereIn('product_id', $products->pluck('id'))
            ->with('product')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->take(15)
            ->get();

        return [
            'total_available' => $totalAvailable,
            'total_sold' => $totalSold,
            'total_inventory_value' => $totalInventoryValue,
            'total_gross_profit' => $totalGrossProfit,
            'low_stock_count' => $lowStockProducts->count(),
            'out_of_stock_count' => $outOfStockProducts->count(),
            'low_stock_products' => $lowStockProducts,
            'out_of_stock_products' => $outOfStockProducts,
            'top_selling_products' => $topSellingProducts,
            'recent_movements' => $recentMovements,
            'all_products' => $products,
        ];
    }
}
