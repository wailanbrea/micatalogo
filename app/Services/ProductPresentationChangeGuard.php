<?php

namespace App\Services;

use App\Models\InventoryLot;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

/** Prevents rewriting a product's unit semantics after it has entered commerce. */
class ProductPresentationChangeGuard
{
    /**
     * @param array{sale_unit?: string|null, volume_ml?: int|null, inventory_source_product_id?: int|null} $presentation
     */
    public function assertMayChange(
        Shop $shop,
        Product $product,
        ?ProductInventory $inventory,
        array $presentation,
        ?string $requestedUnitUuid,
        bool $isCombo,
    ): void {
        $currentSaleUnit = $product->sale_unit ?: 'unit';
        $nextSaleUnit = $presentation['sale_unit'] ?? $currentSaleUnit;
        $nextVolume = isset($presentation['volume_ml']) ? (int) $presentation['volume_ml'] : null;
        $nextSource = $presentation['inventory_source_product_id'] ?? null;

        $unitKey = match ($currentSaleUnit) {
            'bottle' => 'bottle',
            'ml' => 'ml',
            'decant' => 'decant',
            'service' => 'service',
            default => 'unit',
        };
        $sync = app(ShopRecordSyncService::class);
        $currentUnitUuid = $sync->productUnits($shop)[$product->public_id]
            ?? $sync->localId($shop, 'unit', $unitKey);

        $presentationChanged = $nextSaleUnit !== $currentSaleUnit
            || $nextVolume !== ($product->volume_ml === null ? null : (int) $product->volume_ml)
            || ($nextSource === null ? null : (int) $nextSource) !== ($product->inventory_source_product_id === null ? null : (int) $product->inventory_source_product_id)
            || $isCombo !== $product->isCombo()
            || ($requestedUnitUuid !== null && $requestedUnitUuid !== $currentUnitUuid);

        if (! $presentationChanged || ! $this->hasCommercialHistory($product, $inventory)) {
            return;
        }

        throw ValidationException::withMessages([
            'sale_unit' => 'No puedes cambiar la unidad o presentación de un producto con inventario o historial comercial. Conservamos su FIFO, ventas y devoluciones; crea una presentación nueva vinculada o usa un proceso de conversión seguro.',
        ]);
    }

    private function hasCommercialHistory(Product $product, ?ProductInventory $inventory): bool
    {
        if ($inventory && (
            (int) $inventory->stock_quantity > 0
            || (int) $inventory->sold_quantity > 0
            || (int) ($inventory->available_ml ?? 0) > 0
            || (int) ($inventory->reserved_decant_ml ?? 0) > 0
            || (int) ($inventory->opened_bottles ?? 0) > 0
        )) {
            return true;
        }

        if ($product->inventoryMovements()->exists()
            || InventoryLot::query()->where('product_id', $product->id)->exists()
            || InvoiceItem::query()->where('product_id', $product->id)->exists()
            || $product->decantProducts()->withTrashed()->exists()
            || DB::table('product_combo_items')
                ->where('product_id', $product->id)
                ->orWhere('component_product_id', $product->id)
                ->exists()
            || app(ShopRouteSyncService::class)->reserved($product) > 0) {
            return true;
        }

        if (app(DecantInventoryService::class)->available()) {
            return DB::table('decant_batches')->where('product_id', $product->id)->exists()
                || DB::table('decant_openings')->where('product_id', $product->id)->exists();
        }

        return false;
    }
}
