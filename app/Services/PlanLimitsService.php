<?php

namespace App\Services;

use App\Enums\UserPlan;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PlanLimitsService
{
    public function planFor(User $user): UserPlan
    {
        $plan = $user->plan instanceof UserPlan ? $user->plan : UserPlan::tryFrom((string) $user->plan);

        if ($plan === UserPlan::Premium && $user->plan_expires_at?->isPast()) {
            return UserPlan::Free;
        }

        return $plan ?? UserPlan::Free;
    }

    public function limitsFor(User $user): array
    {
        $plan = $this->planFor($user);
        $limits = config('catalog.plans.'.$plan->value, config('catalog.plans.free'));

        if ($plan === UserPlan::Free) {
            $limits = array_replace($limits, config('catalog.free', []));
        }

        return $limits;
    }

    public function activeShopLimit(User $user): int
    {
        return (int) $this->limitsFor($user)['max_active_shops'];
    }

    public function productLimit(Shop $shop): int
    {
        if ($shop->product_limit !== null) {
            return (int) $shop->product_limit;
        }

        return (int) $this->limitsFor($shop->user)['max_products_per_shop'];
    }

    public function imageLimit(Shop $shop): int
    {
        return (int) $this->limitsFor($shop->user)['max_images_per_product'];
    }

    public function shopQuota(Shop $shop, ?int $productCount = null): array
    {
        $count = $productCount ?? $shop->products()->count();
        $limit = $this->productLimit($shop);
        $plan = $this->planFor($shop->user);

        return [
            'plan' => $plan->value,
            'plan_label' => $plan->label(),
            'product_count' => $count,
            'product_limit' => $limit,
            'products_remaining' => max(0, $limit - $count),
            'image_limit' => $this->imageLimit($shop),
            'can_add_products' => $count < $limit,
        ];
    }

    public function assertCanAddProducts(Shop $shop, int $newProducts, string $field = 'products'): void
    {
        $currentProducts = $shop->products()->count();
        $limit = $this->productLimit($shop);

        if ($currentProducts + $newProducts > $limit) {
            throw ValidationException::withMessages([
                $field => "La tienda tiene {$currentProducts} productos y el plan {$this->planFor($shop->user)->label()} permite {$limit}.",
            ]);
        }
    }
}
