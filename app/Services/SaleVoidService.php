<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\DailyClosure;
use App\Models\DailyClosureAdjustment;
use App\Models\Invoice;
use App\Models\Shop;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Voids an existing shop invoice from the web sales history while keeping
 * inventory, cash movements, customer debt, and closed-day audits consistent.
 */
class SaleVoidService
{
    public function canVoid(Shop $shop, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->canSellAtShop($shop)
            && ($user->ownsShop($shop) || $user->isActiveShopManager($shop))
            && in_array('sales', app(SellerMenuService::class)->visibleForUser($shop, $user), true);
    }

    public function void(Shop $shop, Invoice $invoice, User $user, string $reason): Invoice
    {
        abort_unless($this->canVoid($shop, $user), 403);
        abort_unless($invoice->shop_id === $shop->id, 404);

        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw new InvalidArgumentException('Indica un motivo de hasta 255 caracteres.', 422);
        }

        return DB::transaction(function () use ($shop, $invoice, $user, $reason): Invoice {
            $locked = Invoice::where('shop_id', $shop->id)
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'void') {
                return $locked;
            }
            if ($locked->status === 'refunded') {
                throw new InvalidArgumentException('Esta venta ya fue reembolsada.', 422);
            }
            if (DB::table('invoice_returns')->where('invoice_id', $locked->id)->exists()) {
                throw new InvalidArgumentException('Esta venta ya tiene devoluciones registradas y no puede anularse directamente.', 422);
            }

            $payments = $locked->payments()->whereNull('reversed_at')->lockForUpdate()->get();
            if ($payments->contains(fn ($payment) => $payment->customer_account_entry_id !== null)) {
                throw new InvalidArgumentException('Esta venta tiene abonos aplicados. Anula primero el abono desde Cobros.', 422);
            }

            $this->restockItems($shop, $locked, $user, $reason);

            $paidCents = (int) $payments->sum('amount_cents');
            if ($payments->isNotEmpty()) {
                $now = now();
                foreach ($payments as $payment) {
                    $payment->forceFill(['reversed_at' => $now])->save();
                }

                $cashMovements = CashMovement::where('shop_id', $shop->id)
                    ->where('reference_type', 'invoice')
                    ->where('reference_id', $locked->id)
                    ->where('amount_cents', '>', 0)
                    ->lockForUpdate()
                    ->get();

                $hash = hash('sha256', json_encode([$shop->id, $locked->id, $reason], JSON_THROW_ON_ERROR));
                foreach ($cashMovements as $movement) {
                    CashRegisterSession::where('shop_id', $shop->id)
                        ->whereKey($movement->cash_register_session_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    CashMovement::create([
                        'cash_register_session_id' => $movement->cash_register_session_id,
                        'shop_id' => $shop->id,
                        'user_id' => $user->id,
                        'type' => 'sale_reversal',
                        'amount' => Money::toDecimal(-abs($movement->amount_cents)),
                        'amount_cents' => -abs($movement->amount_cents),
                        'reference_type' => 'invoice_reversal',
                        'reference_id' => $locked->id,
                        'notes' => "Anulación de venta #{$locked->invoice_number}: {$reason}",
                        'occurred_at' => $movement->occurred_at,
                        'client_operation_uuid' => (string) Str::uuid(),
                        'payload_sha256' => $hash,
                    ]);
                }
            }

            $pendingDebtCents = max(0, Money::toCents($locked->total) - $paidCents);
            if ($locked->customer_id && $pendingDebtCents > 0) {
                $customer = Customer::whereKey($locked->customer_id)->lockForUpdate()->first();
                if ($customer) {
                    $newBalanceCents = max(0, Money::toCents($customer->balance) - $pendingDebtCents);
                    $customer->update(['balance' => Money::toDecimal($newBalanceCents)]);
                    CustomerAccountEntry::create([
                        'shop_id' => $shop->id,
                        'customer_id' => $customer->id,
                        'user_id' => $user->id,
                        'invoice_id' => $locked->id,
                        'type' => 'adjustment',
                        'amount' => Money::toDecimal(-$pendingDebtCents),
                        'balance_after' => Money::toDecimal($newBalanceCents),
                        'notes' => "Anulación de factura #{$locked->invoice_number}: {$reason}",
                        'client_transaction_uuid' => (string) Str::uuid(),
                    ]);
                }
            }

            $locked->update(['status' => 'void']);

            $timezone = app(ShopOperationalSettingsService::class)->forShop($shop)['timezone'];
            $date = ($locked->issued_at ?? $locked->created_at)->copy()->timezone($timezone)->toDateString();
            $closure = DailyClosure::where('shop_id', $shop->id)->whereDate('business_date', $date)->lockForUpdate()->first();
            if ($closure) {
                $previous = DailyClosureAdjustment::where('daily_closure_id', $closure->id)->latest('id')->first();
                $snapshot = app(DailyCloseService::class)->calculateCents($shop, $date);
                $counted = $previous ? $previous->counted_cash_cents : $closure->counted_cash_cents;
                DailyClosureAdjustment::create([
                    'shop_id' => $shop->id,
                    'daily_closure_id' => $closure->id,
                    'user_id' => $user->id,
                    'previous_adjustment_id' => $previous?->id,
                    'client_operation_uuid' => (string) Str::uuid(),
                    'payload_sha256' => hash('sha256', json_encode([$locked->id, $reason], JSON_THROW_ON_ERROR)),
                    'reason' => "Anulación de venta #{$locked->invoice_number}: {$reason}",
                    'expected_cash_cents' => $snapshot['expected_cash_cents'],
                    'counted_cash_cents' => $counted,
                    'difference_cents' => $counted === null ? null : $counted - $snapshot['expected_cash_cents'],
                    'snapshot' => array_intersect_key($snapshot, array_flip(['sales_cash_cents', 'debt_collections_cash_cents', 'other_inflows_cash_cents', 'expenses_cash_cents', 'cash_out_cents'])),
                    'created_at' => now(),
                ]);
            }

            return $locked->fresh();
        });
    }

    private function restockItems(Shop $shop, Invoice $invoice, User $user, string $reason): void
    {
        $decantAvailable = app(DecantInventoryService::class)->available();
        foreach ($invoice->items()->get() as $item) {
            $oldReturnedQty = (int) DB::table('invoice_return_items')->where('invoice_item_id', $item->id)->sum('quantity');
            $remQty = $item->quantity - $oldReturnedQty;
            if ($remQty <= 0) {
                continue;
            }
            $product = $shop->products()->withTrashed()->whereKey($item->product_id)->first();
            if (! $product) {
                continue;
            }
            $remainder = $decantAvailable
                ? DB::table('decant_remainder_sales')
                    ->join('inventory_movements', 'inventory_movements.id', '=', 'decant_remainder_sales.movement_id')
                    ->where('inventory_movements.invoice_id', $invoice->id)
                    ->where('inventory_movements.product_id', $product->id)
                    ->select('decant_remainder_sales.*')
                    ->first()
                : null;
            $cost = $item->total_cost_cents === null ? null : intdiv($item->total_cost_cents * $remQty, $item->quantity);
            if ($remainder) {
                app(DecantInventoryService::class)->returnRemainder($product, $remainder, $cost, $user->id);
            } else {
                app(InventoryService::class)->recordReturn($product, $remQty, $cost, $user->id, "Anulación de venta #{$invoice->invoice_number}: {$reason}");
            }
        }
    }
}
