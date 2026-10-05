<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Shop;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CashRegisterService
{
    public const MANUAL_MOVEMENT_TYPES = [
        'cash_in', 'cash_out', 'owner_contribution', 'owner_withdrawal', 'adjustment',
    ];

    public function getCurrentSession(Shop $shop, User $user): ?CashRegisterSession
    {
        return CashRegisterSession::query()
            ->where('shop_id', $shop->id)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->first();
    }

    public function canManageAllCashRegisters(Shop $shop, User $user): bool
    {
        return $user->isAdmin() || $user->ownsShop($shop) || $user->isActiveShopMember($shop);
    }

    public function canManageSession(Shop $shop, User $user, CashRegisterSession $session): bool
    {
        if ($session->shop_id !== $shop->id) {
            return false;
        }

        if ($session->user_id === $user->id) {
            return true;
        }

        return $this->canManageAllCashRegisters($shop, $user);
    }

    public function openSession(
        Shop $shop,
        User $user,
        mixed $openingAmount = 0,
        ?string $notes = null,
        ?string $clientOperationUuid = null,
        ?string $payloadHash = null
    ): CashRegisterSession {
        // Check idempotency first
        if ($clientOperationUuid) {
            $existingByIdp = CashRegisterSession::query()
                ->where('shop_id', $shop->id)
                ->where('user_id', $user->id)
                ->where('client_operation_uuid', $clientOperationUuid)
                ->first();

            if ($existingByIdp) {
                if ($payloadHash && ! hash_equals((string) $existingByIdp->payload_sha256, $payloadHash)) {
                    throw new InvalidArgumentException('Conflicto de idempotencia: el identificador de operación ya fue utilizado con datos distintos.', 409);
                }

                return $existingByIdp;
            }
        }

        return DB::transaction(function () use ($shop, $user, $openingAmount, $notes, $clientOperationUuid, $payloadHash): CashRegisterSession {
            $existing = CashRegisterSession::query()
                ->where('shop_id', $shop->id)
                ->where('user_id', $user->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new InvalidArgumentException('Ya tienes una sesión de caja abierta en esta tienda.', 409);
            }

            $openingCents = Money::toCents($openingAmount);
            if ($openingCents < 0) {
                throw new InvalidArgumentException('El fondo inicial de caja no puede ser negativo.', 422);
            }

            try {
                return CashRegisterSession::create([
                    'public_id' => (string) Str::ulid(),
                    'shop_id' => $shop->id,
                    'user_id' => $user->id,
                    'opened_at' => now(),
                    'opening_amount' => Money::toDecimal($openingCents),
                    'opening_amount_cents' => $openingCents,
                    'status' => 'open',
                    'is_open_flag' => 1,
                    'notes' => $notes,
                    'client_operation_uuid' => $clientOperationUuid,
                    'payload_sha256' => $payloadHash,
                ]);
            } catch (QueryException $e) {
                // If unique constraint unique_open_cash_session_per_user triggers
                if (str_contains($e->getMessage(), 'unique_open_cash_session_per_user') || str_contains($e->getMessage(), 'Duplicate entry')) {
                    throw new InvalidArgumentException('Ya tienes una sesión de caja abierta en esta tienda.', 409);
                }
                throw $e;
            }
        });
    }

    public function closeSession(
        CashRegisterSession $session,
        User $user,
        mixed $countedAmount,
        ?string $notes = null,
        ?string $clientOperationUuid = null,
        ?string $payloadHash = null
    ): CashRegisterSession {
        if (! $this->canManageSession($session->shop, $user, $session)) {
            throw new AuthorizationException('No tienes permiso para cerrar la sesión de caja de otro usuario.', 403);
        }

        $countedCents = Money::toCents($countedAmount);
        if ($countedCents < 0) {
            throw new InvalidArgumentException('El monto contado no puede ser negativo.', 422);
        }

        // Idempotent retry: return already closed session
        if ($session->status === 'closed') {
            if ($payloadHash && $session->payload_sha256 && ! hash_equals((string) $session->payload_sha256, $payloadHash)) {
                throw new InvalidArgumentException('Conflicto de idempotencia: la sesión de caja ya fue cerrada con datos distintos.', 409);
            }
            if ($session->counted_closing_amount_cents !== null && (int) $session->counted_closing_amount_cents !== $countedCents) {
                throw new InvalidArgumentException('Conflicto: la sesión de caja ya se encuentra cerrada con un monto de arqueo distinto.', 409);
            }

            return $session;
        }

        return DB::transaction(function () use ($session, $countedCents, $notes, $clientOperationUuid, $payloadHash): CashRegisterSession {
            $session = CashRegisterSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($session->status === 'closed') {
                if ($payloadHash && $session->payload_sha256 && ! hash_equals((string) $session->payload_sha256, $payloadHash)) {
                    throw new InvalidArgumentException('Conflicto de idempotencia: la sesión de caja ya fue cerrada con datos distintos.', 409);
                }
                if ($session->counted_closing_amount_cents !== null && (int) $session->counted_closing_amount_cents !== $countedCents) {
                    throw new InvalidArgumentException('Conflicto: la sesión de caja ya se encuentra cerrada con un monto de arqueo distinto.', 409);
                }

                return $session;
            }

            $expectedCents = $session->calculateExpectedBalance();
            $differenceCents = $countedCents - $expectedCents;

            $session->closed_at = now();
            $session->expected_closing_amount = Money::toDecimal($expectedCents);
            $session->expected_closing_amount_cents = $expectedCents;
            $session->counted_closing_amount = Money::toDecimal($countedCents);
            $session->counted_closing_amount_cents = $countedCents;
            $session->difference = Money::toDecimal($differenceCents);
            $session->difference_cents = $differenceCents;
            $session->status = 'closed';
            $session->is_open_flag = null; // Release concurrency lock
            if ($notes !== null) {
                $session->notes = $notes;
            }
            if ($clientOperationUuid) {
                $session->client_operation_uuid = $clientOperationUuid;
                $session->payload_sha256 = $payloadHash;
            }
            $session->save();

            return $session;
        });
    }

    public function recordManualMovement(
        CashRegisterSession $session,
        User $user,
        string $type,
        mixed $amount,
        string $notes,
        ?string $clientOperationUuid = null,
        ?string $payloadHash = null
    ): CashMovement {
        if (! in_array($type, self::MANUAL_MOVEMENT_TYPES, true)) {
            throw new InvalidArgumentException("Tipo de movimiento manual no permitido: {$type}. Las ventas, gastos y cobros deben originarse desde sus módulos respectivos.", 422);
        }

        return $this->recordMovement(
            $session,
            $user,
            $type,
            $amount,
            $notes,
            null,
            null,
            $clientOperationUuid,
            $payloadHash
        );
    }

    public function recordMovement(
        CashRegisterSession $session,
        User $user,
        string $type,
        mixed $amount,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $clientOperationUuid = null,
        ?string $payloadHash = null
    ): CashMovement {
        if (! $this->canManageSession($session->shop, $user, $session)) {
            throw new AuthorizationException('No tienes permiso para registrar movimientos en la sesión de caja de otro usuario.', 403);
        }

        // Check idempotency if clientOperationUuid provided
        if ($clientOperationUuid) {
            $existing = CashMovement::query()
                ->where('shop_id', $session->shop_id)
                ->where('client_operation_uuid', $clientOperationUuid)
                ->first();

            if ($existing) {
                if ($payloadHash && ! hash_equals((string) $existing->payload_sha256, $payloadHash)) {
                    throw new InvalidArgumentException('Conflicto de idempotencia: el identificador de operación ya fue utilizado con datos distintos.', 409);
                }

                return $existing;
            }
        }

        return DB::transaction(function () use ($session, $user, $type, $amount, $notes, $referenceType, $referenceId, $clientOperationUuid, $payloadHash): CashMovement {
            $session = CashRegisterSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($session->status === 'closed') {
                throw new InvalidArgumentException('No se pueden registrar movimientos en una sesión de caja cerrada.', 422);
            }

            $rawCents = Money::toCents($amount);
            if ($rawCents === 0) {
                throw new InvalidArgumentException('El monto del movimiento debe ser distinto de cero.', 422);
            }

            $allowedTypes = [
                'sale', 'customer_payment', 'expense', 'supplier_payment',
                'cash_in', 'cash_out', 'owner_contribution', 'owner_withdrawal', 'adjustment',
            ];

            if (! in_array($type, $allowedTypes, true)) {
                throw new InvalidArgumentException("Tipo de movimiento de caja no reconocido: {$type}.", 422);
            }

            $signedCents = match ($type) {
                'expense', 'supplier_payment', 'cash_out', 'owner_withdrawal' => -abs($rawCents),
                'sale', 'customer_payment', 'cash_in', 'owner_contribution' => abs($rawCents),
                'adjustment' => $rawCents,
                default => $rawCents,
            };

            return CashMovement::create([
                'public_id' => (string) Str::ulid(),
                'cash_register_session_id' => $session->id,
                'shop_id' => $session->shop_id,
                'user_id' => $user->id,
                'type' => $type,
                'amount' => Money::toDecimal($signedCents),
                'amount_cents' => $signedCents,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'occurred_at' => now(),
                'client_operation_uuid' => $clientOperationUuid,
                'payload_sha256' => $payloadHash,
            ]);
        });
    }

    public function getSessionSummary(CashRegisterSession $session): array
    {
        $movements = $session->movements;

        $salesCashCents = (int) $movements->where('type', 'sale')->sum('amount_cents');
        $collectionsCashCents = (int) $movements->where('type', 'customer_payment')->sum('amount_cents');
        $cashInCents = (int) $movements->where('type', 'cash_in')->sum('amount_cents');
        $ownerContribCents = (int) $movements->where('type', 'owner_contribution')->sum('amount_cents');

        $expensesCashCents = (int) $movements->where('type', 'expense')->sum('amount_cents');
        $supplierPayCents = (int) $movements->where('type', 'supplier_payment')->sum('amount_cents');
        $cashOutCents = (int) $movements->where('type', 'cash_out')->sum('amount_cents');
        $ownerWithdrCents = (int) $movements->where('type', 'owner_withdrawal')->sum('amount_cents');
        $adjustmentsCents = (int) $movements->where('type', 'adjustment')->sum('amount_cents');

        $totalInCents = $salesCashCents + $collectionsCashCents + $cashInCents + $ownerContribCents;
        $totalOutCents = abs($expensesCashCents) + abs($supplierPayCents) + abs($cashOutCents) + abs($ownerWithdrCents);

        $expectedCents = $session->opening_amount_cents + (int) $movements->sum('amount_cents');

        return [
            'opening_amount' => (float) $session->opening_amount,
            'sales_cash' => (float) Money::toDecimal($salesCashCents),
            'collections_cash' => (float) Money::toDecimal($collectionsCashCents),
            'debt_collections_cash' => (float) Money::toDecimal($collectionsCashCents),
            'cash_in' => (float) Money::toDecimal($cashInCents),
            'owner_contributions' => (float) Money::toDecimal($ownerContribCents),
            'owner_contribution' => (float) Money::toDecimal($ownerContribCents), // Android <= 1.0.22
            'expenses_cash' => (float) Money::toDecimal(abs($expensesCashCents)),
            'supplier_payments' => (float) Money::toDecimal(abs($supplierPayCents)),
            'cash_out' => (float) Money::toDecimal(abs($cashOutCents)),
            'owner_withdrawals' => (float) Money::toDecimal(abs($ownerWithdrCents)),
            'owner_withdrawal' => (float) Money::toDecimal(abs($ownerWithdrCents)), // Android <= 1.0.22
            'adjustments' => (float) Money::toDecimal($adjustmentsCents),
            'total_in' => (float) Money::toDecimal($totalInCents),
            'total_out' => (float) Money::toDecimal($totalOutCents),
            'expected_amount' => (float) Money::toDecimal($expectedCents),
            'expected_closing_amount' => (float) Money::toDecimal($expectedCents),
            'movements_count' => $movements->count(),
            'counted_amount' => $session->counted_closing_amount !== null ? (float) $session->counted_closing_amount : null,
            'difference' => $session->difference !== null ? (float) $session->difference : null,
            'status' => $session->status,
        ];
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
