<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Commands run inside MobileOperationService/InventoryService's tenant transaction. */
class DecantInventoryService
{
    public function available(): bool
    {
        return Schema::hasTable('decant_openings');
    }

    public function managed(Product $source): bool
    {
        return $this->available() && (DB::table('decant_openings')->where('product_id', $source->id)->exists()
            || ($source->sale_unit === 'bottle' && DB::table('decant_vials')->where('shop_id', $source->shop_id)->exists()));
    }

    public function offered(Product $product): bool
    {
        return $product->moderation_status === \App\Enums\ProductModerationStatus::Active
            && (bool) (DB::table('decant_presentation_settings')->where('product_id', $product->id)->value('offered') ?? true);
    }

    public function untrackedMl(Product $source): int
    {
        $inventory = $source->inventory()->first();
        $ml = (int) ($inventory?->available_ml ?? ((int) ($inventory?->stock_quantity ?? 0) * (int) $source->volume_ml));
        $tracked = (int) DB::table('decant_openings')->where('product_id', $source->id)->where('status', 'open')->sum('remaining_ml');

        return max(0, $ml - (int) ($inventory?->stock_quantity ?? 0) * (int) $source->volume_ml - $tracked);
    }

    public function prepared(Product $product): int
    {
        return (int) DB::table('decant_batches')->where('product_id', $product->id)->sum('remaining_quantity');
    }

    public function onDemand(Product $product): bool
    {
        return (bool) (DB::table('decant_presentation_settings')->where('product_id', $product->id)->value('on_demand') ?? true);
    }

    public function capacity(Product $product): int
    {
        $source = $product->sourceProduct;
        if (! $source || ! $product->volume_ml) {
            return 0;
        }
        if ($this->untrackedMl($source) > 0) return 0;
        $capacity = (int) DB::table('decant_openings')->where('product_id', $source->id)->where('status', 'open')->get()
            ->sum(fn ($opening) => intdiv((int) $opening->remaining_ml, (int) $product->volume_ml));
        if ($source->sale_unit === 'bottle') {
            $capacity += (int) ($source->inventory()->value('stock_quantity') ?? 0) * intdiv((int) $source->volume_ml, (int) $product->volume_ml);
        }
        $vial = DB::table('decant_vials')->where('shop_id', $product->shop_id)->where('volume_ml', $product->volume_ml)->where('active', true)->first();
        $empty = $vial ? (int) DB::table('decant_vial_lots')->where('vial_id', $vial->id)->sum('remaining_quantity') : 0;

        return min($capacity, $empty);
    }

    public function sellable(Product $product): int
    {
        if (! $this->offered($product)) return 0;
        return $this->prepared($product) + ($this->onDemand($product) ? $this->capacity($product) : 0);
    }

