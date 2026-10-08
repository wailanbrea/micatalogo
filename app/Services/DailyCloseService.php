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
        $calculation = $this->calculateCents($shop, $date);

        return [
            'business_date' => $calculation['business_date'],
            'sales_cash' => $this->money($calculation['sales_cash_cents']),
            'debt_collections_cash' => $this->money($calculation['debt_collections_cash_cents']),
            'other_inflows_cash' => $this->money($calculation['other_inflows_cash_cents']),
            'expenses_cash' => $this->money($calculation['expenses_cash_cents']),
            'cash_out' => $this->money($calculation['cash_out_cents']),
            'expected_cash' => $this->money($calculation['expected_cash_cents']),
            'sales_card' => (float) ($calculation['sales_card'] ?? 0),
            'sales_transfer' => (float) ($calculation['sales_transfer'] ?? 0),
            'sales_other' => (float) ($calculation['sales_other'] ?? 0),
            'debt_collections_total' => (float) ($calculation['debt_collections_total'] ?? 0),
            'expenses_paid_total' => (float) ($calculation['expenses_paid_total'] ?? 0),
            'closure' => $calculation['closure'] ? $this->serializeClosure($calculation['closure']) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function calculateCents(Shop $shop, ?string $date = null): array
    {
        $businessDate = Carbon::parse($date ?: now()->toDateString())->toDateString();
        $summary = $this->dashboard->getSummary($shop, $businessDate, $businessDate);
        $cashFlow = $summary['cash_flow'];

        $salesCashCents = (int) ($cashFlow['inflows']['sales_cash_cents'] ?? Money::toCents($cashFlow['inflows']['sales_cash'] ?? 0));
        $debtCollectionsCashCents = (int) ($cashFlow['inflows']['debt_collections_cash_cents'] ?? Money::toCents($cashFlow['inflows']['debt_collections_cash'] ?? 0));
        $otherInflowsCashCents = (int) ($cashFlow['inflows']['other_inflows_cents'] ?? Money::toCents($cashFlow['inflows']['other_inflows'] ?? 0));
        $expensesCashCents = (int) ($cashFlow['outflows']['expenses_paid_cash_cents'] ?? Money::toCents($cashFlow['outflows']['expenses_paid_cash'] ?? 0));
        $cashOutCents = (int) ($cashFlow['outflows']['cash_out_cents'] ?? Money::toCents($cashFlow['outflows']['cash_out'] ?? 0));
        $expectedCashCents = $salesCashCents + $debtCollectionsCashCents + $otherInflowsCashCents - $expensesCashCents - $cashOutCents;
        $closure = DailyClosure::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', $businessDate)
            ->first();

        return [
            'business_date' => $businessDate,
            'sales_cash_cents' => $salesCashCents,
            'debt_collections_cash_cents' => $debtCollectionsCashCents,
            'other_inflows_cash_cents' => $otherInflowsCashCents,
            'expenses_cash_cents' => $expensesCashCents,
            'cash_out_cents' => $cashOutCents,
            'expected_cash_cents' => $expectedCashCents,
            'sales_card' => $cashFlow['inflows']['sales_card'] ?? 0,
            'sales_transfer' => $cashFlow['inflows']['sales_transfer'] ?? 0,
            'sales_other' => $cashFlow['inflows']['sales_other'] ?? 0,
            'debt_collections_total' => $cashFlow['inflows']['debt_collections'] ?? 0,
            'expenses_paid_total' => $cashFlow['outflows']['expenses_paid'] ?? 0,
            'closure' => $closure,
        ];
    }

    public function close(Shop $shop, User $user, string $date, mixed $countedCash = null, ?string $notes = null): DailyClosure
    {
        $countedCents = $countedCash === null || trim((string) $countedCash) === ''
            ? null
            : Money::toCents($countedCash);

        if ($countedCents !== null && $countedCents < 0) {
            throw new \InvalidArgumentException('El efectivo contado no puede ser negativo.', 422);
        }

        return DB::transaction(function () use ($shop, $user, $date, $countedCents, $notes): DailyClosure {
            // Serialize closures for the same shop before firstOrNew. The unique
            // (shop_id, business_date) index protects integrity, but without a
            // shared lock concurrent close requests can still race into a 1062.
            $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            $calculation = $this->calculateCents($shop, $date);
            $expectedCents = $calculation['expected_cash_cents'];
            $closure = DailyClosure::query()
                ->where('shop_id', $shop->id)
                ->whereDate('business_date', $calculation['business_date'])
                ->first();

            if (! $closure) {
                $closure = new DailyClosure([
                    'shop_id' => $shop->id,
                    'business_date' => $calculation['business_date'],
                ]);
            }

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
                'sales_cash_cents' => $calculation['sales_cash_cents'],
                'debt_collections_cash_cents' => $calculation['debt_collections_cash_cents'],
                'other_inflows_cash_cents' => $calculation['other_inflows_cash_cents'],
                'expenses_cash_cents' => $calculation['expenses_cash_cents'],
                'cash_out_cents' => $calculation['cash_out_cents'],
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
