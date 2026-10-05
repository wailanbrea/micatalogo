<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
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
     * @param  array  $payments  Array of [ 'method' => string, 'amount' => string|float, 'reference' => ?string, 'notes' => ?string ]
     * @param  string|float  $creditAmount  Amount left on customer credit
     * @param  Customer|null  $customer  Required if credit > 0
     * @param  string|null  $clientOperationUuid  Idempotency key
     * @param  string|null  $payloadHash  SHA-256 payload hash
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

            // Determine invoice status derived strictly in backend from real money
            $status = match (true) {
                $paidCents === $totalCents && $creditCents === 0 => 'paid',
                $paidCents === 0 && $creditCents === $totalCents => 'pending',
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
     * Record a customer account debt payment with FIFO allocation to open invoices,
     * maintaining consistency across Customer balance, Invoice balance, Aging, and Cash Register.
     *
     * @return array{
     *     payment_id: int,
     *     client_transaction_uuid: string|null,
     *     amount: string,
     *     amount_cents: int,
     *     payment_method: string,
     *     customer_balance: string,
     *     allocations: array<int, array{invoice_id: int, invoice_number: string, allocated_amount: string, allocated_cents: int, remaining_invoice_balance: string, invoice_status: string}>,
     *     cash_register_affected: bool,
     *     cash_movement: CashMovement|null,
     *     entry: CustomerAccountEntry,
     *     server_timestamp: string
     * }
     */
    public function recordCustomerDebtPayment(
        Shop $shop,
        Customer $customer,
        User $user,
        string|float $amount,
        string $paymentMethod = 'cash',
        string $transactionUuid = '',
        string $payloadHash = '',
        ?string $notes = null,
        ?string $reference = null
    ): array {
        return DB::transaction(function () use ($shop, $customer, $user, $amount, $paymentMethod, $transactionUuid, $payloadHash, $notes, $reference): array {
            $amountCents = Money::toCents($amount);
            if ($amountCents <= 0) {
                throw new InvalidArgumentException('El monto del cobro debe ser mayor a cero.', 422);
            }

            $allowedMethods = config('catalog.payment_methods', [
                'cash' => ['label' => 'Efectivo'],
                'card' => ['label' => 'Tarjeta'],
                'bank_transfer' => ['label' => 'Transferencia'],
                'credit' => ['label' => 'Crédito'],
                'other' => ['label' => 'Otro'],
            ]);

            if (! isset($allowedMethods[$paymentMethod]) || $paymentMethod === 'credit') {
                throw new InvalidArgumentException("Método de pago no reconocido para cobro de deuda: {$paymentMethod}.", 422);
            }

            $uuid = $transactionUuid ?: (string) Str::uuid();
            $hash = $payloadHash ?: hash('sha256', "debt-pay:{$customer->id}:{$amount}:{$paymentMethod}");

            // 1. Idempotency replay check
            $existingEntry = CustomerAccountEntry::query()
                ->where('shop_id', $shop->id)
                ->where('client_transaction_uuid', $uuid)
                ->lockForUpdate()
                ->first();

            if ($existingEntry) {
                if ($hash && ! hash_equals((string) $existingEntry->payload_sha256, $hash)) {
                    throw new InvalidArgumentException('Conflicto de idempotencia: este identificador de transacción ya fue usado con datos distintos.', 409);
                }

                $existingAllocations = InvoicePayment::query()
                    ->where('customer_account_entry_id', $existingEntry->id)
                    ->with('invoice')
                    ->get()
                    ->map(fn (InvoicePayment $p) => [
                        'invoice_id' => $p->invoice_id,
                        'invoice_number' => (string) ($p->invoice?->invoice_number ?? ''),
                        'allocated_amount' => (string) $p->amount,
                        'allocated_cents' => (int) $p->amount_cents,
                        'remaining_invoice_balance' => Money::toDecimal((int) max(0, Money::toCents($p->invoice?->total ?? 0) - (int) $p->invoice?->payments()->sum('amount_cents'))),
                        'invoice_status' => (string) ($p->invoice?->status ?? 'paid'),
                    ])->all();

                $existingCashMovement = CashMovement::query()
                    ->where('reference_type', 'customer_account_entry')
                    ->where('reference_id', $existingEntry->id)
                    ->first();

                return [
                    'payment_id' => $existingEntry->id,
                    'client_transaction_uuid' => $existingEntry->client_transaction_uuid,
                    'amount' => Money::toDecimal(abs(Money::toCents($existingEntry->amount))),
                    'amount_cents' => abs(Money::toCents($existingEntry->amount)),
                    'payment_method' => $paymentMethod,
                    'customer_balance' => (string) $customer->fresh()->balance,
                    'allocations' => $existingAllocations,
                    'cash_register_affected' => $existingCashMovement !== null,
                    'cash_movement' => $existingCashMovement,
                    'entry' => $existingEntry,
                    'server_timestamp' => $existingEntry->created_at?->toIso8601String() ?? now()->toIso8601String(),
                ];
            }

            // 2. Lock customer & record payment in customer account ledger
            $entry = $this->customerAccountService->recordPayment(
                $customer,
                Money::toDecimal($amountCents),
                $user->id,
                $uuid,
                $hash,
                $notes ?: "Cobro cliente {$customer->name} ({$paymentMethod})"
            );

            // 3. FIFO Allocation to unpaid invoices (due_date ASC, issued_at ASC, id ASC)
            $unpaidInvoices = Invoice::query()
                ->where('shop_id', $shop->id)
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['pending', 'partial'])
                ->orderByRaw('COALESCE(due_date, issued_at) ASC')
                ->orderBy('issued_at', 'ASC')
                ->orderBy('id', 'ASC')
                ->lockForUpdate()
                ->get();

            $remainingToAllocateCents = $amountCents;
            $allocations = [];
            $activeCashSession = ($paymentMethod === 'cash')
                ? $this->cashRegisterService->getCurrentSession($shop, $user)
                : null;
            $cashSessionId = $activeCashSession?->id;

            foreach ($unpaidInvoices as $invoice) {
                if ($remainingToAllocateCents <= 0) {
                    break;
                }

                $invoiceTotalCents = Money::toCents($invoice->total);
                $invoicePaidCents = (int) $invoice->payments()->sum('amount_cents');
                $invoiceReturns = (float) DB::table('invoice_returns')->where('invoice_id', $invoice->id)->sum('total');
                $invoiceReturnsCents = Money::toCents($invoiceReturns);
                $invoicePendingCents = max(0, $invoiceTotalCents - $invoicePaidCents - $invoiceReturnsCents);

                if ($invoicePendingCents <= 0) {
                    if ($invoice->status !== 'paid') {
                        $invoice->status = 'paid';
                        $invoice->save();
                    }

                    continue;
                }

                $allocationCents = min($remainingToAllocateCents, $invoicePendingCents);

                $payment = InvoicePayment::create([
                    'public_id' => (string) Str::ulid(),
                    'shop_id' => $shop->id,
                    'invoice_id' => $invoice->id,
                    'customer_id' => $customer->id,
                    'customer_account_entry_id' => $entry->id,
                    'user_id' => $user->id,
                    'cash_register_session_id' => $cashSessionId,
                    'payment_method' => $paymentMethod,
                    'amount' => Money::toDecimal($allocationCents),
                    'amount_cents' => $allocationCents,
                    'reference' => $reference,
                    'notes' => $notes ?: "Abono a factura {$invoice->invoice_number}",
                    'received_at' => now(),
                    'client_operation_uuid' => (string) Str::uuid(),
                    'payload_sha256' => $hash,
                ]);

                $newPaidCents = $invoicePaidCents + $allocationCents;
                $newPendingCents = max(0, $invoiceTotalCents - $newPaidCents - $invoiceReturnsCents);
                $invoice->status = ($newPendingCents === 0) ? 'paid' : 'partial';
                $invoice->save();

                $allocations[] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'allocated_amount' => Money::toDecimal($allocationCents),
                    'allocated_cents' => $allocationCents,
                    'remaining_invoice_balance' => Money::toDecimal($newPendingCents),
                    'invoice_status' => $invoice->status,
                ];

                $remainingToAllocateCents -= $allocationCents;
            }

            // 4. If paid in cash, record ONE cash movement for the total cash collected
            $cashMovement = null;
            if ($paymentMethod === 'cash' && $activeCashSession) {
                $cashMovement = $this->cashRegisterService->recordMovement(
                    $activeCashSession,
                    $user,
                    'customer_payment',
                    Money::toDecimal($amountCents),
                    "Cobro a {$customer->name} ({$paymentMethod})",
                    'customer_account_entry',
                    $entry->id
                );
            }

            return [
                'payment_id' => $entry->id,
                'client_transaction_uuid' => $entry->client_transaction_uuid,
                'amount' => Money::toDecimal($amountCents),
                'amount_cents' => $amountCents,
                'payment_method' => $paymentMethod,
                'customer_balance' => (string) $customer->fresh()->balance,
                'allocations' => $allocations,
                'cash_register_affected' => $cashMovement !== null,
                'cash_movement' => $cashMovement,
                'entry' => $entry,
                'server_timestamp' => now()->toIso8601String(),
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
