<?php

namespace App\Services;

use App\Models\DailyClosure;
use App\Models\DailyClosureAdjustment;
use App\Models\Shop;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            'adjustments' => $calculation['closure'] && Schema::hasTable('daily_closure_adjustments')
                ? DailyClosureAdjustment::where('shop_id', $shop->id)->where('daily_closure_id', $calculation['closure']->id)
                    ->with(['user:id,name', 'previous:id,public_id'])->orderByDesc('id')->limit(50)->get()
                    ->map(fn ($row) => $this->serializeAdjustment($row, $calculation['closure']))->all() : [],
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
            $businessDate = Carbon::parse($date)->toDateString();
            $closure = DailyClosure::query()
                ->where('shop_id', $shop->id)
                ->whereDate('business_date', $businessDate)
                ->first();

            if ($closure) {
                if ($countedCents !== null && $closure->counted_cash_cents !== $countedCents) {
                    throw new \InvalidArgumentException('El cierre diario ya tiene un arqueo distinto registrado.', 409);
                }
                // A retry returns the immutable snapshot, including its original
                // author, notes, date and optional count. It must not recalculate.
                return $closure;
            }

            $calculation = $this->calculateCents($shop, $businessDate);
            $expectedCents = $calculation['expected_cash_cents'];
            $closure = new DailyClosure(['shop_id' => $shop->id, 'business_date' => $businessDate]);
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

    public function canAdjust(Shop $shop, User $user): bool
    {
        return $user->canSellAtShop($shop) && ($user->ownsShop($shop) || $user->isActiveShopManager($shop))
            && in_array('day_close', app(SellerMenuService::class)->visibleForUser($shop, $user), true);
    }

    public function adjust(Shop $shop, User $user, string $closureId, string $uuid, mixed $countedCash, string $reason): DailyClosureAdjustment
    {
        abort_unless($this->canAdjust($shop, $user), 403);
        abort_unless(Schema::hasTable('daily_closure_adjustments'), 503, 'El registro de ajustes de conciliación todavía no está habilitado.');
        $counted = $countedCash === null || trim((string) $countedCash) === '' ? null : Money::toCents($countedCash);
        $reason = trim($reason);
        if (($counted !== null && $counted < 0) || $reason === '' || mb_strlen($reason) > 1000 || ! Str::isUuid($uuid)) {
            throw new \InvalidArgumentException('Indica un motivo válido, un UUID y un arqueo no negativo.', 422);
        }
        $hash = hash('sha256', json_encode([$closureId, $counted, $reason], JSON_THROW_ON_ERROR));
        return DB::transaction(function () use ($shop, $user, $closureId, $uuid, $counted, $reason, $hash) {
            $lockedShop = Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            $closure = DailyClosure::where('shop_id', $lockedShop->id)->where('public_id', $closureId)->firstOrFail();
            $old = DailyClosureAdjustment::where('shop_id', $lockedShop->id)->where('client_operation_uuid', $uuid)->first();
            if ($old) {
                abort_unless((string) $old->user_id === (string) $user->id, 403);
                if (! hash_equals($old->payload_sha256, $hash)) throw new \InvalidArgumentException('El UUID del ajuste ya confirma otro contenido.', 409);
                return $old;
            }
            $snapshot = $this->calculateCents($lockedShop, $closure->business_date->toDateString());
            $expected = $snapshot['expected_cash_cents'];
            return DailyClosureAdjustment::create(['shop_id' => $lockedShop->id, 'daily_closure_id' => $closure->id,
                'user_id' => $user->id, 'previous_adjustment_id' => DailyClosureAdjustment::where('daily_closure_id', $closure->id)->max('id'),
                'client_operation_uuid' => $uuid, 'payload_sha256' => $hash, 'reason' => $reason,
                'expected_cash_cents' => $expected, 'counted_cash_cents' => $counted,
                'difference_cents' => $counted === null ? null : $counted - $expected,
                'snapshot' => array_intersect_key($snapshot, array_flip(['sales_cash_cents', 'debt_collections_cash_cents', 'other_inflows_cash_cents', 'expenses_cash_cents', 'cash_out_cents'])),
                'created_at' => now()]);
        });
    }

    public function serializeAdjustment(DailyClosureAdjustment $adjustment, DailyClosure $closure): array
    {
        return ['id' => $adjustment->public_id, 'closure_id' => $closure->public_id, 'client_operation_uuid' => $adjustment->client_operation_uuid,
            'user_id' => (string) $adjustment->user_id,
            'user_name' => $adjustment->user?->name, 'reason' => $adjustment->reason, 'created_at' => $adjustment->created_at->toISOString(),
            'previous_id' => $adjustment->previous?->public_id,
            'expected_cash' => Money::toDecimal($adjustment->expected_cash_cents),
            'counted_cash' => $adjustment->counted_cash_cents === null ? null : Money::toDecimal($adjustment->counted_cash_cents),
            'difference' => $adjustment->difference_cents === null ? null : Money::toDecimal($adjustment->difference_cents),
            'change_from_original' => Money::toDecimal($adjustment->expected_cash_cents - $closure->expected_cash_cents)];
    }

    private function money(int $cents): float
    {
        return (float) Money::toDecimal($cents);
    }
}
