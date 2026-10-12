<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    /**
     * Read model for invoices that can be refunded from the mobile app.
     * This includes invoices created by the web/POS/quote flows, not only
     * invoices that were uploaded from the Android outbox.
     */
    public function eligible(Request $request, Shop $shop): JsonResponse
    {
        abort_unless($request->user()->canSellAtShop($shop), 404);

        $invoices = $shop->invoices()
            ->with(['customer', 'items.product'])
            ->where('status', 'paid')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('customer_account_entries')
                    ->whereColumn('customer_account_entries.invoice_id', 'invoices.id');
            })
            ->latest('issued_at')
            ->limit(100)
            ->get()
            ->map(function (Invoice $invoice): ?array {
                $charged = app(\App\Services\InvoiceRefundAmounts::class)->chargedLines($invoice);
                $returned = DB::table('invoice_return_items')
                    ->whereIn('invoice_item_id', $invoice->items->pluck('id'))
                    ->selectRaw('invoice_item_id, COALESCE(SUM(quantity), 0) AS quantity')
                    ->groupBy('invoice_item_id')
                    ->pluck('quantity', 'invoice_item_id');

                $items = $invoice->items->map(function ($item) use ($returned, $charged): ?array {
                    $returnedQuantity = (int) ($returned->get($item->id) ?? 0);
                    $remaining = (int) $item->quantity - $returnedQuantity;
                    if ($remaining <= 0) {
                        return null;
                    }

                    return [
                        'id' => (int) $item->id,
                        'product_id' => (string) $item->product?->public_id,
                        'product_name' => (string) $item->product_name,
                        'quantity' => (int) $item->quantity,
                        'refunded_quantity' => $returnedQuantity,
                        'remaining_quantity' => $remaining,
                        'unit_price' => (string) $item->unit_price,
                        'line_total' => (string) $item->line_total,
                        'charged_line_cents' => $charged[$item->id],
                        'remaining_refund_cents' => $charged[$item->id] - intdiv($charged[$item->id] * $returnedQuantity, (int) $item->quantity),
                        'sale_unit' => $item->sale_unit,
                        'volume_ml' => $item->volume_ml,
                    ];
                })->filter()->values();

                if ($items->isEmpty()) {
                    return null;
                }

                return [
                    'id' => (int) $invoice->id,
                    'invoice_number' => (string) $invoice->invoice_number,
                    'total' => (string) $invoice->total,
                    'issued_at' => $invoice->issued_at?->toISOString(),
                    'customer_name' => $invoice->customer?->name,
                    'items' => $items->all(),
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'invoices' => $invoices,
            'generated_at' => now()->toISOString(),
        ]);
    }
}
