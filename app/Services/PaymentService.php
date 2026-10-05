<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Shop;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        protected CustomerAccountService $customerAccountService,
        protected CashRegisterService $cashRegisterService
    ) {}

    /**
     * Process payments for a newly created or existing invoice.
     * Enforces the rule: sum(payments) + credit = invoice total (in cents).
     *
     * @param array $payments Array of [ 'method' => string, 'amount' => string|float, 'reference' => ?string, 'notes' => ?string ]
     * @param string|float $creditAmount Amount left on customer credit
     * @param Customer|null $customer Required if credit > 0
     * @param string|null $clientOperationUuid Idempotency key
     * @param string|null $payloadHash SHA-256 payload hash
     * @return array Created InvoicePayment models
     */
    public function processInvoicePayments(
        Shop $shop,
        Invoice $invoice,
        User $user,
        array $payments,
        string|float $creditAmount = 0,
        ?Customer $customer = null,
        ?string $clientOperationUuid = null,
        ?string $payloadHash = null
    ): array {
        return DB::transaction(function () use (
            $shop, $invoice, $user, $payments, $creditAmount, $customer, $clientOperationUuid, $payloadHash
        ): array {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            // 1. Check idempotency if clientOperationUuid provided
            if ($clientOperationUuid) {
                $existing = InvoicePayment::query()
                    ->where('shop_id', $shop->id)
                    ->where('client_operation_uuid', $clientOperationUuid)
                    ->get();

                if ($existing->isNotEmpty()) {
                    if ($payloadHash && ! hash_equals((string) $existing->first()->payload_sha256, $payloadHash)) {
                        throw new InvalidArgumentException('Conflicto de idempotencia: el identificador de operación ya fue utilizado con datos distintos.', 409);
                    }

                    return $existing->all();
                }
            }

            $totalCents = Money::toCents($invoice->total);
            $creditCents = Money::toCents($creditAmount);

            if ($creditCents < 0) {
                throw new InvalidArgumentException('El crédito no puede ser negativo.', 422);
            }

            // Check for credit sent via payments[] to prevent double counting
            $hasPaymentCredit = false;
            $paymentCreditCents = 0;
            $nonCreditPayments = [];

            foreach ($payments as $item) {
                $method = (string) ($item['method'] ?? $item['payment_method'] ?? 'cash');
                if ($method === 'credit') {
                    $hasPaymentCredit = true;
                    $paymentCreditCents += Money::toCents($item['amount'] ?? 0);
                } else {
                    $nonCreditPayments[] = $item;
                }
            }

            if ($hasPaymentCredit) {
                if ($creditCents > 0 && $creditCents !== $paymentCreditCents) {
                    throw new InvalidArgumentException('Conflicto de crédito: credit_amount y pago con método crédito no coinciden.', 422);
                }
                // Normalize without double counting
                $creditCents = max($creditCents, $paymentCreditCents);
            }

            if ($creditCents > 0 && ! $customer) {
                throw new InvalidArgumentException('Se requiere un cliente para registrar ventas a crédito.', 422);
            }

            $paidCents = 0;
            $allowedMethods = config('catalog.payment_methods', []);
            $activeCashSession = $this->cashRegisterService->getCurrentSession($shop, $user);

            $createdPayments = [];

            foreach ($nonCreditPayments as $item) {
                $method = (string) ($item['method'] ?? $item['payment_method'] ?? 'cash');
                if (! isset($allowedMethods[$method])) {
                    throw new InvalidArgumentException("Método de pago no reconocido: {$method}.", 422);
                }

                $amountCents = Money::toCents($item['amount'] ?? 0);
                if ($amountCents <= 0) {
                    continue;
                }

                $paidCents += $amountCents;

                // Check cash register link
                $cashSessionId = null;
                if ($method === 'cash' && $activeCashSession) {
                    $cashSessionId = $activeCashSession->id;
                }

                $payment = InvoicePayment::create([
                    'public_id' => (string) Str::ulid(),
                    'shop_id' => $shop->id,
                    'invoice_id' => $invoice->id,
                    'customer_id' => $customer?->id,
                    'user_id' => $user->id,
                    'cash_register_session_id' => $cashSessionId,
                    'payment_method' => $method,
                    'amount' => Money::toDecimal($amountCents),
                    'amount_cents' => $amountCents,
                    'reference' => $item['reference'] ?? null,
                    'notes' => $item['notes'] ?? null,
                    'received_at' => now(),
                    'client_operation_uuid' => $clientOperationUuid,
                    'payload_sha256' => $payloadHash,
                ]);

                // Record cash movement if cash and open session
                if ($method === 'cash' && $activeCashSession) {
                    $this->cashRegisterService->recordMovement(
                        $activeCashSession,
                        $user,
                        'sale',
                        Money::toDecimal($amountCents),
                        "Venta #{$invoice->invoice_number}",
                        'invoice',
                        $invoice->id
                    );
                }

                $createdPayments[] = $payment;
            }

            // Validation: paidCents + creditCents must equal totalCents
            if ($paidCents + $creditCents !== $totalCents) {
                $difference = ($paidCents + $creditCents) - $totalCents;
                $formattedDiff = number_format(abs($difference) / 100, 2);
                if ($difference > 0) {
                    throw new InvalidArgumentException("La suma de los pagos y crédito supera el total por RD\${$formattedDiff}.", 422);
                } else {
                    throw new InvalidArgumentException("Faltan RD\${$formattedDiff} para cubrir el total de la venta.", 422);
                }
            }

            // Record customer credit charge if credit > 0
            if ($creditCents > 0 && $customer) {
                $this->customerAccountService->recordInvoiceCharge(
                    $customer,
                    $invoice,
                    Money::toDecimal($creditCents),
                    $user->id
                );
            }

            // Determine invoice status derived strictly in backend
            $status = match (true) {
                $invoice->status === 'partial' => 'partial',
                $creditCents === 0 => 'paid',
                $paidCents === 0 => 'pending',
                default => 'partial',
            };

            $invoice->status = $status;
            if ($customer && ! $invoice->customer_id) {
                $invoice->customer_id = $customer->id;
            }
            $invoice->save();

            return $createdPayments;
        });
    }

    public function recordInvoicePayments(
        Invoice $invoice,
        array $payments,
        User $user,
        string|float $creditAmount = 0,
        ?Customer $customer = null,
        ?string $clientOperationUuid = null,
        ?string $payloadHash = null
    ): array {
        return $this->processInvoicePayments(
            $invoice->shop,
            $invoice,
            $user,
            $payments,
            $creditAmount,
            $customer ?? $invoice->customer,
            $clientOperationUuid,
            $payloadHash
        );
    }

    /**
     * Record a customer account payment and register in cash register if cash.
     */
    public function recordCustomerDebtPayment(
        Shop $shop,
        Customer $customer,
        User $user,
        string|float $amount,
        string $paymentMethod = 'cash',
        string $transactionUuid = '',
        string $payloadHash = '',
        ?string $notes = null
    ): array {
        return DB::transaction(function () use ($shop, $customer, $user, $amount, $paymentMethod, $transactionUuid, $payloadHash, $notes): array {
            $uuid = $transactionUuid ?: (string) Str::uuid();
            $hash = $payloadHash ?: hash('sha256', "debt-pay:{$customer->id}:{$amount}:{$paymentMethod}");

            // 1. Record payment in customer account ledger
            $entry = $this->customerAccountService->recordPayment(
                $customer,
                (string) $amount,
                $user->id,
                $uuid,
                $hash,
                $notes ?: "Cobro cliente {$customer->name} ({$paymentMethod})"
            );

            // 2. If paid in cash, record in active cash register session
            $cashMovement = null;
            if ($paymentMethod === 'cash') {
                $activeCashSession = $this->cashRegisterService->getCurrentSession($shop, $user);
                if ($activeCashSession) {
                    $cashMovement = $this->cashRegisterService->recordMovement(
                        $activeCashSession,
                        $user,
                        'customer_payment',
                        $amount,
                        "Cobro a {$customer->name}",
                        'customer_account_entry',
                        $entry->id
                    );
                }
            }

            return [
                'entry' => $entry,
                'cash_movement' => $cashMovement,
            ];
        });
    }

    public function toCents(mixed $amount): int
    {
        return Money::toCents($amount);
    }

    public function toDecimal(int $cents): string
    {
        return Money::toDecimal($cents);
    }
}
