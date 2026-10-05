<?php

namespace App\Services;

use App\Enums\ProductAvailabilityStatus;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;
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
    public function recordSale(Product $product, int $quantity, ?string $notes = null, ?int $userId = null, bool $createInvoice = true, ?float $unitPrice = null, ?string $paymentMethod = 'cash'): InventoryMovement
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('La cantidad vendida debe ser mayor a 0.');
        }

        return DB::transaction(function () use ($product, $quantity, $notes, $userId, $createInvoice, $unitPrice, $paymentMethod) {
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

            $fifo = app(FifoCostService::class);
            $fifoSource = $source ?? $product;
            $fifoInventory = $sourceInventory ?? $inventory;
            $fifo->initialize($fifoSource, $fifoInventory);

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
                'unit_price' => $unitPrice ?? $product->currentPrice(),
                'unit_cost' => $inventory->cost_price,
                'notes' => $notes ?: ($product->isDecant()
                    ? "Venta de decant de {$product->volume_ml} ml"
                    : 'Venta registrada'),
                'user_id' => $userId,
                'created_at' => now(),
            ]);

            $costCents = $fifo->consume($fifoSource, $consumedMl ?? $quantity, $movement);
            $movement->update([
                'total_cost_cents' => $costCents,
                'unit_cost' => $costCents === null ? null : $costCents / (100 * $quantity),
            ]);

            if ($createInvoice) {
                $this->createInvoiceForSales(
                    $product->shop_id,
                    [['product' => $product, 'quantity' => $quantity, 'unit_price' => $unitPrice]],
                    [$movement],
                    $userId,
                    'web',
                    'paid',
                    0,
                    0,
                    $paymentMethod ?? 'cash'
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
    public function recordCartSales(array $sales, ?int $userId = null, string $channel = 'whatsapp', string $paymentStatus = 'paid', float $discount = 0, float $tax = 0, ?string $paymentMethod = 'cash'): array
    {
        return DB::transaction(function () use ($sales, $userId, $channel, $paymentStatus, $discount, $tax, $paymentMethod): array {
            if ($sales === [] || $discount < 0 || $tax < 0) {
                throw new InvalidArgumentException('La venta o sus importes no son válidos.');
            }
            $movements = collect($sales)->map(fn (array $sale) => $this->recordSale(
                $sale['product'],
                $sale['quantity'],
                $channel === 'pos' ? 'Venta POS sincronizada' : 'Cobro de carrito compartido por WhatsApp',
                $userId,
                false,
                $sale['unit_price'] ?? null,
                $paymentMethod ?? 'cash',
            ))->all();

            $this->createInvoiceForSales($sales[0]['product']->shop_id, $sales, $movements, $userId, $channel, $paymentStatus, $discount, $tax, $paymentMethod ?? 'cash');

            return $movements;
        });
    }

    /**
     * Record restock of units into inventory.
     *
     * @throws InvalidArgumentException
     */
    public function recordRestock(Product $product, int $quantity, ?string $notes = null, ?int $userId = null, ?float $unitCost = null): InventoryMovement
    {
        if ($quantity <= 0 || ($unitCost !== null && $unitCost < 0)) {
            throw new InvalidArgumentException('La cantidad a reponer debe ser mayor a 0.');
        }

        return DB::transaction(function () use ($product, $quantity, $notes, $userId, $unitCost) {
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

            $fifo = app(FifoCostService::class);
            $fifo->initialize($product, $inventory);
            $cost = $unitCost ?? ($inventory->cost_price === null ? null : (float) $inventory->cost_price);
            $fifo->receive($product, $product->sale_unit === 'bottle' ? $quantity * (int) $product->volume_ml : $quantity,
                $cost === null ? null : (int) round($cost * $quantity * 100));

            $availableMl = $this->availableMl($product, $inventory);
            $inventory->stock_quantity = $after;
            if ($unitCost !== null) {
                $inventory->cost_price = $unitCost;
            }
            if ($availableMl !== null) {
                $inventory->available_ml = $availableMl + ($product->sale_unit === 'bottle' ? $quantity * $product->volume_ml : $quantity);
            }
            $inventory->save();

            if ($cost !== null) {
                app(ProductPricingService::class)->propose($product, $cost, $userId);
            }

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

    /** Restores returned goods at their sale's captured cost, never the latest cost. */
    public function recordReturn(Product $product, int $quantity, ?int $costCents, ?int $userId, ?string $notes = null): InventoryMovement
    {
        if ($quantity <= 0 || ($costCents !== null && $costCents < 0)) throw new InvalidArgumentException('Devolución no válida.');
        return DB::transaction(function () use ($product, $quantity, $costCents, $userId, $notes) {
            $product = Product::withTrashed()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $inventory = $product->inventory()->lockForUpdate()->firstOrFail();
            $source = $product->isDecant() ? Product::withTrashed()->whereKey($product->inventory_source_product_id)->lockForUpdate()->firstOrFail() : $product;
            $sourceInventory = $source->id === $product->id ? $inventory : $source->inventory()->lockForUpdate()->firstOrFail();
            if (! $sourceInventory->track_inventory) throw new InvalidArgumentException('El inventario fuente no está controlado.');
            $fifo = app(FifoCostService::class);
            $fifo->initialize($source, $sourceInventory);
            $canonical = $this->consumedMl($product, $quantity) ?? $quantity;
            $available = $this->availableMl($source, $sourceInventory);
            $before = $product->isDecant() ? intdiv((int) $available, $product->volume_ml) : $inventory->stock_quantity;
            $fifo->receive($source, $canonical, $costCents, 'sale_return');
            if ($available !== null) {
                $sourceInventory->available_ml = $available + $canonical;
                $sourceInventory->stock_quantity = $this->stockUnitsFromMl($source, $available + $canonical);
            } else $sourceInventory->stock_quantity += $quantity;
            $sourceInventory->save();
            $inventory->sold_quantity = max(0, $inventory->sold_quantity - $quantity);
            $inventory->save();
            $this->syncAvailability($source, $sourceInventory->stock_quantity);
            $this->syncDependentDecants($source);
            $after = $product->isDecant() ? intdiv((int) $sourceInventory->available_ml, $product->volume_ml) : $sourceInventory->stock_quantity;
            return InventoryMovement::create(['product_id' => $product->id, 'type' => 'return', 'quantity' => $quantity,
                'stock_before' => $before, 'stock_after' => $after, 'unit_price' => null,
                'unit_cost' => $costCents === null ? null : $costCents / (100 * $quantity), 'total_cost_cents' => $costCents,
                'user_id' => $userId, 'notes' => $notes ?: 'Devolución con costo de venta original', 'created_at' => now()]);
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

            $fifo = app(FifoCostService::class);
            $fifo->initialize($product, $inventory);
            $canonicalBefore = $fifo->quantity($product, $inventory);
            $canonicalAfter = $product->sale_unit === 'bottle' ? $after * (int) $product->volume_ml : $after;

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

            $movement = InventoryMovement::create([
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
            if ($canonicalAfter > $canonicalBefore) {
                // A physical count does not prove acquisition cost.
                $fifo->receive($product, $canonicalAfter - $canonicalBefore, null, 'physical_count');
            } elseif ($canonicalAfter < $canonicalBefore) {
                $fifo->consume($product, $canonicalBefore - $canonicalAfter, $movement);
            }

            return $movement;
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
    private function createInvoiceForSales(int $shopId, array $sales, array $movements, ?int $userId, string $channel = 'whatsapp', string $paymentStatus = 'paid', float $discount = 0, float $tax = 0, ?string $paymentMethod = 'cash'): Invoice
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
            $unitPrice = (float) ($sale['unit_price'] ?? $product->currentPrice());
            $lineDiscount = (float) ($sale['discount'] ?? 0);
            $lineTax = (float) ($sale['tax'] ?? 0);
            $lineTotal = round($unitPrice * $quantity - $lineDiscount + $lineTax, 2);
            if ($lineTotal < 0) {
                throw new InvalidArgumentException('El descuento supera el importe de la línea.');
            }
            $total += $lineTotal;

            $invoice->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_code' => $product->product_code,
                'sale_unit' => $product->sale_unit,
                'volume_ml' => $product->volume_ml,
                'inventory_source_product_id' => $product->inventory_source_product_id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'discount' => $lineDiscount,
                'tax' => $lineTax,
                'total_cost_cents' => $movements[$index]->total_cost_cents ?? null,
            ]);

            if (isset($movements[$index])) {
                $movements[$index]->forceFill(['invoice_id' => $invoice->id])->saveQuietly();
            }
        }

        if ($discount > $total) {
            throw new InvalidArgumentException('El descuento supera el subtotal.');
        }
        $remainingDiscount = (int) round($discount * 100);
        $remainingSubtotal = (int) round($total * 100);
        foreach ($invoice->items()->orderBy('id')->get() as $item) {
            $lineCents = (int) round((float) $item->line_total * 100);
            $allocated = $remainingSubtotal > 0
                ? ($lineCents === $remainingSubtotal ? $remainingDiscount : intdiv($remainingDiscount * $lineCents, $remainingSubtotal))
                : 0;
            $item->update(['general_discount_cents' => $allocated]);
            $remainingSubtotal -= $lineCents;
            $remainingDiscount -= $allocated;
        }
        $invoice->update(['subtotal' => $total, 'discount' => $discount, 'tax' => $tax, 'total' => round($total - $discount + $tax, 2)]);

        $this->applySalespersonCommission($invoice, $shopId, $userId, (float) $invoice->total);

        // For non-POS sales with status paid, record InvoicePayment and link to cash register
        if ($channel !== 'pos' && $paymentStatus === 'paid' && (float) $invoice->total > 0) {
            $shop = Shop::find($shopId);
            $user = $userId ? User::find($userId) : $shop?->user;
            if ($shop && $user) {
                app(PaymentService::class)->processInvoicePayments(
                    $shop,
                    $invoice,
                    $user,
                    [['method' => $paymentMethod ?? 'cash', 'amount' => (string) $invoice->total]],
                    0,
                    null
                );
            }
        }

        return $invoice;
    }

    private function applySalespersonCommission(Invoice $invoice, int $shopId, ?int $userId, float $total): void
    {
        if (! $userId) {
            return;
        }

        $seller = ShopSeller::query()
            ->where('shop_id', $shopId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        if (! $seller) {
            return;
        }

        $amount = $seller->commission_type === 'percentage'
            ? round($total * ((float) $seller->commission_value / 100), 2)
            : (float) $seller->commission_value;

        $invoice->update([
            'salesperson_id' => $userId,
            'commission_type' => $seller->commission_type,
            'commission_value' => $seller->commission_value,
            'commission_amount' => $amount,
        ]);
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
