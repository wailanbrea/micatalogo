<?php

namespace App\Services;

use App\Models\DailyClosure;
use App\Models\Shop;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DailyCloseService
{
    public function __construct(protected BusinessDashboardService $dashboard) {}

    public function calculate(Shop $shop, ?string $date = null): array
    {
        $businessDate = Carbon::parse($date ?: now()->toDateString())->toDateString();
        $summary = $this->dashboard->getSummary($shop, $businessDate, $businessDate);
        $cashFlow = $summary['cash_flow'];

        $salesCashCents = Money::toCents($cashFlow['inflows']['sales_cash'] ?? 0);
        $debtCollectionsCashCents = Money::toCents($cashFlow['inflows']['debt_collections_cash'] ?? 0);
        $otherInflowsCashCents = Money::toCents($cashFlow['inflows']['other_inflows'] ?? 0);
        $expensesCashCents = Money::toCents($cashFlow['outflows']['expenses_paid_cash'] ?? 0);
        $cashOutCents = Money::toCents($cashFlow['outflows']['cash_out'] ?? 0);
        $expectedCashCents = $salesCashCents + $debtCollectionsCashCents + $otherInflowsCashCents - $expensesCashCents - $cashOutCents;
        $closure = DailyClosure::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', $businessDate)
            ->first();

        return [
            'business_date' => $businessDate,
            'sales_cash' => $this->money($salesCashCents),
            'debt_collections_cash' => $this->money($debtCollectionsCashCents),
            'other_inflows_cash' => $this->money($otherInflowsCashCents),
            'expenses_cash' => $this->money($expensesCashCents),
            'cash_out' => $this->money($cashOutCents),
            'expected_cash' => $this->money($expectedCashCents),
            'sales_card' => (float) ($cashFlow['inflows']['sales_card'] ?? 0),
            'sales_transfer' => (float) ($cashFlow['inflows']['sales_transfer'] ?? 0),
            'sales_other' => (float) ($cashFlow['inflows']['sales_other'] ?? 0),
            'debt_collections_total' => (float) ($cashFlow['inflows']['debt_collections'] ?? 0),
            'expenses_paid_total' => (float) ($cashFlow['outflows']['expenses_paid'] ?? 0),
            'closure' => $closure ? $this->serializeClosure($closure) : null,
        ];
    }

    public function close(Shop $shop, User $user, string $date, mixed $countedCash = null, ?string $notes = null): DailyClosure
    {
        $calculation = $this->calculate($shop, $date);
        $countedCents = $countedCash === null || trim((string) $countedCash) === ''
            ? null
            : Money::toCents($countedCash);

        if ($countedCents !== null && $countedCents < 0) {
            throw new \InvalidArgumentException('El efectivo contado no puede ser negativo.', 422);
        }

        return DB::transaction(function () use ($shop, $user, $calculation, $countedCents, $notes): DailyClosure {
            $expectedCents = Money::toCents($calculation['expected_cash']);
            $closure = DailyClosure::query()->firstOrNew([
                'shop_id' => $shop->id,
                'business_date' => $calculation['business_date'],
            ]);

            if ($closure->exists
                && $closure->counted_cash_cents !== null
                && $countedCents !== null
                && $closure->counted_cash_cents !== $countedCents) {
                throw new \InvalidArgumentException('El cierre diario ya tiene un arqueo distinto registrado.', 409);
            }

            $closure->fill([
                'public_id' => $closure->public_id ?: (string) Str::ulid(),
                'user_id' => $user->id,
                'expected_cash_cents' => $expectedCents,
                'counted_cash_cents' => $countedCents,
                'difference_cents' => $countedCents === null ? null : $countedCents - $expectedCents,
                'sales_cash_cents' => Money::toCents($calculation['sales_cash']),
                'debt_collections_cash_cents' => Money::toCents($calculation['debt_collections_cash']),
                'other_inflows_cash_cents' => Money::toCents($calculation['other_inflows_cash']),
                'expenses_cash_cents' => Money::toCents($calculation['expenses_cash']),
                'cash_out_cents' => Money::toCents($calculation['cash_out']),
                'status' => 'closed',
                'notes' => $notes,
                'closed_at' => now(),
            ]);
            $closure->save();

            return $closure;
        });
    }

    public function serializeClosure(DailyClosure $closure): array
    {
        return [
            'id' => $closure->public_id,
            'business_date' => $closure->business_date->toDateString(),
            'expected_cash' => $this->money($closure->expected_cash_cents),
            'counted_cash' => $closure->counted_cash_cents === null ? null : $this->money($closure->counted_cash_cents),
            'difference' => $closure->difference_cents === null ? null : $this->money($closure->difference_cents),
            'status' => $closure->status,
            'closed_at' => $closure->closed_at?->toIso8601String(),
            'notes' => $closure->notes,
        ];
    }

    private function money(int $cents): float
    {
        return (float) Money::toDecimal($cents);
    }
}
