<?php

namespace App\Services;

use App\Enums\ProductAvailabilityStatus;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InventoryService
{
    /**
     * Record an external or direct sale of units.
     *
     * @throws InvalidArgumentException
     */
    public function recordSale(Product $product, int $quantity, ?string $notes = null, ?int $userId = null, bool $createInvoice = true, ?float $unitPrice = null): InventoryMovement
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('La cantidad vendida debe ser mayor a 0.');
        }

        return DB::transaction(function () use ($product, $quantity, $notes, $userId, $createInvoice, $unitPrice) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $inventory = ProductInventory::where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if (! $inventory?->track_inventory) {
                throw new InvalidArgumentException('Activa el control de inventario para registrar movimientos de este producto.');
            }

            $source = null;
            $sourceInventory = null;
            if ($product->isDecant()) {
                $source = Product::query()->lockForUpdate()->findOrFail($product->inventory_source_product_id);
                $sourceInventory = ProductInventory::where('product_id', $source->id)->lockForUpdate()->first();
                if (! $sourceInventory?->track_inventory) {
                    throw new InvalidArgumentException('La botella fuente no tiene control de inventario activo.');
                }
            }

            $before = $product->isDecant()
                ? intdiv((int) $this->availableMl($source, $sourceInventory), (int) $product->volume_ml)
                : $inventory->stock_quantity;
            $consumedMl = $product->isDecant()
                ? $quantity * (int) $product->volume_ml
                : $this->consumedMl($product, $quantity);
            $availableMl = $product->isDecant()
                ? $this->availableMl($source, $sourceInventory)
                : $this->availableMl($product, $inventory);

            if ($before < $quantity || ($consumedMl !== null && ($availableMl === null || $availableMl < $consumedMl))) {
                $availableLabel = $consumedMl !== null && $availableMl !== null
                    ? "Solo quedan {$availableMl} ml disponibles"
                    : "Solo quedan {$before} unidades disponibles";
                throw new InvalidArgumentException("Stock insuficiente para {$product->name}. {$availableLabel}.", 409);
            }

            $after = $before - $quantity;

            if ($product->isDecant()) {
                $sourceAvailableAfter = $availableMl - $consumedMl;
                $sourceInventory->available_ml = $sourceAvailableAfter;
                $sourceInventory->stock_quantity = $this->stockUnitsFromMl($source, $sourceAvailableAfter);
                $sourceInventory->save();
            } else {
                $inventory->stock_quantity = $after;
                if ($consumedMl !== null) {
                    $inventory->available_ml = $availableMl - $consumedMl;
                }
            }
            $inventory->sold_quantity += $quantity;
            $inventory->save();

            $this->syncAvailability($product, $after);
            if ($source) {
                $this->syncAvailability($source, $sourceInventory->stock_quantity);
                $this->syncDependentDecants($source);
            } else {
                $this->syncDependentDecants($product);
            }

            $movement = InventoryMovement::create([
                'product_id' => $product->id,
                'type' => 'sale',
                'quantity' => -$quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'unit_price' => $unitPrice ?? $product->price,
                'unit_cost' => $inventory->cost_price,
                'notes' => $notes ?: ($product->isDecant()
                    ? "Venta de decant de {$product->volume_ml} ml"
                    : 'Venta registrada'),
                'user_id' => $userId,
                'created_at' => now(),
            ]);

            if ($createInvoice) {
                $this->createInvoiceForSales(
                    $product->shop_id,
                    [['product' => $product, 'quantity' => $quantity, 'unit_price' => $unitPrice]],
                    [$movement],
                    $userId,
                );
            }

            return $movement;
        });
    }

    /**
     * Register all lines from a shared cart as one atomic checkout.
     *
     * @param  array<int, array{product: Product, quantity: int, unit_price?: float}>  $sales
     * @return array<int, InventoryMovement>
     */
    public function recordCartSales(array $sales, ?int $userId = null, string $channel = 'whatsapp', string $paymentStatus = 'paid'): array
    {
        return DB::transaction(function () use ($sales, $userId, $channel, $paymentStatus): array {
            $movements = collect($sales)->map(fn (array $sale) => $this->recordSale(
                $sale['product'],
                $sale['quantity'],
                $channel === 'pos' ? 'Venta POS sincronizada' : 'Cobro de carrito compartido por WhatsApp',
                $userId,
                false,
                $sale['unit_price'] ?? null,
            ))->all();

            $this->createInvoiceForSales($sales[0]['product']->shop_id, $sales, $movements, $userId, $channel, $paymentStatus);

            return $movements;
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

            if ($product->isDecant()) {
                throw new InvalidArgumentException('Los decants se reponen agregando stock a su botella fuente.');
            }

            $before = $inventory->stock_quantity;
            $after = $before + $quantity;

            $inventory->stock_quantity = $after;
            $availableMl = $this->availableMl($product, $inventory);
            if ($availableMl !== null) {
                $inventory->available_ml = $availableMl + ($product->sale_unit === 'bottle' ? $quantity * $product->volume_ml : $quantity);
            }
            $inventory->save();

            $this->syncAvailability($product, $after);
            $this->syncDependentDecants($product);

            return InventoryMovement::create([
                'product_id' => $product->id,
                'type' => 'restock',
                'quantity' => $quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'unit_price' => $product->price,
                'unit_cost' => $inventory->cost_price,
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

            if ($product->isDecant()) {
                throw new InvalidArgumentException('Los decants se ajustan modificando el stock de su botella fuente.');
            }

            $before = $inventory->stock_quantity;
            $after = $newStock;
            $diff = $after - $before;

            $inventory->stock_quantity = $after;
            if ($product->sale_unit === 'bottle') {
                $inventory->available_ml = $after * $product->volume_ml;
            } elseif ($product->sale_unit === 'ml') {
                $inventory->available_ml = $after;
            } else {
                $inventory->available_ml = null;
            }
            // El ajuste NO altera la cantidad vendida
            $inventory->save();

            $this->syncAvailability($product, $after);
            $this->syncDependentDecants($product);

            return InventoryMovement::create([
                'product_id' => $product->id,
                'type' => 'adjustment',
                'quantity' => $diff,
                'stock_before' => $before,
                'stock_after' => $after,
                'unit_price' => $product->price,
                'unit_cost' => $inventory->cost_price,
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
            ->with(['inventory', 'images', 'primaryImage', 'shopCategory', 'sourceProduct.inventory'])
            ->get();

        $trackedProducts = $products->filter(fn (Product $p) => $p->isInventoryTracked());

        $totalAvailable = $trackedProducts->sum(fn (Product $p) => $p->inventory->stock_quantity);
        $totalSold = $trackedProducts->sum(fn (Product $p) => $p->inventory->sold_quantity);

        // Valuación financiera del inventario
        $totalInventoryValue = $trackedProducts->sum(fn (Product $p) => $p->inventory->inventory_value);
        $totalGrossProfit = $trackedProducts->sum(fn (Product $p) => $p->inventory->gross_profit);

        $lowStockProducts = $trackedProducts->filter(function (Product $p) {
            return $p->inventory->isLowStock($p);
        })->sortBy('inventory.stock_quantity')->values();

        $outOfStockProducts = $trackedProducts->filter(function (Product $p) {
            return $p->inventory->stock_quantity <= 0;
        })->values();

        $topSellingProducts = $trackedProducts->filter(function (Product $p) {
            return $p->inventory->sold_quantity > 0;
        })->sortByDesc('inventory.sold_quantity')->take(5)->values();

        $decantProducts = $products->filter(fn (Product $p) => $p->isDecant() && $p->inventory_source_product_id);
        $decantRevenue = InventoryMovement::query()
            ->whereIn('product_id', $decantProducts->pluck('id'))
            ->where('type', 'sale')
            ->whereNotNull('unit_price')
            ->selectRaw('product_id, COALESCE(SUM(ABS(quantity) * unit_price), 0) as revenue')
            ->groupBy('product_id')
            ->pluck('revenue', 'product_id');

        $costRecovery = [];
        foreach ($products as $product) {
            if ($product->sale_unit !== 'bottle' || ! $product->inventory?->cost_price) {
                continue;
            }

            $revenue = $decantProducts
                ->where('inventory_source_product_id', $product->id)
                ->sum(fn (Product $decant) => (float) ($decantRevenue[$decant->id] ?? 0));
            $cost = (float) $product->inventory->cost_price;

            $costRecovery[$product->id] = [
                'cost' => $cost,
                'revenue' => round($revenue, 2),
                'difference' => round($revenue - $cost, 2),
                'covered' => $revenue >= $cost,
            ];
        }

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
            'cost_recovery' => $costRecovery,
            'recent_movements' => $recentMovements,
            'all_products' => $products,
        ];
    }

    /**
     * Persist a price snapshot and link the resulting invoice to its movements.
     *
     * @param  array<int, array{product: Product, quantity: int, unit_price?: float}>  $sales
     * @param  array<int, InventoryMovement>  $movements
     */
    private function createInvoiceForSales(int $shopId, array $sales, array $movements, ?int $userId, string $channel = 'whatsapp', string $paymentStatus = 'paid'): Invoice
    {
        $invoice = Invoice::create([
            'shop_id' => $shopId,
            'user_id' => $userId,
            'invoice_number' => 'FAC-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'status' => $paymentStatus,
            'channel' => $channel,
            'currency' => 'DOP',
            'subtotal' => 0,
            'total' => 0,
            'issued_at' => now(),
        ]);

        $total = 0.0;
        foreach ($sales as $index => $sale) {
            $product = $sale['product'];
            $quantity = (int) $sale['quantity'];
            $unitPrice = (float) ($sale['unit_price'] ?? $product->price);
            $lineTotal = $unitPrice * $quantity;
            $total += $lineTotal;

            $invoice->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_code' => $product->product_code,
                'sale_unit' => $product->sale_unit,
                'volume_ml' => $product->volume_ml,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ]);

            if (isset($movements[$index])) {
                $movements[$index]->forceFill(['invoice_id' => $invoice->id])->saveQuietly();
            }
        }

        $invoice->update(['subtotal' => $total, 'total' => $total]);

        return $invoice;
    }

    private function consumedMl(Product $product, int $quantity): ?int
    {
        return match ($product->sale_unit) {
            'bottle' => $quantity * (int) $product->volume_ml,
            'ml' => $quantity,
            'decant' => $quantity * (int) $product->volume_ml,
            default => null,
        };
    }

    private function availableMl(Product $product, ProductInventory $inventory): ?int
    {
        return $inventory->available_ml ?? match ($product->sale_unit) {
            'bottle' => $product->volume_ml ? $inventory->stock_quantity * $product->volume_ml : null,
            'ml' => $inventory->stock_quantity,
            default => null,
        };
    }

    private function stockUnitsFromMl(Product $product, int $availableMl): int
    {
        return match ($product->sale_unit) {
            'bottle' => $product->volume_ml ? intdiv($availableMl, $product->volume_ml) : 0,
            'ml' => $availableMl,
            default => 0,
        };
    }

    private function syncDependentDecants(Product $source): void
    {
        if (! in_array($source->sale_unit, ['bottle', 'ml'], true)) {
            return;
        }

        $availableMl = $this->availableMl($source, $source->inventory);
        if ($availableMl === null) {
            return;
        }

        $source->decantProducts()->with('inventory')->get()->each(function (Product $decant) use ($availableMl) {
            if (! $decant->inventory?->track_inventory || ! $decant->volume_ml) {
                return;
            }

            $stock = intdiv($availableMl, $decant->volume_ml);
            $decant->inventory->stock_quantity = $stock;
            $decant->inventory->save();
            $this->syncAvailability($decant, $stock);
        });
    }

    private function syncAvailability(Product $product, int $stock): void
    {
        $status = $stock > 0 ? ProductAvailabilityStatus::Available : ProductAvailabilityStatus::OutOfStock;
        if ($product->availability_status !== $status) {
            $product->availability_status = $status;
            $product->save();
        }
    }
}
