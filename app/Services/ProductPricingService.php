<?php

namespace App\Services;

use App\Enums\UserPlan;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductPricingService
{
    public function requirePro(Product $product): void
    {
        if (app(PlanLimitsService::class)->planFor($product->shop->user) !== UserPlan::Pro) {
            throw ValidationException::withMessages(['pricing' => 'Las reglas automáticas de precio están incluidas en Pro.']);
        }
    }

    public function propose(Product $product, string|int|float $unitCost, ?int $userId): void
    {
        if (app(PlanLimitsService::class)->planFor($product->shop->user) !== UserPlan::Pro) {
            return;
        }
        $rule = DB::table('product_price_rules')->where('product_id', $product->id)->lockForUpdate()->first();
        if (! $rule) {
            return;
        }
        $unitCostCents = Money::toCents($unitCost);
        // margin_percent is a percentage represented as basis points (40% = 4000).
        // Keep the pricing mutation entirely in integer cents/basis points.
        $marginBps = Money::toCents($rule->margin_percent);
        $denominator = 10000 - $marginBps;
        $numerator = $unitCostCents * 10000;
        $rawCents = intdiv($numerator + $denominator - 1, $denominator);
        $step = (int) $rule->round_step_cents;
        $priceCents = intdiv($rawCents + $step - 1, $step) * $step;
        $pending = null;
        $currentPriceCents = Money::toCents($product->price);
        if ($priceCents > $currentPriceCents && $rule->auto_increase) {
            $this->apply($product, Money::toDecimal($priceCents), $userId, 'receipt_increase');
        } elseif ($priceCents !== $currentPriceCents) {
            $pending = Money::toDecimal($priceCents);
        }
        DB::table('product_price_rules')->where('id', $rule->id)->update(['pending_price' => $pending, 'updated_at' => now()]);
    }

    public function apply(Product $product, string|int|float $price, ?int $userId, string $reason): void
    {
        $priceDecimal = Money::toDecimal(Money::toCents($price));
        DB::table('product_price_changes')->insert([
            'product_id' => $product->id, 'user_id' => $userId, 'old_price' => $product->price,
            'new_price' => $priceDecimal, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $product->update(['price' => $priceDecimal]);
    }
}
