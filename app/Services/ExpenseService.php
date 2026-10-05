<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpensePayment;
use App\Models\Shop;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExpenseService
{
    public function __construct(
        protected CashRegisterService $cashRegisterService,
        protected PlanLimitsService $planLimitsService
    ) {}

    public function seedDefaultCategories(Shop $shop): void
    {
        $defaults = config('catalog.default_expense_categories', [
            'Alquiler', 'Electricidad', 'Internet', 'Transporte',
            'Publicidad', 'Nómina', 'Comisiones', 'Mantenimiento',
            'Materiales', 'Impuestos', 'Otros',
        ]);

        foreach ($defaults as $name) {
            ExpenseCategory::firstOrCreate(
                ['shop_id' => $shop->id, 'name' => $name],
                ['is_active' => true]
            );
        }
    }

    /**
     * Record a new operating expense.
     * Enforces:
     * - Plan Pro protection via PlanLimitsService.
     * - Separation of incurred expense (amount_cents) vs paid expense (amount_paid_cents).
     * - Idempotency via client_operation_uuid and payload_sha256.
     * - Cash register movement only for the actual amount paid in cash.
     */
    public function recordExpense(
        Shop $shop,
        User $user,
        array $data,
        ?string $clientOperationUuid = null,
        ?string $payloadHash = null
    ): Expense {
        $this->planLimitsService->assertFeature($shop->user, 'expenses');

        $clientOperationUuid = $clientOperationUuid ?? $data['client_operation_uuid'] ?? null;
        $payloadHash = $payloadHash ?? $data['payload_sha256'] ?? (! empty($clientOperationUuid) ? hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)) : null);

        if ($clientOperationUuid) {
            $existing = Expense::query()
                ->where('shop_id', $shop->id)
                ->where('client_operation_uuid', $clientOperationUuid)
                ->first();

            if ($existing) {
                if ($payloadHash && ! hash_equals((string) $existing->payload_sha256, $payloadHash)) {
                    throw new InvalidArgumentException('Conflicto de idempotencia: el identificador de operación ya fue utilizado con datos distintos.', 409);
                }

                return $existing;
            }
        }

        return DB::transaction(function () use ($shop, $user, $data, $clientOperationUuid, $payloadHash): Expense {
            $categoryId = (int) ($data['expense_category_id'] ?? 0);
            $category = ExpenseCategory::query()
                ->where('shop_id', $shop->id)
                ->where('id', $categoryId)
                ->where('is_active', true)
                ->first();

            if (! $category) {
                if (! empty($data['category_name'])) {
                    $category = ExpenseCategory::firstOrCreate(
                        ['shop_id' => $shop->id, 'name' => trim((string) $data['category_name'])],
                        ['is_active' => true]
                    );
                } else {
                    throw new InvalidArgumentException('Categoría de gasto inválida o no pertenece a esta tienda.', 422);
                }
            }

            $amountCents = Money::toCents($data['amount'] ?? 0);
            if ($amountCents <= 0) {
                throw new InvalidArgumentException('El monto total del gasto debe ser mayor a cero.', 422);
            }

            $paymentMethod = $data['payment_method'] ?? 'cash';
            $rawStatus = $data['payment_status'] ?? 'paid';

            // Support partial paid amount if explicitly provided
            if (isset($data['paid_amount'])) {
                $paidCents = Money::toCents($data['paid_amount']);
            } elseif ($rawStatus === 'paid') {
                $paidCents = $amountCents;
            } elseif ($rawStatus === 'pending') {
                $paidCents = 0;
            } else {
                $paidCents = $amountCents;
            }

            if ($paidCents < 0 || $paidCents > $amountCents) {
                throw new InvalidArgumentException('El monto pagado no puede ser negativo ni superar el total del gasto.', 422);
            }

            // Derive payment status
            $paymentStatus = match (true) {
                $paidCents === 0 => 'pending',
                $paidCents === $amountCents => 'paid',
                default => 'partial',
            };

            $activeCashSession = $this->cashRegisterService->getCurrentSession($shop, $user);
            $cashSessionId = null;

            if ($paymentMethod === 'cash' && $paidCents > 0 && $activeCashSession) {
                $cashSessionId = $activeCashSession->id;
            }

            $expense = Expense::create([
                'public_id' => (string) Str::ulid(),
                'shop_id' => $shop->id,
                'user_id' => $user->id,
                'expense_category_id' => $category->id,
                'cash_register_session_id' => $cashSessionId,
                'description' => trim((string) ($data['description'] ?? 'Gasto operativo')),
                'amount' => Money::toDecimal($amountCents),
                'amount_cents' => $amountCents,
                'amount_paid' => Money::toDecimal($paidCents),
                'amount_paid_cents' => $paidCents,
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentMethod,
                'reference' => $data['reference'] ?? null,
                'occurred_at' => ! empty($data['occurred_at']) ? $data['occurred_at'] : now(),
                'notes' => $data['notes'] ?? null,
                'client_operation_uuid' => $clientOperationUuid,
                'payload_sha256' => $payloadHash,
            ]);

            // If an initial payment was made, record in expense_payments
            if ($paidCents > 0) {
                $expensePayment = ExpensePayment::create([
                    'public_id' => (string) Str::ulid(),
                    'shop_id' => $shop->id,
                    'expense_id' => $expense->id,
                    'user_id' => $user->id,
                    'cash_register_session_id' => $cashSessionId,
                    'payment_method' => $paymentMethod,
                    'amount' => Money::toDecimal($paidCents),
                    'amount_cents' => $paidCents,
                    'reference' => $data['reference'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'paid_at' => ! empty($data['occurred_at']) ? $data['occurred_at'] : now(),
                    'client_operation_uuid' => $clientOperationUuid,
                    'payload_sha256' => $payloadHash,
                ]);

                // Record cash movement if paid in cash with active cash register session
                // Rule 17: ONLY for the paid portion, NOT the total obligation!
                if ($paymentMethod === 'cash' && $activeCashSession) {
                    $this->cashRegisterService->recordMovement(
                        $activeCashSession,
                        $user,
                        'expense',
                        Money::toDecimal($paidCents),
                        "Gasto: {$expense->description}",
                        'expense_payment',
                        $expensePayment->id
                    );
                }
            }

            return $expense;
        });
    }

    /**
     * Record a subsequent payment towards an existing unpaid or partial expense.
     */
    public function recordExpensePayment(
        Shop $shop,
        Expense $expense,
        User $user,
        array $data,
        ?string $clientOperationUuid = null,
        ?string $payloadHash = null
    ): ExpensePayment {
        $this->planLimitsService->assertFeature($shop->user, 'expenses');

        $clientOperationUuid = $clientOperationUuid ?? $data['client_operation_uuid'] ?? null;
        $payloadHash = $payloadHash ?? $data['payload_sha256'] ?? (! empty($clientOperationUuid) ? hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)) : null);

        if ($clientOperationUuid) {
            $existing = ExpensePayment::query()
                ->where('shop_id', $shop->id)
                ->where('client_operation_uuid', $clientOperationUuid)
                ->first();

            if ($existing) {
                if ($payloadHash && ! hash_equals((string) $existing->payload_sha256, $payloadHash)) {
                    throw new InvalidArgumentException('Conflicto de idempotencia: el identificador de operación ya fue utilizado con datos distintos.', 409);
                }

                return $existing;
            }
        }

        return DB::transaction(function () use ($shop, $expense, $user, $data, $clientOperationUuid, $payloadHash): ExpensePayment {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);

            $paymentCents = Money::toCents($data['amount'] ?? 0);
            if ($paymentCents <= 0) {
                throw new InvalidArgumentException('El monto a abonar debe ser mayor a cero.', 422);
            }

            $unpaidCents = $expense->unpaidAmountCents();
            if ($paymentCents > $unpaidCents) {
                $maxFormatted = Money::toDecimal($unpaidCents);
                throw new InvalidArgumentException("El pago no puede superar el saldo pendiente del gasto (RD\${$maxFormatted}).", 422);
            }

            $paymentMethod = $data['payment_method'] ?? 'cash';
            $activeCashSession = $this->cashRegisterService->getCurrentSession($shop, $user);
            $cashSessionId = ($paymentMethod === 'cash' && $activeCashSession) ? $activeCashSession->id : null;

            $expensePayment = ExpensePayment::create([
                'public_id' => (string) Str::ulid(),
                'shop_id' => $shop->id,
                'expense_id' => $expense->id,
                'user_id' => $user->id,
                'cash_register_session_id' => $cashSessionId,
                'payment_method' => $paymentMethod,
                'amount' => Money::toDecimal($paymentCents),
                'amount_cents' => $paymentCents,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => ! empty($data['paid_at']) ? $data['paid_at'] : now(),
                'client_operation_uuid' => $clientOperationUuid,
                'payload_sha256' => $payloadHash,
            ]);

            // Update parent expense
            $newPaidCents = $expense->amount_paid_cents + $paymentCents;
            $expense->amount_paid_cents = $newPaidCents;
            $expense->amount_paid = Money::toDecimal($newPaidCents);
            $expense->payment_status = ($newPaidCents >= $expense->amount_cents) ? 'paid' : 'partial';
            $expense->save();

            // Record cash movement if cash
            if ($paymentMethod === 'cash' && $activeCashSession) {
                $this->cashRegisterService->recordMovement(
                    $activeCashSession,
                    $user,
                    'expense',
                    Money::toDecimal($paymentCents),
                    "Pago gasto: {$expense->description}",
                    'expense_payment',
                    $expensePayment->id
                );
            }

            return $expensePayment;
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
