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
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class PublicOrderController extends Controller
{
    public function received(Shop $shop, Order $order): \Illuminate\Contracts\View\View
    {
        abort_unless($shop->status === 'active' && $order->shop_id === $shop->id, 404);

        return view('shops.order-received', [
            'shop' => $shop,
            'order' => $order->load('items'),
            'whatsappUrl' => $this->whatsappUrl($shop, $order),
        ]);
    }

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
                $controlsStock = $product->isCombo() || $product->isInventoryTracked();
                $available = $controlsStock
                    ? $availableQuantity > 0 && ($product->isDecant() || $product->availability_status !== ProductAvailabilityStatus::OutOfStock)
                    : $product->availability_status === ProductAvailabilityStatus::Available;

                if (! $available) {
                    abort(422, "El producto {$product->name} ya no está disponible.");
                }

                if ($controlsStock && $quantity > $availableQuantity) {
                    abort(422, "La cantidad solicitada de {$product->name} supera el stock disponible.");
                }

                $unitPriceCents = $product->currentPriceCents();
                $lines[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price_cents' => $unitPriceCents,
                    'line_total_cents' => $unitPriceCents * $quantity,
                ];
            }

            $subtotalCents = (int) collect($lines)->sum('line_total_cents');
            $order = $shop->orders()->create([
                'order_number' => $this->nextOrderNumber(),
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'delivery_type' => $validated['delivery_type'] ?? null,
                'delivery_at' => $validated['delivery_at'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'currency' => config('catalog.currency', 'DOP'),
                'subtotal' => Money::toDecimal($subtotalCents),
                'total' => Money::toDecimal($subtotalCents),
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
                    'unit_price' => Money::toDecimal($line['unit_price_cents']),
                    'line_total' => Money::toDecimal($line['line_total_cents']),
                ]);
            }

            return $order->load('items');
        });

        $metricService->recordShopOrderSent($shop);
        $confirmationUrl = URL::temporarySignedRoute(
            'seller.shops.orders.confirm.show',
            now()->addDays(7),
            [$shop, $order]
        );
        $whatsappUrl = $this->whatsappUrl($shop, $order, $confirmationUrl);
        $publicConfirmationUrl = URL::temporarySignedRoute(
            'orders.received',
            now()->addDays(7),
            [$shop, $order]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'order_number' => $order->order_number,
                'confirmation_url' => $confirmationUrl,
                'public_confirmation_url' => $publicConfirmationUrl,
                'whatsapp_url' => $whatsappUrl,
            ], 201);
        }

        return redirect()->away($publicConfirmationUrl);
    }

    private function availableQuantity(Product $product, $sourceInventories): int
    {
        if ($product->isCombo()) {
            return $product->comboAvailableQuantity();
        }

        if (! $product->isInventoryTracked()) {
            return 10000;
        }

        if ($product->isDecant() && (int) $product->volume_ml > 0) {
            $source = $product->sourceProduct;
            $sourceInventory = $source ? ($sourceInventories->get($source->id) ?? $source->inventory) : null;
            if ($source && app(\App\Services\DecantInventoryService::class)->managed($source)) return app(\App\Services\DecantInventoryService::class)->sellable($product);

            if ($source && $sourceInventory?->track_inventory) {
                $availableMl = $sourceInventory->reserved_decant_ml ?? $sourceInventory->available_ml;
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

    private function whatsappUrl(Shop $shop, Order $order, ?string $confirmationUrl = null): string
    {
        $confirmationUrl ??= URL::temporarySignedRoute(
            'seller.shops.orders.confirm.show',
            now()->addDays(7),
            [$shop, $order]
        );

        return 'https://wa.me/'.$shop->whatsapp_country_code.$shop->whatsapp_number.'?text='.rawurlencode($this->whatsappMessage($shop, $order, $confirmationUrl));
    }

    private function whatsappMessage(Shop $shop, Order $order, string $confirmationUrl): string
    {
        $message = "Hola {$shop->name}, quiero realizar este pedido desde MiCatalogo:\n\n";
        $message .= "Pedido: #{$order->order_number}\n";

        foreach ($order->items as $item) {
            $message .= "{$item->quantity} × {$item->product_name} — RD$ ".number_format((float) $item->line_total, 0, ',', '.')."\n";
        }

        $message .= "\nTotal: RD$ ".number_format((float) $order->total, 0, ',', '.');
        $message .= "\n\nConfirmar pedido y registrar pago:\n{$confirmationUrl}";

        if ($order->customer_name) {
            $message .= "\nCliente: {$order->customer_name}";
        }
        if ($order->customer_phone) {
            $message .= "\nWhatsApp del cliente: {$order->customer_phone}";
        }
        if ($order->delivery_type) {
            $message .= "\nEntrega: ".($order->delivery_type === 'delivery' ? 'Envío a domicilio' : 'Retiro en tienda');
        }
        if ($order->delivery_at) {
            $message .= "\nFecha solicitada: ".$order->delivery_at->format('d/m/Y');
        }
        if ($order->notes) {
            $message .= "\nNotas: {$order->notes}";
        }

        return $message;
    }
}
