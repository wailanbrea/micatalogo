<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CustomerAccountService
{
    public function recordInvoiceCharge(Customer $customer, Invoice $invoice, string $amount, ?int $userId): CustomerAccountEntry
    {
        return DB::transaction(function () use ($customer, $invoice, $amount, $userId): CustomerAccountEntry {
            $existing = CustomerAccountEntry::query()
                ->where('invoice_id', $invoice->id)
                ->where('type', 'charge')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $amountCents = $this->cents($amount);
            if ($amountCents <= 0 || $amountCents > $this->cents($invoice->total)) {
                throw new InvalidArgumentException('El crédito debe ser mayor que cero y no puede superar el total de la factura.');
            }

            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $this->assertCanCharge($customer, $amountCents);

            return $this->writeEntry($customer, 'charge', $amountCents, $userId, $invoice, null, null, "Crédito de factura {$invoice->invoice_number}");
        });
    }

    public function recordPayment(Customer $customer, string $amount, int $userId, string $transactionUuid, string $payloadHash, ?string $notes = null): CustomerAccountEntry
    {
        return $this->recordClientEntry($customer, 'payment', -$this->cents($amount), $userId, $transactionUuid, $payloadHash, $notes);
    }

    public function recordManualCharge(Customer $customer, string $amount, int $userId, string $transactionUuid, string $payloadHash, ?string $notes = null): CustomerAccountEntry
    {
        return $this->recordClientEntry($customer, 'charge', $this->cents($amount), $userId, $transactionUuid, $payloadHash, $notes);
    }

    public function recordAdjustment(Customer $customer, string $amount, int $userId, string $transactionUuid, string $payloadHash, ?string $notes = null): CustomerAccountEntry
    {
        return $this->recordClientEntry($customer, 'adjustment', $this->cents($amount), $userId, $transactionUuid, $payloadHash, $notes);
    }

    private function recordClientEntry(Customer $customer, string $type, int $amountCents, int $userId, string $transactionUuid, string $payloadHash, ?string $notes): CustomerAccountEntry
    {
        return DB::transaction(function () use ($customer, $type, $amountCents, $userId, $transactionUuid, $payloadHash, $notes): CustomerAccountEntry {
            $existing = CustomerAccountEntry::query()
                ->where('shop_id', $customer->shop_id)
                ->where('client_transaction_uuid', $transactionUuid)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (! hash_equals((string) $existing->payload_sha256, $payloadHash)) {
                    throw new InvalidArgumentException('Este identificador de transacción ya fue usado con datos distintos.', 409);
                }

                return $existing;
            }

            if ($amountCents === 0) {
                throw new InvalidArgumentException('El monto debe ser distinto de cero.');
            }

            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            if (! $customer->is_active) {
                throw new InvalidArgumentException('El cliente está inactivo.');
            }

            if ($amountCents > 0) {
                $this->assertCanCharge($customer, $amountCents);
            } elseif ($this->cents($customer->balance) + $amountCents < 0) {
                throw new InvalidArgumentException('El cobro no puede superar el saldo pendiente.');
            }

            return $this->writeEntry($customer, $type, $amountCents, $userId, null, $transactionUuid, $payloadHash, $notes);
        });
    }

    private function assertCanCharge(Customer $customer, int $amountCents): void
    {
        if (! $customer->is_active) {
            throw new InvalidArgumentException('El cliente está inactivo.');
        }

        if ($this->cents($customer->balance) + $amountCents > $this->cents($customer->credit_limit)) {
            throw new InvalidArgumentException('El crédito solicitado supera el límite disponible del cliente.', 409);
        }
    }

    private function writeEntry(Customer $customer, string $type, int $amountCents, ?int $userId, ?Invoice $invoice, ?string $transactionUuid, ?string $payloadHash, ?string $notes): CustomerAccountEntry
    {
        $balanceAfter = $this->cents($customer->balance) + $amountCents;
        $customer->forceFill(['balance' => $this->amount($balanceAfter)])->save();

        return CustomerAccountEntry::create([
            'shop_id' => $customer->shop_id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice?->id,
            'user_id' => $userId,
            'client_transaction_uuid' => $transactionUuid,
            'payload_sha256' => $payloadHash,
            'type' => $type,
            'amount' => $this->amount($amountCents),
            'balance_after' => $this->amount($balanceAfter),
            'notes' => $notes,
        ]);
    }

    private function cents(string|int|float|null $amount): int
    {
        $value = trim((string) $amount);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');

        return ($negative ? -1 : 1) * (((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0'));
    }

    private function amount(int $cents): string
    {
        return sprintf('%s%d.%02d', $cents < 0 ? '-' : '', intdiv(abs($cents), 100), abs($cents) % 100);
    }
}
