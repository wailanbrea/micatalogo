<?php

namespace App\Http\Controllers;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Services\MetricRecordingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicOrderController extends Controller
{
    public function store(StoreOrderRequest $request, Shop $shop, MetricRecordingService $metricService): JsonResponse|RedirectResponse
    {
        abort_unless($shop->status === 'active', 404);

        $validated = $request->validated();
        $requestedItems = collect($validated['items'])
            ->mapWithKeys(fn (array $item) => [$item['id'] => (int) $item['quantity']]);

        $order = DB::transaction(function () use ($shop, $requestedItems, $validated) {
            $products = $shop->products()
                ->where('moderation_status', ProductModerationStatus::Active)
                ->whereIn('public_id', $requestedItems->keys()->all())
                ->with(['inventory', 'sourceProduct.inventory'])
                ->lockForUpdate()
                ->get()
                ->keyBy('public_id');

            if ($products->count() !== $requestedItems->count()) {
                abort(422, 'Uno o más productos ya no están disponibles. Actualiza tu pedido e inténtalo de nuevo.');
            }

            $sourceInventories = ProductInventory::query()
                ->whereIn('product_id', $products->filter(fn (Product $product) => $product->isDecant())
                    ->map(fn (Product $product) => $product->inventory_source_product_id)
                    ->filter()
                    ->unique()
                    ->values())
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            $lines = [];
            foreach ($requestedItems as $publicId => $quantity) {
                $product = $products->get($publicId);
                $availableQuantity = $this->availableQuantity($product, $sourceInventories);
                $available = $product->isInventoryTracked()
                    ? $availableQuantity > 0 && ($product->isDecant() || $product->availability_status !== ProductAvailabilityStatus::OutOfStock)
                    : $product->availability_status === ProductAvailabilityStatus::Available;

                if (! $available) {
                    abort(422, "El producto {$product->name} ya no está disponible.");
                }

                if ($product->isInventoryTracked() && $quantity > $availableQuantity) {
                    abort(422, "La cantidad solicitada de {$product->name} supera el stock disponible.");
                }

                $unitPrice = $product->currentPrice();
                $lines[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $unitPrice * $quantity,
                ];
            }

            $subtotal = collect($lines)->sum('line_total');
            $order = $shop->orders()->create([
                'order_number' => $this->nextOrderNumber(),
                'customer_name' => $validated['customer_name'] ?? null,
                'delivery_type' => $validated['delivery_type'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'currency' => config('catalog.currency', 'DOP'),
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'status' => 'sent_to_whatsapp',
            ]);

            foreach ($lines as $line) {
                $product = $line['product'];
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->isDecant()
                        ? $product->name.' ('.$product->volume_ml.' ml)'
                        : $product->name,
                    'product_code' => $product->product_code,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                ]);
            }

            return $order->load('items');
        });

        $metricService->recordShopOrderSent($shop);
        $message = $this->whatsappMessage($shop, $order);
        $whatsappUrl = 'https://wa.me/'.$shop->whatsapp_country_code.$shop->whatsapp_number.'?text='.rawurlencode($message);

        if ($request->expectsJson()) {
            return response()->json([
                'order_number' => $order->order_number,
                'whatsapp_url' => $whatsappUrl,
            ], 201);
        }

        return redirect()->away($whatsappUrl);
    }

    private function availableQuantity(Product $product, $sourceInventories): int
    {
        if (! $product->isInventoryTracked()) {
            return 10000;
        }

        if ($product->isDecant() && (int) $product->volume_ml > 0) {
            $source = $product->sourceProduct;
            $sourceInventory = $source ? ($sourceInventories->get($source->id) ?? $source->inventory) : null;

            if ($source && $sourceInventory?->track_inventory) {
                $availableMl = $sourceInventory->available_ml;
                if ($availableMl === null) {
                    $availableMl = match ($source->sale_unit) {
                        'bottle' => (int) $source->volume_ml * (int) $sourceInventory->stock_quantity,
                        'ml' => (int) $sourceInventory->stock_quantity,
                        default => 0,
                    };
                }

                return intdiv(max(0, (int) $availableMl), (int) $product->volume_ml);
            }
        }

        return max(0, (int) ($product->inventory?->stock_quantity ?? 0));
    }

    private function nextOrderNumber(): string
    {
        do {
            $number = 'MC-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }

    private function whatsappMessage(Shop $shop, Order $order): string
    {
        $message = "Hola {$shop->name}, quiero realizar este pedido desde MiCatalogo:\n\n";
        $message .= "Pedido: #{$order->order_number}\n";

        foreach ($order->items as $item) {
            $message .= "{$item->quantity} × {$item->product_name} — RD$ ".number_format((float) $item->line_total, 0, ',', '.')."\n";
        }

        $message .= "\nTotal: RD$ ".number_format((float) $order->total, 0, ',', '.');

        if ($order->customer_name) {
            $message .= "\nCliente: {$order->customer_name}";
        }
        if ($order->delivery_type) {
            $message .= "\nEntrega: ".($order->delivery_type === 'delivery' ? 'Envío a domicilio' : 'Retiro en tienda');
        }
        if ($order->notes) {
            $message .= "\nNotas: {$order->notes}";
        }

        return $message;
    }
}
