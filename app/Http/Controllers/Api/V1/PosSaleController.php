<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PosSaleUpload;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CustomerAccountService;
use App\Services\InventoryService;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PosSaleController extends Controller
{
    public function store(
        Request $request,
        Shop $shop,
        InventoryService $inventoryService,
        CustomerAccountService $customerAccountService,
        PaymentService $paymentService
    ): JsonResponse {
        abort_unless($request->user()->canSellAtShop($shop), 404);

        $validated = $request->validate([
            'client_sale_uuid' => ['required', 'uuid'],
            'payment_status' => ['required', 'in:paid,partial,pending'],
            'sale_mode' => ['sometimes', 'in:retail,wholesale'],
            'customer_id' => ['nullable', 'ulid'],
            'credit_amount' => ['nullable', 'decimal:0,2', 'min:0'],
            'discount' => ['sometimes', 'decimal:0,2', 'min:0'],
            'tax' => ['sometimes', 'decimal:0,2', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'payments' => ['sometimes', 'array'],
            'payments.*.method' => ['required_with:payments', 'string'],
            'payments.*.amount' => ['required_with:payments', 'decimal:0,2', 'min:0.01'],
            'payments.*.reference' => ['nullable', 'string', 'max:120'],
            'payments.*.notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'ulid'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.unit_price' => ['required', 'decimal:0,2', 'min:0'],
            'items.*.discount' => ['sometimes', 'decimal:0,2', 'min:0'],
            'items.*.tax' => ['sometimes', 'decimal:0,2', 'min:0'],
            'items.*.expected_sale_unit' => ['nullable', 'in:unit,bottle,ml,decant,service'],
            'items.*.expected_volume_ml' => ['nullable', 'integer', 'min:1'],
            'items.*.expected_source_product_id' => ['nullable', 'ulid'],
        ]);
        $payloadHash = hash('sha256', json_encode($validated, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        try {
            return DB::transaction(function () use ($shop, $validated, $payloadHash, $inventoryService, $paymentService, $request): JsonResponse {
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
                    $product = $products[$item['product_id']];
                    // Older clients omit presentation hints; new clients capture all three
                    // together so a null source also means "no linked bottle".
                    if (isset($item['expected_sale_unit']) && (
                        $item['expected_sale_unit'] !== ($product->sale_unit ?: 'unit')
                        || ($item['expected_volume_ml'] ?? null) !== $product->volume_ml
                        || ($item['expected_source_product_id'] ?? null) !== $product->sourceProduct?->public_id
                    )) {
                        return response()->json([
                            'message' => 'Cambió la presentación o la botella de origen. Concilia la venta antes de enviarla.',
                            'reason' => 'presentation_conflict',
                        ], 409);
                    }
                    $expectedPrice = ($validated['sale_mode'] ?? 'retail') === 'wholesale'
                        ? $products[$item['product_id']]->wholesale_price
                        : $products[$item['product_id']]->currentPrice();
                    if ($expectedPrice === null || Money::toCents($item['unit_price']) !== Money::toCents($expectedPrice)) {
                        return response()->json([
                            'message' => 'The submitted price no longer matches the catalog.',
                            'reason' => 'price_conflict',
                        ], 409);
                    }
                }

                $creditAmount = $validated['credit_amount'] ?? '0.00';
                $creditCents = Money::toCents($creditAmount);
                $totalCents = collect($validated['items'])->sum(fn (array $item): int => Money::toCents($item['unit_price']) * (int) $item['quantity'] - Money::toCents($item['discount'] ?? 0) + Money::toCents($item['tax'] ?? 0))
                    - Money::toCents($validated['discount'] ?? 0) + Money::toCents($validated['tax'] ?? 0);
                if ($totalCents < 0) {
                    return response()->json(['message' => 'El descuento supera el total.'], 422);
                }
                $customer = null;
                if (! empty($validated['customer_id'])) {
                    $customer = Customer::query()
                        ->where('shop_id', $shop->id)
                        ->where('public_id', $validated['customer_id'])
                        ->lockForUpdate()
                        ->first();
                    if (! $customer) {
                        return response()->json(['message' => 'The customer does not belong to this shop.'], 422);
                    }
                }
                if ($creditCents > 0 && ! $customer) {
                    return response()->json(['message' => 'A customer is required to register credit.'], 422);
                }
                if ($creditCents > $totalCents) {
                    return response()->json(['message' => 'Credit cannot exceed the sale total.'], 422);
                }

                // Disallow orphan pending without customer or debt
                if ($validated['payment_status'] === 'pending' && ($creditCents === 0 || ! $customer)) {
                    return response()->json([
                        'message' => 'Una factura pendiente requiere un cliente y saldo a crédito real.',
                        'errors' => [
                            'customer_id' => ['Una factura pendiente requiere un cliente y saldo a crédito real.'],
                        ],
                    ], 422);
                }

                $payments = $validated['payments'] ?? [];
                if (empty($payments) && $creditCents < $totalCents) {
                    $paidCents = $totalCents - $creditCents;
                    if ($paidCents > 0) {
                        $payments[] = [
                            'method' => 'cash',
                            'amount' => Money::toDecimal($paidCents),
                        ];
                    }
                }

                $paidCents = collect($payments)->sum(fn ($p) => Money::toCents($p['amount'] ?? 0));

                if ($paidCents + $creditCents !== $totalCents) {
                    $diffFormatted = number_format(abs(($paidCents + $creditCents) - $totalCents) / 100, 2);

                    return response()->json([
                        'message' => "La suma de pagos y crédito no coincide con el total de la venta (diferencia: RD\${$diffFormatted}).",
                    ], 422);
                }

                // Derive status strictly from money, ignoring client status manipulation
                $derivedStatus = match (true) {
                    $paidCents === $totalCents && $creditCents === 0 => 'paid',
                    $paidCents === 0 && $creditCents === $totalCents => 'pending',
                    default => 'partial',
                };

                $upload = PosSaleUpload::create([
                    'shop_id' => $shop->id,
                    'client_sale_uuid' => $validated['client_sale_uuid'],
                    'payload_sha256' => $payloadHash,
                ]);
                $sales = array_map(fn (array $item): array => [
                    'product' => $products[$item['product_id']],
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => (float) ($item['discount'] ?? 0),
                    'tax' => (float) ($item['tax'] ?? 0),
                ], $validated['items']);
                $sourceBottles = collect($sales)
                    ->map(fn (array $sale): ?Product => $sale['product']->isDecant() ? $sale['product']->sourceProduct : null)
                    ->filter()
                    ->unique('id')
                    ->values();
                $recoveryBefore = $inventoryService->getCostRecoveryForBottles($sourceBottles);
                $movements = $inventoryService->recordCartSales(
                    $sales,
                    $request->user()->id,
                    'pos',
                    $derivedStatus,
                    (float) ($validated['discount'] ?? 0),
                    (float) ($validated['tax'] ?? 0),
                );
                $invoice = Invoice::query()->findOrFail($movements[0]->invoice_id);
                $invoice->sale_mode = $validated['sale_mode'] ?? 'retail';
                $invoice->save();

                if (! empty($validated['due_date'])) {
                    $invoice->due_date = $validated['due_date'];
                    $invoice->save();
                }

                if ($customer) {
                    $invoice->customer()->associate($customer)->save();
                }

                // Process payments (split or default single payment / credit) with idempotency
                $paymentService->processInvoicePayments(
                    $shop,
                    $invoice,
                    $request->user(),
                    $payments,
                    $creditAmount,
                    $customer,
                    $validated['client_sale_uuid'],
                    $payloadHash
                );

                $upload->invoice()->associate($invoice);
                $upload->save();

                $recoveryAfter = $inventoryService->getCostRecoveryForBottles($sourceBottles);

                return response()->json($this->successPayload($upload, $invoice, $recoveryAfter, $recoveryBefore), 201);
            });
        } catch (InvalidArgumentException $exception) {
            if ($exception->getCode() === 409 && str_starts_with($exception->getMessage(), 'Stock insuficiente')) {
                return response()->json([
                    'message' => 'Insufficient remote stock.',
                    'reason' => 'stock_conflict',
                ], 409);
            }

            if ($exception->getCode() === 409) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'reason' => 'credit_limit',
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

        $invoice = $upload->invoice->loadMissing('items.product.sourceProduct');
        $bottles = $invoice->items
            ->map(fn ($item) => $item->product?->isDecant() ? $item->product->sourceProduct : null)
            ->filter()
            ->unique('id')
            ->values();

        return response()->json($this->successPayload(
            $upload,
            $invoice,
            app(InventoryService::class)->getCostRecoveryForBottles($bottles),
        ), 201);
    }

    /**
     * @param array<int, array<string, mixed>> $recoveryAfter
     * @param array<int, array<string, mixed>> $recoveryBefore
     * @return array<string, mixed>
     */
    private function successPayload(PosSaleUpload $upload, Invoice $invoice, array $recoveryAfter = [], array $recoveryBefore = []): array
    {
        $bottleRecovery = array_values(array_map(
            static function (array $recovery) use ($recoveryBefore): array {
                $sourceProductId = $recovery['source_product_id'] ?? null;
                $previous = collect($recoveryBefore)->firstWhere('source_product_id', $sourceProductId);
                $justCovered = $recovery['covered'] === true && ($previous['covered'] ?? false) !== true;

                return $recovery + [
                    'just_covered' => $justCovered,
                    'alert' => $justCovered
                        ? "¡Botella recuperada! Las ventas de decants ya cubrieron el costo de {$recovery['source_product_name']}."
                        : $recovery['message'],
                ];
            },
            $recoveryAfter,
        ));

        return [
            'client_sale_uuid' => $upload->client_sale_uuid,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'total' => $invoice->total,
            'bottle_recovery' => $bottleRecovery,
        ];
    }
}
