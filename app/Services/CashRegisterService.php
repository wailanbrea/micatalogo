<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CashRegisterService
{
    public function getCurrentSession(Shop $shop, User $user): ?CashRegisterSession
    {
        return CashRegisterSession::query()
            ->where('shop_id', $shop->id)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->first();
    }

    public function openSession(Shop $shop, User $user, mixed $openingAmount = 0, ?string $notes = null): CashRegisterSession
    {
        return DB::transaction(function () use ($shop, $user, $openingAmount, $notes): CashRegisterSession {
            $existing = CashRegisterSession::query()
                ->where('shop_id', $shop->id)
                ->where('user_id', $user->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new InvalidArgumentException('Ya tienes una sesión de caja abierta en esta tienda.', 409);
            }

            $openingCents = $this->toCents($openingAmount);
            if ($openingCents < 0) {
                throw new InvalidArgumentException('El fondo inicial de caja no puede ser negativo.');
            }

            return CashRegisterSession::create([
                'public_id' => (string) Str::ulid(),
                'shop_id' => $shop->id,
                'user_id' => $user->id,
                'opened_at' => now(),
                'opening_amount' => $this->toDecimal($openingCents),
                'opening_amount_cents' => $openingCents,
                'status' => 'open',
                'notes' => $notes,
            ]);
        });
    }

    public function closeSession(CashRegisterSession $session, User $user, mixed $countedAmount, ?string $notes = null): CashRegisterSession
    {
        return DB::transaction(function () use ($session, $user, $countedAmount, $notes): CashRegisterSession {
            $session = CashRegisterSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($session->status === 'closed') {
                throw new InvalidArgumentException('Esta sesión de caja ya se encuentra cerrada.');
            }

            $countedCents = $this->toCents($countedAmount);
            if ($countedCents < 0) {
                throw new InvalidArgumentException('El monto contado no puede ser negativo.');
            }

            $expectedCents = $session->calculateExpectedBalance();
            $differenceCents = $countedCents - $expectedCents;

            $session->closed_at = now();
            $session->expected_closing_amount = $this->toDecimal($expectedCents);
            $session->expected_closing_amount_cents = $expectedCents;
            $session->counted_closing_amount = $this->toDecimal($countedCents);
            $session->counted_closing_amount_cents = $countedCents;
            $session->difference = $this->toDecimal($differenceCents);
            $session->difference_cents = $differenceCents;
            $session->status = 'closed';
            if ($notes !== null) {
                $session->notes = $notes;
            }
            $session->save();

            return $session;
        });
    }

    public function recordMovement(
        CashRegisterSession $session,
        User $user,
        string $type,
        mixed $amount,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): CashMovement {
        return DB::transaction(function () use ($session, $user, $type, $amount, $notes, $referenceType, $referenceId): CashMovement {
            $session = CashRegisterSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($session->status === 'closed') {
                throw new InvalidArgumentException('No se pueden registrar movimientos en una sesión de caja cerrada.', 422);
            }

            $rawCents = $this->toCents($amount);
            if ($rawCents === 0) {
                throw new InvalidArgumentException('El monto del movimiento debe ser distinto de cero.');
            }

            $allowedTypes = [
                'sale', 'customer_payment', 'expense', 'supplier_payment',
                'cash_in', 'cash_out', 'owner_contribution', 'owner_withdrawal', 'adjustment',
            ];

            if (! in_array($type, $allowedTypes, true)) {
                throw new InvalidArgumentException("Tipo de movimiento de caja no reconocido: {$type}.");
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
                'amount' => $this->toDecimal($signedCents),
                'amount_cents' => $signedCents,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'occurred_at' => now(),
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
            'sales_cash' => (float) $this->toDecimal($salesCashCents),
            'collections_cash' => (float) $this->toDecimal($collectionsCashCents),
            'cash_in' => (float) $this->toDecimal($cashInCents),
            'owner_contributions' => (float) $this->toDecimal($ownerContribCents),
            'expenses_cash' => (float) $this->toDecimal(abs($expensesCashCents)),
            'supplier_payments' => (float) $this->toDecimal(abs($supplierPayCents)),
            'cash_out' => (float) $this->toDecimal(abs($cashOutCents)),
            'owner_withdrawals' => (float) $this->toDecimal(abs($ownerWithdrCents)),
            'adjustments' => (float) $this->toDecimal($adjustmentsCents),
            'total_in' => (float) $this->toDecimal($totalInCents),
            'total_out' => (float) $this->toDecimal($totalOutCents),
            'expected_amount' => (float) $this->toDecimal($expectedCents),
            'counted_amount' => $session->counted_closing_amount !== null ? (float) $session->counted_closing_amount : null,
            'difference' => $session->difference !== null ? (float) $session->difference : null,
            'status' => $session->status,
        ];
    }

    public function toCents(mixed $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round((float) $amount * 100);
    }

    public function toDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
