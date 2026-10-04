<?php

namespace App\Services;

use App\Enums\UserPlan;
use App\Models\Product;
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

    public function propose(Product $product, float $unitCost, ?int $userId): void
    {
        if (app(PlanLimitsService::class)->planFor($product->shop->user) !== UserPlan::Pro) {
            return;
        }
        $rule = DB::table('product_price_rules')->where('product_id', $product->id)->lockForUpdate()->first();
        if (! $rule) {
            return;
        }
        $denominator = 10000 - (int) round((float) $rule->margin_percent * 100);
        $numerator = (int) round($unitCost * 100) * 10000;
        $rawCents = intdiv($numerator + $denominator - 1, $denominator);
        $step = (int) $rule->round_step_cents;
        $price = (intdiv($rawCents + $step - 1, $step) * $step) / 100;
        $pending = null;
        if ($price > (float) $product->price && $rule->auto_increase) {
            $this->apply($product, $price, $userId, 'receipt_increase');
        } elseif ($price !== (float) $product->price) {
            $pending = $price;
        }
        DB::table('product_price_rules')->where('id', $rule->id)->update(['pending_price' => $pending, 'updated_at' => now()]);
    }

    public function apply(Product $product, float $price, ?int $userId, string $reason): void
    {
        DB::table('product_price_changes')->insert([
            'product_id' => $product->id, 'user_id' => $userId, 'old_price' => $product->price,
            'new_price' => $price, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $product->update(['price' => $price]);
    }
}
