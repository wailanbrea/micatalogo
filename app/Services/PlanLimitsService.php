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

        if ($plan !== UserPlan::Free && $user->plan_expires_at?->isPast()) {
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

        if ($plan === UserPlan::Custom) {
            $limits['features'] = array_values(array_unique(array_merge(
                config('catalog.plans.pro.features', []),
                $limits['features'] ?? [],
            )));
        }

        return $limits;
    }

    public function activeShopLimit(User $user): int
    {
        return (int) $this->limitsFor($user)['max_active_shops'];
    }

    public function userLimit(Shop $shop): int
    {
        return (int) $this->limitsFor($shop->user)['max_users'] + (int) $shop->user->additional_user_seats;
    }

    public function sellerLimit(Shop $shop): int
    {
        return (int) $this->limitsFor($shop->user)['max_sellers'] + (int) $shop->user->additional_seller_seats;
    }

    public function userCount(Shop $shop): int
    {
        return 1 + $shop->members()->where('is_active', true)->where('role', '!=', 'accountant')->count();
    }

    public function sellerCount(Shop $shop): int
    {
        return $shop->sellers()->where('is_active', true)->count();
    }

    public function hasFeature(User $user, string $feature): bool
    {
        return $user->isAdmin() || in_array($feature, $this->limitsFor($user)['features'] ?? [], true);
    }

    public function assertFeature(User $user, string $feature): void
    {
        if (! $this->hasFeature($user, $feature)) {
            throw ValidationException::withMessages([
                'plan' => "La función solicitada está disponible desde el plan {$this->planFor($user)->label()}.",
            ]);
        }
    }

    public function assertCanAddUser(Shop $shop): void
    {
        if ($this->userCount($shop) >= $this->userLimit($shop)) {
            throw ValidationException::withMessages([
                'email' => "El plan {$this->planFor($shop->user)->label()} permite {$this->userLimit($shop)} usuarios. Puedes contratar usuarios adicionales por US$".number_format((float) config('catalog.additional_seat_price_usd'), 2).' al mes.',
            ]);
        }
    }

    public function assertCanAddSeller(Shop $shop): void
    {
        if ($this->sellerCount($shop) >= $this->sellerLimit($shop)) {
            throw ValidationException::withMessages([
                'email' => "El plan {$this->planFor($shop->user)->label()} permite {$this->sellerLimit($shop)} vendedores. Puedes contratar vendedores adicionales por US$".number_format((float) config('catalog.additional_seat_price_usd'), 2).' al mes.',
            ]);
        }
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
            'user_count' => $this->userCount($shop),
            'user_limit' => $this->userLimit($shop),
            'users_remaining' => max(0, $this->userLimit($shop) - $this->userCount($shop)),
            'seller_count' => $this->sellerCount($shop),
            'seller_limit' => $this->sellerLimit($shop),
            'sellers_remaining' => max(0, $this->sellerLimit($shop) - $this->sellerCount($shop)),
            'can_add_users' => $this->userCount($shop) < $this->userLimit($shop),
            'can_add_sellers' => $this->sellerCount($shop) < $this->sellerLimit($shop),
            'additional_seat_price_usd' => (float) config('catalog.additional_seat_price_usd', 5),
            'features' => array_values($this->limitsFor($shop->user)['features'] ?? []),
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
