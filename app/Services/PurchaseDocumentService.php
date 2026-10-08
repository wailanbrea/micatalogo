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
     * @param  array{type: string, document_number: string, supplier_id?: string|null, invoice_date?: string|null, due_at?: string|null, amount?: string|float|int|null, currency?: string|null, exchange_rate?: string|float|int|null, carrier?: string|null, tracking_number?: string|null, expected_at?: string|null, shipping_pounds?: string|float|int|null, freight_amount?: string|float|int|null, customs_amount?: string|float|int|null, payment_status?: string|null, parent_document_id?: string|null, mode?: string|null, items?: array<int, array{product_id: string, quantity: int, unit_cost: string|float|int}>, notes?: string|null}  $data
     */
    public function create(Shop $shop, User $user, array $data): PurchaseDocument
    {
        $items = collect($data['items'] ?? []);
        if ($items->isEmpty() && ! in_array(($data['type'] ?? null), ['load', 'supplier_debt'], true)) {
            throw new InvalidArgumentException('Agrega al menos un producto a la compra.');
        }

        if (($data['type'] ?? null) === 'supplier_debt') {
            if (empty($data['supplier_id']) || ! isset($data['amount']) || Money::toCents($data['amount']) <= 0) {
                throw new InvalidArgumentException('Una deuda necesita suplidor y monto mayor que cero.');
            }
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

        $parent = ! empty($data['parent_document_id'])
            ? $shop->purchaseDocuments()->where('public_id', $data['parent_document_id'])->first()
            : null;
        if (! empty($data['parent_document_id']) && (! $parent || $parent->type !== 'load')) {
            throw new InvalidArgumentException('La carga seleccionada no pertenece a esta tienda.');
        }

        $lines = $items->map(function (array $item) use ($products): array {
            $product = $products->get($item['product_id']);
            $quantity = (int) $item['quantity'];
            $unitCostCents = Money::toCents($item['unit_cost']);

            return [
                'product' => $product,
                'quantity' => $quantity,
                // Keep the persisted decimal aligned with the integer-cent
                // source used for totals, lots and inventory movements.
                'unit_cost' => Money::toDecimal($unitCostCents),
                'unit_cost_cents' => $unitCostCents,
                'line_total' => Money::toDecimal($unitCostCents * $quantity),
            ];
        })->values();
        $subtotalCents = $lines->sum(fn (array $line): int => $line['unit_cost_cents'] * $line['quantity']);
        $mode = $data['mode'] ?? 'received';

        return DB::transaction(function () use ($shop, $user, $data, $supplier, $parent, $lines, $subtotalCents, $mode): PurchaseDocument {
            $document = PurchaseDocument::create([
                'shop_id' => $shop->id,
                'supplier_id' => $supplier?->id,
                'user_id' => $user->id,
                'document_number' => $data['document_number'],
                'invoice_date' => $data['invoice_date'] ?? null,
                'due_at' => $data['due_at'] ?? null,
                'type' => $data['type'] === 'supplier_debt' ? 'purchase_invoice' : $data['type'],
                'status' => $data['type'] === 'supplier_debt' ? 'debt' : $mode,
                'currency' => strtoupper($data['currency'] ?? 'DOP'),
                'exchange_rate' => $data['exchange_rate'] ?? null,
                'carrier' => $data['carrier'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
                'expected_at' => $data['expected_at'] ?? null,
                'shipping_pounds' => $data['shipping_pounds'] ?? null,
                'freight_amount' => $data['freight_amount'] ?? 0,
                'customs_amount' => $data['customs_amount'] ?? 0,
                'payment_status' => $data['payment_status'] ?? 'pending',
                'parent_document_id' => $parent?->id,
                'subtotal' => $data['type'] === 'supplier_debt' ? Money::toDecimal(Money::toCents($data['amount'])) : Money::toDecimal($subtotalCents),
                'total' => $data['type'] === 'supplier_debt' ? Money::toDecimal(Money::toCents($data['amount'])) : Money::toDecimal($subtotalCents),
                'received_at' => $data['type'] === 'supplier_debt' ? null : ($mode === 'received' ? now() : null),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $movement = $mode === 'received'
                    ? $this->inventory->recordRestock($line['product'], $line['quantity'], $data['notes'] ?? null, $user->id, $line['unit_cost'])
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
        if ($document->type === 'load') {
            throw new InvalidArgumentException('Una carga se recibe a través de sus contenedores.');
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
            if ($locked->type === 'load') {
                throw new InvalidArgumentException('Una carga se recibe a través de sus contenedores.');
            }

            $locked->load('items.product');
            foreach ($locked->items as $item) {
                if ($item->inventory_movement_id) {
                    continue;
                }
                $movement = $this->inventory->recordRestock($item->product, $item->quantity, $locked->notes, $user->id, $item->unit_cost);
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
            'type' => $document->status === 'debt' ? 'supplier_debt' : $document->type,
            'status' => $document->status,
            'currency' => $document->currency,
            'exchange_rate' => $document->exchange_rate,
            'carrier' => $document->carrier,
            'tracking_number' => $document->tracking_number,
            'expected_at' => $document->expected_at?->toDateString(),
            'invoice_date' => $document->invoice_date?->toDateString(),
            'due_at' => $document->due_at?->toDateString(),
            'shipping_pounds' => $document->shipping_pounds,
            'freight_amount' => $document->freight_amount,
            'customs_amount' => $document->customs_amount,
            'payment_status' => $document->payment_status,
            'parent_document_id' => $document->parentDocument?->public_id,
            'subtotal' => $document->subtotal,
            'total' => $document->total,
            'notes' => $document->notes,
            'received_at' => $document->received_at?->toIso8601String(),
            'supplier' => $document->supplier ? [
                'id' => $document->supplier->public_id,
                'name' => $document->supplier->name,
                'invoice_currency' => $document->supplier->invoice_currency,
            ] : null,
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
                'invoice_currency' => $supplier->invoice_currency,
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
