<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Converts a public catalog order into one accounting operation.
 * The web panel and the mobile API must use the same transaction so stock,
 * invoice, cash and customer credit cannot diverge by channel.
 */
class OrderConfirmationService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @param array{payment_kind?: string, payment_method?: string, customer_id?: string|int|null, credit_amount?: string|int|float|null, reference?: string|null} $data
     */
    public function confirm(Shop $shop, Order $order, User $user, array $data): ?string
    {
        return DB::transaction(function () use ($shop, $order, $user, $data): ?string {
            $lockedOrder = Order::query()->with('items')->lockForUpdate()->findOrFail($order->id);
            if ($lockedOrder->shop_id !== $shop->id) {
                abort(404);
            }
            if ($lockedOrder->invoice_id !== null) {
                return $lockedOrder->invoice?->invoice_number;
            }

            $paymentKind = $data['payment_kind'] ?? 'paid';
            $paymentKind = $paymentKind === 'cash' ? 'paid' : $paymentKind;
            $totalCents = Money::toCents($lockedOrder->total);
            $creditCents = match ($paymentKind) {
                'credit' => $totalCents,
                'mixed' => Money::toCents($data['credit_amount'] ?? 0),
                default => 0,
            };

            if ($paymentKind === 'mixed' && ($creditCents <= 0 || $creditCents >= $totalCents)) {
                throw ValidationException::withMessages(['credit_amount' => 'En un pago mixto, el crédito debe ser menor que el total y mayor que cero.']);
            }
            if ($creditCents > $totalCents) {
                throw ValidationException::withMessages(['credit_amount' => 'El crédito no puede superar el total del pedido.']);
            }

            $customer = null;
            if ($creditCents > 0 && empty($data['customer_id'])) {
                throw ValidationException::withMessages(['customer_id' => 'Selecciona el cliente que asumirá el crédito.']);
            }
            if (! empty($data['customer_id'])) {
                $customer = $shop->customers()
                    ->where(function ($query) use ($data): void {
                        $query->whereKey($data['customer_id'])->orWhere('public_id', $data['customer_id']);
                    })
                    ->where('is_active', true)
                    ->first();
                if (! $customer) {
                    throw ValidationException::withMessages(['customer_id' => 'El cliente no pertenece a esta tienda.']);
                }
            }

            $paymentMethod = $data['payment_method'] ?? 'cash';
            if ($paymentMethod === 'credit') {
                $paymentMethod = 'cash';
            }
            $lines = $lockedOrder->items->map(function ($item) use ($shop): array {
                $product = $shop->products()->find($item->product_id);
                if (! $product) {
                    throw ValidationException::withMessages(['order' => 'Un producto del pedido ya no está disponible.']);
                }

                // The order snapshot is already a decimal string. Preserve it
                // until Money::toCents so WhatsApp confirmations cannot lose cents.
                return ['product' => $product, 'quantity' => $item->quantity, 'unit_price' => (string) $item->unit_price];
            })->all();

            $initialStatus = $creditCents === 0 ? 'paid' : ($totalCents === $creditCents ? 'pending' : 'partial');
            $movements = $this->inventory->recordCartSales(
                $lines,
                $user->id,
                'whatsapp',
                $initialStatus,
                0,
                0,
                $paymentMethod,
                $customer,
                Money::toDecimal($creditCents),
                $data['reference'] ?? null,
            );
            $invoice = $movements[0]->fresh()->invoice;
            $lockedOrder->update(['status' => 'confirmed', 'invoice_id' => $movements[0]->invoice_id]);

            return $invoice?->invoice_number;
        });
    }
}
