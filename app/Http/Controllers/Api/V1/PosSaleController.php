<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PosSaleUpload;
use App\Models\Product;
use App\Models\Shop;
use App\Services\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PosSaleController extends Controller
{
    public function store(Request $request, Shop $shop, InventoryService $inventoryService): JsonResponse
    {
        abort_unless($request->user()->ownsShop($shop), 404);

        $validated = $request->validate([
            'client_sale_uuid' => ['required', 'uuid'],
            'payment_status' => ['required', 'in:paid,partial,pending'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'ulid'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.unit_price' => ['required', 'decimal:0,2', 'min:0'],
        ]);
        $payloadHash = hash('sha256', json_encode($validated, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        try {
            return DB::transaction(function () use ($shop, $validated, $payloadHash, $inventoryService, $request): JsonResponse {
                $existing = PosSaleUpload::query()
                    ->with('invoice')
                    ->where('shop_id', $shop->id)
                    ->where('client_sale_uuid', $validated['client_sale_uuid'])
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $this->idempotentResponse($existing, $payloadHash);
                }

                $productIds = array_unique(array_column($validated['items'], 'product_id'));
                $products = Product::query()
                    ->where('shop_id', $shop->id)
                    ->whereIn('public_id', $productIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('public_id');

                if ($products->count() !== count($productIds)) {
                    return response()->json([
                        'message' => 'One or more products do not belong to this shop.',
                    ], 422);
                }

                foreach ($validated['items'] as $item) {
                    if ($this->toCents($item['unit_price']) !== $this->toCents($products[$item['product_id']]->currentPrice())) {
                        return response()->json([
                            'message' => 'The submitted price no longer matches the catalog.',
                            'reason' => 'price_conflict',
                        ], 409);
                    }
                }

                $upload = PosSaleUpload::create([
                    'shop_id' => $shop->id,
                    'client_sale_uuid' => $validated['client_sale_uuid'],
                    'payload_sha256' => $payloadHash,
                ]);
                $sales = array_map(fn (array $item): array => [
                    'product' => $products[$item['product_id']],
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => $products[$item['product_id']]->currentPrice(),
                ], $validated['items']);
                $movements = $inventoryService->recordCartSales(
                    $sales,
                    $request->user()->id,
                    'pos',
                    $validated['payment_status'],
                );
                $invoice = Invoice::query()->findOrFail($movements[0]->invoice_id);

                $upload->invoice()->associate($invoice);
                $upload->save();

                return response()->json($this->successPayload($upload, $invoice), 201);
            });
        } catch (InvalidArgumentException $exception) {
            if ($exception->getCode() === 409) {
                return response()->json([
                    'message' => 'Insufficient remote stock.',
                    'reason' => 'stock_conflict',
                ], 409);
            }

            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (QueryException $exception) {
            $upload = PosSaleUpload::query()
                ->with('invoice')
                ->where('shop_id', $shop->id)
                ->where('client_sale_uuid', $validated['client_sale_uuid'])
                ->first();

            if (! $upload) {
                throw $exception;
            }

            return $this->idempotentResponse($upload, $payloadHash);
        }
    }

    private function idempotentResponse(PosSaleUpload $upload, string $payloadHash): JsonResponse
    {
        if (! hash_equals($upload->payload_sha256, $payloadHash)) {
            return response()->json([
                'message' => 'This client sale UUID has already been used with a different payload.',
                'reason' => 'idempotency_conflict',
            ], 409);
        }

        return response()->json($this->successPayload($upload, $upload->invoice), 201);
    }

    /**
     * @return array{client_sale_uuid: string, invoice_number: string, status: string, total: string}
     */
    private function successPayload(PosSaleUpload $upload, Invoice $invoice): array
    {
        return [
            'client_sale_uuid' => $upload->client_sale_uuid,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'total' => $invoice->total,
        ];
    }

    private function toCents(string|int|float $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
