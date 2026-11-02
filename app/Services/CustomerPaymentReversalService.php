<?php

namespace App\Services;

use App\Models\{CashMovement, CashRegisterSession, Customer, CustomerAccountEntry, DailyClosure, DailyClosureAdjustment, Invoice, InvoicePayment, Shop, User};
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CustomerPaymentReversalService
{
    public function canReverse(Shop $shop, User $user): bool
    {
        return $user->canSellAtShop($shop) && ($user->ownsShop($shop) || $user->isActiveShopManager($shop))
            && in_array('collections', app(SellerMenuService::class)->visibleForUser($shop, $user), true);
    }

    public function reverse(Shop $shop, Customer $customer, int $paymentId, User $user, string $uuid, string $reason): CustomerAccountEntry
    {
        abort_unless($this->canReverse($shop, $user), 403);
        abort_unless($customer->shop_id === $shop->id, 404);
        $reason = trim($reason);
        if (! Str::isUuid($uuid) || $reason === '' || mb_strlen($reason) > 255) throw new InvalidArgumentException('Indica un motivo de hasta 255 caracteres.', 422);
        $hash = hash('sha256', json_encode([$customer->id, $paymentId, $reason], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($shop, $customer, $paymentId, $user, $uuid, $reason, $hash) {
            Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $payment = CustomerAccountEntry::where('shop_id', $shop->id)->where('customer_id', $customer->id)->whereKey($paymentId)->lockForUpdate()->firstOrFail();
            if ($payment->type !== 'payment' || Money::toCents($payment->amount) >= 0) throw new InvalidArgumentException('Solo se pueden anular abonos de clientes.', 422);
            $retry = CustomerAccountEntry::where('shop_id', $shop->id)->where('client_transaction_uuid', $uuid)->first();
            if ($retry) {
                if ((int) $retry->reverses_entry_id !== $paymentId || ! hash_equals((string) $retry->payload_sha256, $hash)) throw new InvalidArgumentException('El identificador ya confirma otra operación.', 409);
                return $retry;
            }
            if ($payment->reversal()->exists()) throw new InvalidArgumentException('Este abono ya fue anulado. Actualiza el historial.', 409);
            $amount = abs(Money::toCents($payment->amount));
            $balance = Money::toCents($customer->balance) + $amount;
            $allocations = InvoicePayment::where('shop_id', $shop->id)->where('customer_account_entry_id', $paymentId)->lockForUpdate()->get();
            foreach ($allocations->groupBy('invoice_id') as $invoiceId => $rows) {
                $invoice = Invoice::where('shop_id', $shop->id)->where('customer_id', $customer->id)->whereKey($invoiceId)->lockForUpdate()->firstOrFail();
                $paidCents = (int) $invoice->payments()->sum('amount_cents');
                $returned = Money::toCents(DB::table('invoice_returns')->where('shop_id', $shop->id)->where('invoice_id', $invoiceId)->sum('total'));
                $net = Money::toCents($invoice->total) - $returned;
                $restore = (int) $rows->sum('amount_cents');
                if ($invoice->status === 'void' || max(0, $net - $paidCents + $restore) - max(0, $net - $paidCents) !== $restore) {
                    throw new InvalidArgumentException('La factura tiene una devolución o anulación que requiere revisar el reembolso antes de anular este abono.', 409);
                }
            }
            $cash = CashMovement::where('shop_id', $shop->id)->where('reference_type', 'customer_account_entry')->where('reference_id', $paymentId)->where('type', 'customer_payment')->lockForUpdate()->get();
            $entry = CustomerAccountEntry::create([
                'shop_id' => $shop->id, 'customer_id' => $customer->id, 'user_id' => $user->id,
                'type' => 'payment_reversal', 'amount' => Money::toDecimal($amount), 'balance_after' => Money::toDecimal($balance),
                'reverses_entry_id' => $paymentId, 'payment_method' => $payment->payment_method ?? $allocations->first()?->payment_method,
                'client_transaction_uuid' => $uuid, 'payload_sha256' => $hash, 'notes' => $reason,
            ]);
            // Restoring a mistaken collection may exceed today's limit or involve an inactive customer.
            $customer->forceFill(['balance' => Money::toDecimal($balance)])->save();
            $payment->forceFill(['reversed_at' => $entry->created_at, 'payment_method' => $entry->payment_method])->save();
            foreach ($allocations as $allocation) {
                abort_unless((int) $allocation->customer_id === $customer->id, 409, 'La asignación del abono requiere revisión.');
                $allocation->forceFill(['reversed_at' => $entry->created_at])->save();
            }
            foreach ($allocations->pluck('invoice_id')->unique()->sort() as $invoiceId) {
                $invoice = Invoice::where('shop_id', $shop->id)->where('customer_id', $customer->id)->whereKey($invoiceId)->lockForUpdate()->firstOrFail();
                if ($invoice->status === 'void') continue;
                $paid = (int) $invoice->payments()->sum('amount_cents');
                $returned = Money::toCents(DB::table('invoice_returns')->where('shop_id', $shop->id)->where('invoice_id', $invoiceId)->sum('total'));
                $pending = max(0, Money::toCents($invoice->total) - $paid - $returned);
                $invoice->forceFill(['status' => $pending === 0 ? 'paid' : ($paid > 0 ? 'partial' : 'pending')])->save();
            }
            // Correction belongs to the original register, including a closed one. Its saved close is never rewritten.
            foreach ($cash as $movement) {
                CashRegisterSession::where('shop_id', $shop->id)->whereKey($movement->cash_register_session_id)->lockForUpdate()->firstOrFail();
                CashMovement::create([
                    'cash_register_session_id' => $movement->cash_register_session_id, 'shop_id' => $shop->id, 'user_id' => $user->id,
                    'type' => 'customer_payment_reversal', 'amount' => Money::toDecimal(-abs($movement->amount_cents)), 'amount_cents' => -abs($movement->amount_cents),
                    'reference_type' => 'customer_account_reversal', 'reference_id' => $entry->id,
                    'notes' => "Anulación de abono #{$paymentId}: {$reason}", 'occurred_at' => $movement->occurred_at,
                    'client_operation_uuid' => (string) Str::uuid(), 'payload_sha256' => $hash,
                ]);
            }
            $timezone = app(ShopOperationalSettingsService::class)->forShop($shop)['timezone'];
            $date = $payment->created_at->copy()->timezone($timezone)->toDateString();
            $closure = DailyClosure::where('shop_id', $shop->id)->whereDate('business_date', $date)->lockForUpdate()->first();
            if ($closure) {
                $previous = DailyClosureAdjustment::where('daily_closure_id', $closure->id)->latest('id')->first();
                $snapshot = app(DailyCloseService::class)->calculateCents($shop, $date);
                $counted = $previous ? $previous->counted_cash_cents : $closure->counted_cash_cents;
                DailyClosureAdjustment::create([
                    'shop_id' => $shop->id, 'daily_closure_id' => $closure->id, 'user_id' => $user->id, 'previous_adjustment_id' => $previous?->id,
                    'client_operation_uuid' => $uuid, 'payload_sha256' => $hash, 'reason' => "Anulación de abono #{$paymentId}: {$reason}",
                    'expected_cash_cents' => $snapshot['expected_cash_cents'], 'counted_cash_cents' => $counted,
                    'difference_cents' => $counted === null ? null : $counted - $snapshot['expected_cash_cents'],
                    'snapshot' => array_intersect_key($snapshot, array_flip(['sales_cash_cents','debt_collections_cash_cents','other_inflows_cash_cents','expenses_cash_cents','cash_out_cents'])), 'created_at' => now(),
                ]);
            }
            return $entry;
        });
    }

    public function payload(CustomerAccountEntry $payment, Shop $shop, User $user): array
    {
        $payment->loadMissing(['customer', 'reversal.user', 'invoicePayments']);
        return [
            'id' => (string) $payment->id, 'customer_id' => $payment->customer?->public_id,
            'customer_name' => $payment->customer?->name ?? 'Cliente', 'client_transaction_uuid' => $payment->client_transaction_uuid,
            'receipt' => 'REC-'.$payment->id, 'amount' => Money::toDecimal(abs(Money::toCents($payment->amount))),
            'payment_method' => $payment->payment_method ?? $payment->invoicePayments->first()?->payment_method ?? 'other',
            'notes' => $payment->notes, 'created_at' => $payment->created_at->toISOString(),
            'can_reverse' => ! $payment->reversal && $this->canReverse($shop, $user),
            'reversal' => $payment->reversal ? ['id' => (string) $payment->reversal->id, 'reason' => $payment->reversal->notes,
                'user_name' => $payment->reversal->user?->name, 'created_at' => $payment->reversal->created_at->toISOString()] : null,
        ];
    }
}
