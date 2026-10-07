<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\Shop;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Single write path for purchase documents used by web and Android.
 * It keeps draft creation side-effect free and makes receiving idempotent.
 */
class PurchaseDocumentService
{
    public function __construct(private InventoryService $inventory) {}

    /**
     * @param  array{type: string, document_number: string, supplier_id?: string|null, currency?: string|null, mode?: string|null, items: array<int, array{product_id: string, quantity: int, unit_cost: string|float|int}>, notes?: string|null}  $data
     */
    public function create(Shop $shop, User $user, array $data): PurchaseDocument
    {
        $items = collect($data['items'] ?? []);
        if ($items->isEmpty()) {
            throw new InvalidArgumentException('Agrega al menos un producto a la compra.');
        }

        $products = Product::query()
            ->where('shop_id', $shop->id)
            ->whereIn('public_id', $items->pluck('product_id')->unique())
            ->get()
            ->keyBy('public_id');
        if ($products->count() !== $items->pluck('product_id')->unique()->count()) {
            throw new InvalidArgumentException('Uno o más productos no pertenecen a esta tienda.');
        }

        $supplier = ! empty($data['supplier_id'])
            ? $shop->suppliers()->where('public_id', $data['supplier_id'])->first()
            : null;
        if (! empty($data['supplier_id']) && ! $supplier) {
            throw new InvalidArgumentException('El suplidor no pertenece a esta tienda.');
        }

        $lines = $items->map(function (array $item) use ($products): array {
            $product = $products->get($item['product_id']);
            $quantity = (int) $item['quantity'];
            $unitCostCents = Money::toCents($item['unit_cost']);

            return [
                'product' => $product,
                'quantity' => $quantity,
                'unit_cost' => $item['unit_cost'],
                'unit_cost_cents' => $unitCostCents,
                'line_total' => Money::toDecimal($unitCostCents * $quantity),
            ];
        })->values();
        $subtotalCents = $lines->sum(fn (array $line): int => $line['unit_cost_cents'] * $line['quantity']);
        $mode = $data['mode'] ?? 'received';

        return DB::transaction(function () use ($shop, $user, $data, $supplier, $lines, $subtotalCents, $mode): PurchaseDocument {
            $document = PurchaseDocument::create([
                'shop_id' => $shop->id,
                'supplier_id' => $supplier?->id,
                'user_id' => $user->id,
                'document_number' => $data['document_number'],
                'type' => $data['type'],
                'status' => $mode,
                'currency' => strtoupper($data['currency'] ?? 'DOP'),
                'subtotal' => Money::toDecimal($subtotalCents),
                'total' => Money::toDecimal($subtotalCents),
                'received_at' => $mode === 'received' ? now() : null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $movement = $mode === 'received'
                    ? $this->inventory->recordRestock($line['product'], $line['quantity'], $data['notes'] ?? null, $user->id, (float) $line['unit_cost'])
                    : null;
                $document->items()->create([
                    'product_id' => $line['product']->id,
                    'inventory_movement_id' => $movement?->id,
                    'product_name' => $line['product']->name,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'line_total' => $line['line_total'],
                ]);
            }

            return $document->load(['supplier', 'items.product']);
        });
    }

    public function receive(Shop $shop, PurchaseDocument $document, User $user): PurchaseDocument
    {
        if ($document->shop_id !== $shop->id) {
            abort(404);
        }
        if ($document->status !== 'draft') {
            throw new InvalidArgumentException('Esta compra ya fue recibida y no puede duplicar sus lotes.');
        }

        return DB::transaction(function () use ($shop, $document, $user): PurchaseDocument {
            $locked = PurchaseDocument::query()
                ->whereKey($document->id)
                ->where('shop_id', $shop->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($locked->status !== 'draft') {
                throw new InvalidArgumentException('Esta compra ya fue recibida y no puede duplicar sus lotes.');
            }

            $locked->load('items.product');
            foreach ($locked->items as $item) {
                if ($item->inventory_movement_id) {
                    continue;
                }
                $movement = $this->inventory->recordRestock($item->product, $item->quantity, $locked->notes, $user->id, (float) $item->unit_cost);
                $item->update(['inventory_movement_id' => $movement->id]);
            }
            $locked->update(['status' => 'received', 'received_at' => now()]);

            return $locked->fresh(['supplier', 'items.product']);
        });
    }

    /** @return array<string, mixed> */
    public function payload(PurchaseDocument $document): array
    {
        return [
            'id' => $document->public_id,
            'document_number' => $document->document_number,
            'type' => $document->type,
            'status' => $document->status,
            'currency' => $document->currency,
            'subtotal' => $document->subtotal,
            'total' => $document->total,
            'notes' => $document->notes,
            'received_at' => $document->received_at?->toIso8601String(),
            'supplier' => $document->supplier ? ['id' => $document->supplier->public_id, 'name' => $document->supplier->name] : null,
            'items' => $document->items->map(fn ($item): array => [
                'id' => $item->id,
                'product_id' => $item->product?->public_id,
                'product_name' => $item->product_name,
                'quantity' => (int) $item->quantity,
                'unit_cost' => $item->unit_cost,
                'line_total' => $item->line_total,
                'received' => $item->inventory_movement_id !== null,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function catalogPayload(Shop $shop): array
    {
        return [
            'suppliers' => $shop->suppliers()->orderBy('name')->get()->map(fn ($supplier): array => [
                'id' => $supplier->public_id,
                'name' => $supplier->name,
            ])->values()->all(),
            'products' => $shop->products()
                ->whereHas('inventory', fn ($query) => $query->where('track_inventory', true))
                ->with('inventory')
                ->orderBy('name')
                ->limit(1000)
                ->get()
                ->map(fn (Product $product): array => [
                    'id' => $product->public_id,
                    'name' => $product->name,
                    'code' => $product->product_code ?: ($product->barcode ?: ''),
                    'cost' => number_format((float) ($product->inventory?->cost_price ?? 0), 2, '.', ''),
                ])->values()->all(),
        ];
    }
}
