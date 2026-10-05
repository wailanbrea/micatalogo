<?php

namespace App\Services;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductImageProcessingStatus;
use App\Enums\ProductModerationStatus;
use App\Models\CustomerAccountEntry;
use App\Models\Invoice;
use App\Models\MobileOperation;
use App\Models\PosSaleUpload;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MobileOperationService
{
    public function apply(Shop $shop, User $user, array $data): array
    {
        abort_unless($user->canSellAtShop($shop), 404);
        if ($data['type'] !== 'return') {
            Gate::forUser($user)->authorize('update', $shop);
        } else {
            abort_unless(in_array('returns', app(SellerMenuService::class)->forUser($shop, $user), true), 403);
        }
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($shop, $user, $data, $hash) {
            // Serializes deduplication, quota checks and document writes in one tenant.
            $shop = Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            $old = MobileOperation::where('shop_id', $shop->id)->where('client_operation_uuid', $data['client_operation_uuid'])->first();
            if ($old) {
                if (! hash_equals($old->payload_sha256, $hash)) {
                    throw new InvalidArgumentException('El identificador ya fue utilizado con otro contenido.', 409);
                }

                return $old->result;
            }
            $operation = MobileOperation::create(['shop_id' => $shop->id, 'client_operation_uuid' => $data['client_operation_uuid'],
                'type' => $data['type'], 'payload_sha256' => $hash, 'result' => []]);
            $result = $data['type'] === 'return' ? $this->refund($shop, $user, $data, $operation)
                : $this->productOperation($shop, $user, $data);
            $result['client_operation_uuid'] = $data['client_operation_uuid'];
            $operation->update(['result' => $result]);

            return $result;
        });
    }

    private function productOperation(Shop $shop, User $user, array $data): array
    {
        $product = $shop->products()->withTrashed()->where('public_id', $data['product_id'])->lockForUpdate()->first();
        if ($data['type'] === 'product_upsert') {
            if (! $product) {
                abort_if(Product::withTrashed()->where('public_id', $data['product_id'])->exists(), 409);
                app(PlanLimitsService::class)->assertCanAddProducts($shop, 1);
                $name = $data['name'] ?? throw ValidationException::withMessages(['name' => 'Indica el nombre.']);
                $price = $data['price'] ?? throw ValidationException::withMessages(['price' => 'Indica el precio.']);
                $product = $shop->products()->make(['name' => $name, 'price' => $price,
                    'slug' => (Str::slug($name) ?: 'producto').'-'.strtolower($data['product_id']),
                    'currency' => 'DOP', 'sale_unit' => 'unit', 'moderation_status' => ProductModerationStatus::Active,
                    'availability_status' => ProductAvailabilityStatus::OutOfStock, 'published_at' => now()]);
                $product->forceFill(['public_id' => $data['product_id']])->save();
                $product->inventory()->create(['track_inventory' => true, 'stock_quantity' => 0,
                    'cost_price' => $data['cost_price'] ?? null, 'sold_quantity' => 0, 'low_stock_threshold' => $data['minimum_stock'] ?? 3]);
            } else {
                abort_if($product->trashed(), 409, 'El producto está en la papelera.');
                if (isset($data['expected_price']) && (int) round($product->currentPrice() * 100) !== (int) round((float) $data['expected_price'] * 100)) {
                    throw new InvalidArgumentException('El precio remoto cambió; revisa el conflicto antes de editar.', 409);
                }
            }
            foreach (['name' => 'name', 'internal_code' => 'product_code', 'barcode' => 'barcode', 'description' => 'description'] as $input => $column) {
                if (array_key_exists($input, $data)) {
                    $product->$column = $data[$input];
                }
            }
            if (array_key_exists('price', $data)) {
                $product->{$product->isOnSale() ? 'sale_price' : 'price'} = $data['price'];
            }
            if (! empty($data['category_name'])) {
                $slug = Str::slug($data['category_name']) ?: 'categoria';
                $category = $shop->categories()->firstOrCreate(['slug' => $slug], ['name' => $data['category_name'], 'status' => 'active']);
                $product->shop_category_id = $category->id;
            }
            $product->save();
            if (isset($data['minimum_stock'])) {
                $product->inventory?->update(['low_stock_threshold' => $data['minimum_stock']]);
            }
            if (isset($data['image_base64'])) {
                $this->productImage($shop, $product, $data);
            }
        } else {
            abort_unless($product && ! $product->trashed(), 404);
            if ($data['type'] === 'product_archive') {
                $product->delete(); // Always reversible; ledger and images survive.
            } elseif ($data['type'] === 'restock') {
                app(InventoryService::class)->recordRestock($product, $data['quantity'], $data['notes'] ?? null, $user->id,
                    isset($data['unit_cost']) ? (float) $data['unit_cost'] : null);
            } elseif ($data['type'] === 'adjustment') {
                $inventory = $product->inventory()->lockForUpdate()->firstOrFail();
                if ($inventory->stock_quantity !== $data['expected_stock']) {
                    throw new InvalidArgumentException('El stock cambió; vuelve a contar antes de ajustar.', 409);
                }
                if (isset($data['expected_available_ml']) && $inventory->available_ml !== $data['expected_available_ml']) {
                    throw new InvalidArgumentException('Los mililitros disponibles cambiaron; vuelve a contar antes de ajustar.', 409);
                }
                app(InventoryService::class)->adjustStock($product, $data['stock'], $data['notes'], $user->id);
            }
        }

        return ['product_id' => $product->public_id, 'price' => number_format($product->fresh()->currentPrice(), 2, '.', ''),
            'stock' => $product->inventory?->fresh()?->stock_quantity];
    }

    private function productImage(Shop $shop, Product $product, array $data): void
    {
        $bytes = base64_decode($data['image_base64'], true);
        $info = $bytes === false ? false : @getimagesizefromstring($bytes);
        if ($bytes === false || strlen($bytes) > 512 * 1024 || ! $info ||
            ! in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp', 'image/avif'], true) ||
            (int) config('catalog.uploads.max_input_pixels', 60_000_000) < $info[0] * $info[1] ||
            ! hash_equals($data['image_sha256'], hash('sha256', $bytes))) {
            throw ValidationException::withMessages(['image_base64' => 'La foto no es válida o cambió durante el envío.']);
        }
        $derivatives = app(ImageDerivativeService::class);
        try {
            $rendered = $derivatives->render($bytes);
        } catch (\Throwable $error) {
            throw ValidationException::withMessages(['image_base64' => 'No se pudo procesar la foto.']);
        }
        $existing = $product->images()->where('checksum_sha256', $rendered['checksum'])->first();
        if (! $existing) {
            abort_if($product->images()->count() >= app(PlanLimitsService::class)->imageLimit($shop), 422,
                'Tu plan alcanzó el límite de fotos. Gestiona las anteriores en el panel; no se borran automáticamente.');
            $media = app(MediaStorageService::class);
            $main = $media->buildProductObjectKey($product->public_id, 'main', $rendered['checksum']);
            $thumb = $media->buildProductObjectKey($product->public_id, 'thumb', $rendered['checksum']);
            $derivatives->store($rendered, $main, $thumb, $media);
            $existing = $product->images()->create(['object_key' => $main, 'thumbnail_object_key' => $thumb,
                'checksum_sha256' => $rendered['checksum'], 'mime_type' => 'image/webp',
                'width' => $rendered['width'], 'height' => $rendered['height'], 'size_bytes' => $rendered['size_bytes'],
                'sort_order' => 0, 'processing_status' => ProductImageProcessingStatus::Ready]);
        }
        // Promote the chosen photo without deleting or replacing an older object.
        $product->images()->whereKeyNot($existing->id)->increment('sort_order');
        $existing->update(['sort_order' => 0]);
    }

    private function refund(Shop $shop, User $user, array $data, MobileOperation $operation): array
    {
        $upload = PosSaleUpload::where('shop_id', $shop->id)->where('client_sale_uuid', $data['client_sale_uuid'])->first();
        if (! $upload?->invoice_id) {
            throw new InvalidArgumentException('Sincroniza primero la venta original.', 409);
        }
        $invoice = Invoice::whereKey($upload->invoice_id)->lockForUpdate()->firstOrFail();
        if ($invoice->status !== 'paid' || CustomerAccountEntry::where('invoice_id', $invoice->id)->exists()) {
            throw new InvalidArgumentException('Solo se devuelven ventas de contado pagadas, sin deuda.', 422);
        }
        $rows = $invoice->items()->get();
        $invoiceItems = $rows->keyBy(fn ($item) => (string) $item->product_id);
        if ($rows->count() !== $invoiceItems->count()) {
            throw new InvalidArgumentException('La venta contiene líneas repetidas; requiere conciliación antes de devolver.', 422);
        }
        // Allocate invoice-level tax deterministically in cents, conserving the
        // full amount across all lines and subsequent partial returns.
        $globalTax = (int) round((float) $invoice->tax * 100);
        $weights = $rows->mapWithKeys(fn ($item) => [$item->id => max(0, (int) round((float) $item->line_total * 100) - (int) $item->general_discount_cents)]);
        if ($weights->sum() === 0) {
            $weights = $rows->mapWithKeys(fn ($item) => [$item->id => (int) round((float) $item->line_total * 100)]);
            if ($weights->sum() === 0) {
                $weights = $rows->mapWithKeys(fn ($item) => [$item->id => (int) $item->quantity]);
            }
        }
        $weightTotal = $weights->sum();
        $taxShares = [];
        $cumulativeWeight = $allocatedTax = 0;
        foreach ($rows->sortBy('id') as $row) {
            $cumulativeWeight += $weights[$row->id];
            $nextTax = $weightTotal > 0 ? intdiv($globalTax * $cumulativeWeight, $weightTotal) : 0;
            $taxShares[$row->id] = $nextTax - $allocatedTax;
            $allocatedTax = $nextTax;
        }
        $returnId = DB::table('invoice_returns')->insertGetId(['invoice_id' => $invoice->id, 'mobile_operation_id' => $operation->id,
            'total' => 0, 'notes' => $data['notes'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
        $totalCents = 0;
        foreach ($data['items'] as $line) {
            $product = $shop->products()->withTrashed()->where('public_id', $line['product_id'])->firstOrFail();
            $item = $invoiceItems->get((string) $product->id);
            if (! $item) {
                throw new InvalidArgumentException('La línea no pertenece a la venta.', 422);
            }
            if ($line['restock'] && ($item->sale_unit !== $product->sale_unit ||
                in_array($item->sale_unit, ['bottle', 'ml', 'decant'], true) && (
                    $item->volume_ml !== $product->volume_ml ||
                    (string) $item->inventory_source_product_id !== (string) $product->inventory_source_product_id ||
                    $item->sale_unit === 'decant' && $item->inventory_source_product_id === null
                ))) {
                throw new InvalidArgumentException('La presentación o fuente cambió desde la venta; concilia el inventario antes de reintegrar.', 422);
            }
            $oldQty = (int) DB::table('invoice_return_items')->where('invoice_item_id', $item->id)->sum('quantity');
            $qty = $line['quantity'];
            if ($oldQty + $qty > $item->quantity) {
                throw new InvalidArgumentException('La devolución supera las unidades vendidas.', 422);
            }
            $refundCents = isset($line['refund_total']) ? (int) round((float) $line['refund_total'] * 100)
                : (int) round((float) $line['refund_price'] * 100) * $qty;
            $chargedCents = (int) round((float) $item->line_total * 100) - (int) $item->general_discount_cents + $taxShares[$item->id];
            $maxRefund = intdiv($chargedCents * ($oldQty + $qty), $item->quantity) - intdiv($chargedCents * $oldQty, $item->quantity);
            if ($refundCents > $maxRefund) {
                throw new InvalidArgumentException('El reembolso supera lo cobrado por esas unidades.', 422);
            }
            $cost = $item->total_cost_cents === null ? null
                : intdiv($item->total_cost_cents * ($oldQty + $qty), $item->quantity) - intdiv($item->total_cost_cents * $oldQty, $item->quantity);
            $itemTaxCents = (int) round((float) $item->tax * 100) + $taxShares[$item->id];
            $taxCents = min($refundCents, intdiv($itemTaxCents * ($oldQty + $qty), $item->quantity) - intdiv($itemTaxCents * $oldQty, $item->quantity));
            DB::table('invoice_return_items')->insert(['invoice_return_id' => $returnId, 'invoice_item_id' => $item->id,
                'quantity' => $qty, 'refund' => $refundCents / 100, 'tax_refund' => $taxCents / 100,
                'total_cost_cents' => $cost, 'restock' => $line['restock'], 'created_at' => now(), 'updated_at' => now()]);
            if ($line['restock']) {
                app(InventoryService::class)->recordReturn($product, $qty, $cost, $user->id, $data['notes'] ?? null);
            }
            $totalCents += $refundCents;
        }
        DB::table('invoice_returns')->where('id', $returnId)->update(['total' => $totalCents / 100]);

        return ['return_id' => $returnId, 'total' => number_format($totalCents / 100, 2, '.', '')];
    }
}
