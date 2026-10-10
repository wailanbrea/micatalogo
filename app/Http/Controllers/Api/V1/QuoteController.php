<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CommercialQuote;
use App\Models\Product;
use App\Models\Shop;
use App\Services\InventoryService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

class QuoteController extends Controller
{
    public function pdf(Request $request, Shop $shop, CommercialQuote $quote)
    {
        abort_unless($request->user()->canSellAtShop($shop) && $quote->shop_id === $shop->id, 404);

        return app(\App\Http\Controllers\SellerCommerceController::class)->quotePdf($request, $shop, $quote);
    }

    public function store(Request $request, Shop $shop): JsonResponse
    {
        abort_unless($request->user()->canSellAtShop($shop), 404);

        $data = $request->validate([
            'customer_id' => ['nullable', 'ulid'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'ulid'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['nullable', 'decimal:0,2', 'min:0'],
        ]);

        $productIds = array_values(array_unique(array_column($data['items'], 'product_id')));
        $products = $shop->products()->whereIn('public_id', $productIds)->get()->keyBy('public_id');
        if ($products->count() !== count($productIds)) {
            return response()->json(['message' => 'Uno o más productos no pertenecen a esta tienda.'], 422);
        }

        $customerId = null;
        if (! empty($data['customer_id'])) {
            $customerId = $shop->customers()->where('public_id', $data['customer_id'])->value('id');
            if (! $customerId) {
                return response()->json(['message' => 'El cliente no pertenece a esta tienda.'], 422);
            }
        }

        $lines = [];
        $subtotalCents = 0;
        foreach ($data['items'] as $item) {
            $product = $products[$item['product_id']];
            $unitPriceCents = Money::toCents($item['unit_price'] ?? $product->currentPriceDecimal());
            $quantity = (int) $item['quantity'];
            $lineTotalCents = $unitPriceCents * $quantity;
            $subtotalCents += $lineTotalCents;
            $lines[] = compact('product', 'quantity', 'unitPriceCents', 'lineTotalCents');
        }

        $quote = DB::transaction(function () use ($shop, $request, $data, $customerId, $lines, $subtotalCents): CommercialQuote {
            $quote = CommercialQuote::create([
                'shop_id' => $shop->id,
                'customer_id' => $customerId,
                'user_id' => $request->user()->id,
                'quote_number' => $this->nextQuoteNumber($shop),
                'status' => 'draft',
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'currency' => 'DOP',
                'subtotal' => Money::toDecimal($subtotalCents),
                'discount' => '0.00',
                'tax' => '0.00',
                'total' => Money::toDecimal($subtotalCents),
                'valid_until' => $data['valid_until'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $product = $line['product'];
                $quote->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_code' => $product->product_code,
                    'sale_unit' => $product->sale_unit ?: 'unit',
                    'volume_ml' => $product->volume_ml,
                    'quantity' => $line['quantity'],
                    'unit_price' => Money::toDecimal($line['unitPriceCents']),
                    'line_total' => Money::toDecimal($line['lineTotalCents']),
                ]);
            }

            return $quote->load('items');
        });

        return response()->json(['message' => 'Cotización guardada.', 'quote' => $this->payload($quote)], 201);
    }

    public function convert(Request $request, Shop $shop, CommercialQuote $quote, InventoryService $inventory): JsonResponse
    {
        abort_unless($request->user()->canSellAtShop($shop), 404);
        abort_unless($quote->shop_id === $shop->id, 404);

        if ($quote->converted_invoice_id || $quote->status === 'converted') {
            return response()->json(['message' => 'Esta cotización ya fue convertida en venta.'], 409);
        }
        if ($quote->status === 'cancelled') {
            return response()->json(['message' => 'Una cotización cancelada no puede convertirse en venta.'], 409);
        }
        if ($quote->isExpired()) {
            return response()->json(['message' => 'Una cotización vencida no puede convertirse en venta.'], 409);
        }

        try {
            $invoice = DB::transaction(function () use ($request, $shop, $quote, $inventory) {
                $quote->load('items');
                $products = $shop->products()->whereIn('id', $quote->items->pluck('product_id'))->get()->keyBy('id');
                if ($products->count() !== $quote->items->pluck('product_id')->unique()->count()) {
                    throw new InvalidArgumentException('La cotización contiene un producto que ya no está disponible.');
                }

                $sales = $quote->items->map(fn ($item) => [
                    'product' => $products->get($item->product_id),
                    'quantity' => (int) $item->quantity,
                    // Keep the persisted decimal as a string until Money::toCents
                    // normalizes it inside InventoryService.
                    'unit_price' => (string) $item->unit_price,
                ])->all();
                $movements = $inventory->recordCartSales($sales, $request->user()->id, 'mobile', 'paid', 0, 0, 'cash');
                $invoice = $movements[0]->fresh()->invoice;
                if ($quote->customer_id && $invoice) {
                    $invoice->update(['customer_id' => $quote->customer_id]);
                }
                $quote->update(['status' => 'converted', 'converted_invoice_id' => $invoice?->id]);

                return $invoice;
            });

            return response()->json([
                'message' => 'Cotización convertida en venta.',
                'invoice_number' => $invoice?->invoice_number,
                'invoice_url' => $invoice
                    ? URL::temporarySignedRoute('track.wa.shop', now()->addDays(7), [$shop, 'invoice' => $invoice->id])
                    : null,
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    private function payload(CommercialQuote $quote): array
    {
        return [
            'id' => $quote->public_id,
            'quote_number' => $quote->quote_number,
            'status' => $quote->status,
            'total' => (string) $quote->total,
            'items_count' => $quote->items->sum('quantity'),
        ];
    }

    private function nextQuoteNumber(Shop $shop): string
    {
        do {
            $number = 'COT-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while ($shop->quotes()->where('quote_number', $number)->exists());

        return $number;
    }
}