    public function registerOpening(Product $source, ProductInventory $inventory, int $quantity, ?int $userId): void
    {
        if (! $this->available()) {
            return;
        }
        // Never label old liquid as a tracked bottle or recost it automatically.
        $trackedMl = (int) DB::table('decant_openings')->where('product_id', $source->id)->where('status', 'open')->sum('remaining_ml');
        $legacyMl = max(0, (int) ($inventory->available_ml ?? 0) - (int) $inventory->stock_quantity * (int) $source->volume_ml - $quantity * (int) $source->volume_ml - $trackedMl);
        if ($legacyMl > 0) {
            throw new InvalidArgumentException('Esta fuente tiene perfume abierto histórico. Concilia sus aperturas antes de crear un lote trazable.', 409);
        }
        $fifo = app(FifoCostService::class);
        for ($i = 0; $i < $quantity; $i++) {
            $movement = $this->movement($source, 'decant_opening_transfer', 0, 0, $userId, 'Transferencia de costo a botella abierta');
            $cost = $fifo->consume($source, (int) $source->volume_ml, $movement);
            $movement->update(['total_cost_cents' => $cost]);
            DB::table('decant_openings')->insert([
                'public_id' => (string) Str::ulid(), 'product_id' => $source->id, 'movement_id' => $movement->id,
                'initial_ml' => $source->volume_ml, 'remaining_ml' => $source->volume_ml,
                'initial_cost_cents' => $cost, 'remaining_cost_cents' => $cost, 'lost_ml' => 0, 'status' => 'open',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $inventory->reserved_decant_ml = $trackedMl + $quantity * (int) $source->volume_ml;
        $inventory->save();
    }

    public function command(Shop $shop, array $data, int $userId): array
    {
        app(BusinessCapabilityService::class)->assert($shop, 'decants');
        if (! $this->available()) {
            throw new InvalidArgumentException('El servidor aún no tiene habilitado el esquema de Decants.', 409);
        }
        if ($data['type'] === 'decant_reconcile_opening') {
            $source = $shop->products()->where('public_id', $data['product_id'])->lockForUpdate()->firstOrFail();
            $inventory = $source->inventory()->lockForUpdate()->firstOrFail();
            $tracked = (int) DB::table('decant_openings')->where('product_id', $source->id)->where('status', 'open')->sum('remaining_ml');
            $legacy = max(0, (int) $inventory->available_ml - $inventory->stock_quantity * (int) $source->volume_ml - $tracked);
            if ($source->sale_unit !== 'bottle' || $legacy !== $data['expected_ml'] || $data['quantity'] > min($legacy, (int) $source->volume_ml)) {
                throw new InvalidArgumentException('La apertura histórica cambió o sus ml no son compatibles con una botella.', 409);
            }
            $fifo = app(FifoCostService::class);
            $fifo->initialize($source, $inventory);
            $movement = $this->movement($source, 'decant_reconciliation_transfer', 0, 0, $userId, 'Conciliación explícita de perfume abierto histórico');
            $cost = $fifo->consume($source, $data['quantity'], $movement);
            $movement->update(['total_cost_cents' => $cost]);
            DB::table('decant_openings')->insert(['public_id' => (string) Str::ulid(), 'product_id' => $source->id, 'movement_id' => $movement->id,
                'initial_ml' => $source->volume_ml, 'remaining_ml' => $data['quantity'], 'initial_cost_cents' => null,
                'remaining_cost_cents' => $cost, 'lost_ml' => 0, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
            $this->sync($source);

            return ['message' => 'Apertura histórica conciliada; no se han creado frascos preparados ni ventas.'];
        }
        if ($data['type'] === 'decant_vial_receive') {
            $vial = DB::table('decant_vials')->where('shop_id', $shop->id)->where('volume_ml', $data['volume_ml'])->lockForUpdate()->first();
            if (! $vial) {
                $id = DB::table('decant_vials')->insertGetId(['public_id' => (string) Str::ulid(), 'shop_id' => $shop->id,
                    'volume_ml' => $data['volume_ml'], 'name' => $data['name'] ?? $data['volume_ml'].' ml',
                    'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            } else {
                $id = $vial->id;
                DB::table('decant_vials')->where('id', $id)->update(['active' => true, 'updated_at' => now()]);
            }
            DB::table('decant_vial_lots')->insert(['vial_id' => $id, 'received_quantity' => $data['quantity'],
                'remaining_quantity' => $data['quantity'], 'received_cost_cents' => Money::toCents($data['unit_cost']) * $data['quantity'],
                'remaining_cost_cents' => Money::toCents($data['unit_cost']) * $data['quantity'],
                'received_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $shop->products()->whereIn('sale_unit', ['bottle', 'ml'])->get()->each(function (Product $source): void {
                if ($this->managed($source)) $this->sync($source);
            });

            return ['message' => 'Envases recibidos con costo FIFO.'];
        }
        if ($data['type'] === 'decant_vial_update') {
            $vial = DB::table('decant_vials')->where('shop_id', $shop->id)->where('public_id', $data['vial_id'])->lockForUpdate()->first();
            if (! $vial) {
                abort(404);
            }
            DB::table('decant_vials')->where('id', $vial->id)->update(['name' => $data['name'] ?? $vial->name,
                'active' => $data['active'], 'updated_at' => now()]);
            $shop->products()->whereIn('sale_unit', ['bottle', 'ml'])->get()->each(function (Product $source): void {
                if ($this->managed($source)) $this->sync($source);
            });

            return ['message' => 'Envase actualizado; se conserva su historial.'];
        }
        if ($data['type'] === 'decant_prepare') {
            $product = $shop->products()->where('public_id', $data['product_id'])->lockForUpdate()->firstOrFail();
            if (! empty($data['opening_id'])) {
                $opening = $this->opening($shop, $data['opening_id']);
                $this->checkOpening($opening, $data);
            } else {
                $source = $shop->products()->where('public_id', $data['source_product_id'])->lockForUpdate()->firstOrFail();
                if ((int) $source->inventory()->lockForUpdate()->value('stock_quantity') !== (int) $data['expected_stock']) {
                    throw new InvalidArgumentException('Cambió la existencia de botellas selladas.', 409);
                }
                app(InventoryService::class)->openBottle($source, 1, null, $userId, true);
                $opening = DB::table('decant_openings')->where('product_id', $source->id)->orderByDesc('id')->first();
            }
            if (! $product->isDecant() || (int) $product->inventory_source_product_id !== (int) $opening->product_id) {
                throw new InvalidArgumentException('La presentación no pertenece a esta botella.', 422);
            }
            $locked = (bool) DB::table('decant_presentation_settings')->where('product_id', $product->id)->value('price_locked');
            if ($locked && Money::toCents($data['price']) !== Money::toCents($product->currentPriceDecimal())) {
                throw new InvalidArgumentException('Esta presentación tiene un precio fijado. Cámbialo desde Cambiar precio antes de preparar.', 409);
            }
            $batch = $this->prepare($product, $opening, $data['quantity'], Money::toCents($data['price']), $userId);
            // One configured catalog price; historical batches retain their own snapshot.
            $product->forceFill([$product->isOnSale() ? 'sale_price' : 'price' => $data['price']])->save();
            if (! $locked) DB::table('decant_presentation_settings')->updateOrInsert(['product_id' => $product->id], ['price_locked' => false]);
            $this->sync($product->sourceProduct);

            return ['batch_id' => $batch->public_id, 'message' => 'Preparación registrada.'];
        }
        if ($data['type'] === 'decant_presentation_update') {
            $product = $shop->products()->where('public_id', $data['product_id'])->lockForUpdate()->firstOrFail();
            abort_unless($product->isDecant(), 422);
            if (isset($data['price']) && Money::toCents($product->currentPriceDecimal()) !== Money::toCents($data['expected_price'])) {
                throw new InvalidArgumentException('El precio cambió. Actualiza antes de fijar otro precio.', 409);
            }
            $settings = array_intersect_key($data, array_flip(['offered', 'on_demand']));
            if ($settings === [] && ! isset($data['price'])) throw new InvalidArgumentException('Indica visibilidad, modalidad o precio.', 422);
            if (array_key_exists('offered', $data)) {
                $product->forceFill(['moderation_status' => $data['offered'] ? \App\Enums\ProductModerationStatus::Active : \App\Enums\ProductModerationStatus::Draft,
                    'published_at' => $data['offered'] ? ($product->published_at ?: now()) : null])->save();
            }
            if (isset($data['price'])) {
                $product->forceFill([$product->isOnSale() ? 'sale_price' : 'price' => $data['price']])->save();
                $settings['price_locked'] = true;
            }
            DB::table('decant_presentation_settings')->updateOrInsert(['product_id' => $product->id], $settings);
            $this->sync($product->sourceProduct);

            return ['message' => 'Presentación actualizada.'];
        }
        $opening = $this->opening($shop, $data['opening_id']);
        $this->checkOpening($opening, $data);
        $source = $shop->products()->whereKey($opening->product_id)->lockForUpdate()->firstOrFail();
        $inventory = $source->inventory()->lockForUpdate()->firstOrFail();
        if ($data['type'] === 'decant_sell_remainder') {
            if ($opening->remaining_ml < 1) throw new InvalidArgumentException('No queda perfume para vender.', 409);
            $volume = $opening->remaining_ml;
            $movement = $this->movement($source, 'sale', -1, 1, $userId, 'Venta del resto de la apertura '.$opening->public_id);
            $movement->update(['total_cost_cents' => $opening->remaining_cost_cents, 'unit_cost' => Money::perUnitDecimal($opening->remaining_cost_cents, 1), 'unit_price' => $data['price']]);
            DB::table('decant_remainder_sales')->insert(['opening_id' => $opening->id, 'movement_id' => $movement->id, 'volume_ml' => $volume, 'cost_cents' => $opening->remaining_cost_cents]);
            DB::table('decant_openings')->where('id', $opening->id)->update(['remaining_ml' => 0, 'remaining_cost_cents' => 0, 'updated_at' => now()]);
            $inventory->available_ml -= $volume;
            $inventory->sold_quantity++;
            $inventory->save();
            $invoice = app(InventoryService::class)->invoiceForRemainder($source, $volume, $data['price'], $movement, $userId, $data['payment_method']);
            \App\Models\PosSaleUpload::create(['shop_id' => $shop->id, 'client_sale_uuid' => $data['client_operation_uuid'],
                'payload_sha256' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)), 'invoice_id' => $invoice->id]);
            $this->sync($source);

            return ['invoice_number' => $invoice->invoice_number, 'client_sale_uuid' => $data['client_operation_uuid'], 'message' => 'Venta del resto registrada.'];
        } elseif ($data['type'] === 'decant_discard') {
            $quantity = (int) $data['quantity'];
            if ($quantity < 1 || $quantity > $opening->remaining_ml) {
                throw new InvalidArgumentException('La merma supera el perfume restante.', 409);
            }
            $cost = $this->portion($opening->remaining_cost_cents, $quantity, $opening->remaining_ml);
            DB::table('decant_openings')->where('id', $opening->id)->update([
                'remaining_ml' => $opening->remaining_ml - $quantity,
                'remaining_cost_cents' => $cost === null ? null : $opening->remaining_cost_cents - $cost,
                'lost_ml' => $opening->lost_ml + $quantity, 'updated_at' => now(),
            ]);
            $inventory->available_ml -= $quantity;
            $inventory->save();
            $movement = $this->movement($source, 'decant_waste', -$quantity, $opening->remaining_ml, $userId, $data['notes']);
            $movement->update(['total_cost_cents' => $cost]);
        } elseif ($data['type'] === 'decant_undo_open') {
            if ($opening->remaining_ml !== $opening->initial_ml || $opening->initial_cost_cents === null || $opening->lost_ml > 0
                || DB::table('decant_batches')->where('opening_id', $opening->id)->exists() || DB::table('decant_remainder_sales')->where('opening_id', $opening->id)->exists()) {
                throw new InvalidArgumentException('La botella ya fue usada; no se puede revertir su apertura.', 409);
            }
            $allocations = DB::table('inventory_lot_allocations')->where('inventory_movement_id', $opening->movement_id)->get();
            foreach ($allocations as $allocation) {
                $lot = DB::table('inventory_lots')->where('id', $allocation->inventory_lot_id)->lockForUpdate()->first();
                DB::table('inventory_lots')->where('id', $lot->id)->update([
                    'remaining_quantity' => $lot->remaining_quantity + $allocation->quantity,
                    'remaining_cost_cents' => $lot->remaining_cost_cents === null ? null : $lot->remaining_cost_cents + $allocation->cost_cents,
                ]);
            }
            DB::table('decant_openings')->where('id', $opening->id)->update(['status' => 'reversed', 'remaining_ml' => 0,
                'remaining_cost_cents' => 0, 'updated_at' => now()]);
            $inventory->stock_quantity++;
            $inventory->save();
            $this->movement($source, 'decant_opening_reversal', 1, $inventory->stock_quantity - 1, $userId, $data['notes'] ?? 'Reversión de apertura no utilizada');
        } else {
            throw new InvalidArgumentException('Operación de Decants no válida.', 422);
        }
        $this->sync($source);

        return ['message' => 'Movimiento registrado con trazabilidad.'];
    }

    private function opening(Shop $shop, string $id): object
    {
        $opening = DB::table('decant_openings')->where('public_id', $id)->whereIn('product_id', $shop->products()->select('id'))->lockForUpdate()->first();
        abort_unless($opening, 404);

        return $opening;
    }

    private function checkOpening(object $opening, array $data): void
    {
        if ($opening->status !== 'open' || $opening->remaining_ml !== (int) $data['expected_ml']) {
            throw new InvalidArgumentException('La botella cambió. Actualiza antes de confirmar.', 409);
        }
    }

    private function portion(?int $cost, int $quantity, int $remaining): ?int
    {
        return $cost === null ? null : ($quantity === $remaining ? $cost : intdiv($cost * $quantity, $remaining));
    }

    private function movement(Product $product, string $type, int $quantity, int $before, ?int $userId, ?string $notes = null): InventoryMovement
    {
        return InventoryMovement::create(['product_id' => $product->id, 'type' => $type, 'quantity' => $quantity,
            'stock_before' => $before, 'stock_after' => $before + $quantity, 'user_id' => $userId, 'notes' => $notes, 'created_at' => now()]);
    }

    private function prepare(Product $product, object $opening, int $quantity, int $priceCents, ?int $userId): object
    {
        $ml = $quantity * (int) $product->volume_ml;
        if ($quantity < 1 || $ml > $opening->remaining_ml) {
            throw new InvalidArgumentException('No hay perfume suficiente para esta preparación.', 409);
        }
        $vial = DB::table('decant_vials')->where('shop_id', $product->shop_id)->where('volume_ml', $product->volume_ml)->where('active', true)->lockForUpdate()->first();
        if (! $vial) {
            throw new InvalidArgumentException('Recibe primero envases vacíos de este tamaño.', 409);
        }
        $lots = DB::table('decant_vial_lots')->where('vial_id', $vial->id)->where('remaining_quantity', '>', 0)->orderBy('received_at')->orderBy('id')->lockForUpdate()->get();
        if ($lots->sum('remaining_quantity') < $quantity) {
            throw new InvalidArgumentException('No hay suficientes envases vacíos.', 409);
        }
        $perfumeCost = $this->portion($opening->remaining_cost_cents, $ml, $opening->remaining_ml);
        $movement = $this->movement($product, 'decant_preparation', $quantity, $this->prepared($product), $userId, 'Preparación física desde '.$opening->public_id);
        $batchId = DB::table('decant_batches')->insertGetId(['public_id' => (string) Str::ulid(), 'product_id' => $product->id,
            'opening_id' => $opening->id, 'movement_id' => $movement->id, 'quantity' => $quantity, 'remaining_quantity' => $quantity,
            'cost_cents' => null, 'remaining_cost_cents' => null, 'price_cents' => $priceCents, 'created_at' => now(), 'updated_at' => now()]);
        $left = $quantity;
        $vialCost = 0;
        foreach ($lots as $lot) {
            if ($left === 0) break;
            $used = min($left, $lot->remaining_quantity);
            $cost = $this->portion($lot->remaining_cost_cents, $used, $lot->remaining_quantity);
            DB::table('decant_vial_allocations')->insert(['lot_id' => $lot->id, 'batch_id' => $batchId, 'quantity' => $used, 'cost_cents' => $cost]);
            DB::table('decant_vial_lots')->where('id', $lot->id)->update(['remaining_quantity' => $lot->remaining_quantity - $used,
                'remaining_cost_cents' => $lot->remaining_cost_cents - $cost, 'updated_at' => now()]);
            $vialCost += $cost;
            $left -= $used;
        }
        $totalCost = $perfumeCost === null ? null : $perfumeCost + $vialCost;
        DB::table('decant_batches')->where('id', $batchId)->update(['cost_cents' => $totalCost, 'remaining_cost_cents' => $totalCost]);
        $movement->update(['total_cost_cents' => $totalCost, 'unit_cost' => Money::perUnitDecimal($totalCost, $quantity)]);
        DB::table('decant_openings')->where('id', $opening->id)->update(['remaining_ml' => $opening->remaining_ml - $ml,
            'remaining_cost_cents' => $perfumeCost === null ? null : $opening->remaining_cost_cents - $perfumeCost, 'updated_at' => now()]);
        $source = $product->sourceProduct;
        $inventory = $source->inventory()->lockForUpdate()->firstOrFail();
        $inventory->available_ml -= $ml;
        $inventory->save();

        return DB::table('decant_batches')->where('id', $batchId)->first();
    }

    public function sale(Product $product, int $quantity, ?int $userId, string|int|float|null $price, ?string $notes): InventoryMovement
    {
        if (! $this->offered($product)) throw new InvalidArgumentException('Esta presentación está oculta. Actualiza el catálogo antes de venderla.', 409);
        if ($quantity < 1 || $this->sellable($product) < $quantity) {
            throw new InvalidArgumentException('No hay preparados ni insumos suficientes para vender este decant.', 409);
        }
        $before = $this->sellable($product);
        $needed = max(0, $quantity - $this->prepared($product));
        if ($needed > 0) {
            while ($needed > 0) {
                $opening = DB::table('decant_openings')->where('product_id', $product->inventory_source_product_id)
                    ->where('status', 'open')->where('remaining_ml', '>=', $product->volume_ml)->orderBy('id')->lockForUpdate()->first();
                if (! $opening) {
                    $source = $product->sourceProduct;
                    app(InventoryService::class)->openBottle($source, 1, 'Apertura para venta a pedido', $userId, true);
                    $opening = DB::table('decant_openings')->where('product_id', $source->id)->orderByDesc('id')->first();
                }
                $used = min($needed, intdiv($opening->remaining_ml, $product->volume_ml));
                if ($used > 0) $this->prepare($product, $opening, $used, Money::toCents($price ?? $product->currentPriceDecimal()), $userId);
                $needed -= $used;
            }
            if ($needed > 0) throw new InvalidArgumentException('Los ml están repartidos entre botellas; prepara un lote compatible.', 409);
        }
        $movement = $this->movement($product, 'sale', -$quantity, $before, $userId, $notes ?: 'Venta de decants preparados');
        $batches = DB::table('decant_batches')->where('product_id', $product->id)->where('remaining_quantity', '>', 0)->orderBy('id')->lockForUpdate()->get();
        $left = $quantity;
        $known = true;
        $total = 0;
        foreach ($batches as $batch) {
            if ($left === 0) break;
            $used = min($left, $batch->remaining_quantity);
            $cost = $this->portion($batch->remaining_cost_cents, $used, $batch->remaining_quantity);
            DB::table('decant_batch_sales')->insert(['batch_id' => $batch->id, 'movement_id' => $movement->id, 'quantity' => $used, 'cost_cents' => $cost]);
            DB::table('decant_batches')->where('id', $batch->id)->update(['remaining_quantity' => $batch->remaining_quantity - $used,
                'remaining_cost_cents' => $cost === null ? null : $batch->remaining_cost_cents - $cost, 'updated_at' => now()]);
            $known = $known && $cost !== null;
            $total += $cost ?? 0;
            $left -= $used;
        }
        $movement->update(['unit_price' => $price ?? $product->currentPriceDecimal(), 'total_cost_cents' => $known ? $total : null,
            'unit_cost' => Money::perUnitDecimal($known ? $total : null, $quantity)]);
        $inventory = $product->inventory()->lockForUpdate()->firstOrFail();
        $inventory->sold_quantity += $quantity;
        $inventory->save();
        $this->sync($product->sourceProduct);

        return $movement;
    }

    public function returned(Product $product, int $quantity, ?int $cost, ?int $userId, ?string $notes): InventoryMovement
    {
        $movement = $this->movement($product, 'return', $quantity, $this->prepared($product), $userId, $notes ?: 'Reintegro de frascos llenos, no de perfume suelto');
        $movement->update(['total_cost_cents' => $cost, 'unit_cost' => Money::perUnitDecimal($cost, $quantity)]);
        DB::table('decant_batches')->insert(['public_id' => (string) Str::ulid(), 'product_id' => $product->id, 'opening_id' => null,
            'movement_id' => $movement->id, 'quantity' => $quantity, 'remaining_quantity' => $quantity, 'cost_cents' => $cost,
            'remaining_cost_cents' => $cost, 'price_cents' => Money::toCents($product->currentPriceDecimal()), 'created_at' => now(), 'updated_at' => now()]);
        $inventory = $product->inventory()->lockForUpdate()->firstOrFail();
        $inventory->sold_quantity = max(0, $inventory->sold_quantity - $quantity);
        $inventory->save();
        $this->sync($product->sourceProduct);

        return $movement;
    }

    public function returnRemainder(Product $source, object $sale, ?int $cost, int $userId): void
    {
        $opening = DB::table('decant_openings')->where('id', $sale->opening_id)->lockForUpdate()->first();
        DB::table('decant_openings')->where('id', $opening->id)->update(['remaining_ml' => $opening->remaining_ml + $sale->volume_ml,
            'remaining_cost_cents' => $cost === null || $opening->remaining_cost_cents === null ? null : $opening->remaining_cost_cents + $cost,
            'updated_at' => now()]);
        $inventory = $source->inventory()->lockForUpdate()->firstOrFail();
        $inventory->available_ml += $sale->volume_ml;
        $inventory->sold_quantity = max(0, $inventory->sold_quantity - 1);
        $inventory->save();
        $movement = $this->movement($source, 'return', 1, 0, $userId, 'Devolución de resto a su apertura original');
        $movement->update(['total_cost_cents' => $cost]);
        $this->sync($source);
    }

    public function sync(Product $source): void
    {
        $openings = DB::table('decant_openings')->where('product_id', $source->id)->where('status', 'open')->get();
        $legacy = $this->untrackedMl($source);
        $knownOpened = $openings->where('remaining_ml', '>', 0)->count();
        $source->inventory()->update(['reserved_decant_ml' => $openings->sum('remaining_ml') + $legacy,
            'opened_bottles' => $legacy > 0 ? max($knownOpened, (int) $source->inventory()->value('opened_bottles')) : $knownOpened]);
        $source->decantProducts()->with('sourceProduct')->get()->each(function (Product $product): void {
            $stock = $this->sellable($product);
            $product->inventory()->update(['stock_quantity' => $stock]);
            $product->forceFill(['availability_status' => $stock > 0 ? \App\Enums\ProductAvailabilityStatus::Available : \App\Enums\ProductAvailabilityStatus::OutOfStock])->saveQuietly();
        });
    }
}
