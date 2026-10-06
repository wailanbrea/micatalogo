<?php

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;

class ShopPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Shop $shop): bool
    {
        return $user->ownsShop($shop) || $user->isActiveShopMember($shop);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || (! $user->hasActiveShopAssignment() && ! $user->hasActiveShopMembership());
    }

    public function update(User $user, Shop $shop): bool
    {
        return $user->ownsShop($shop) || $user->isActiveShopMember($shop);
    }

    public function viewFinance(User $user, Shop $shop): bool
    {
        return ($user->isAdmin() || $user->canSellAtShop($shop))
            && in_array('finance', app(\App\Services\SellerMenuService::class)->visibleForUser($shop, $user), true);
    }

    public function sell(User $user, Shop $shop): bool
    {
        return $user->canSellAtShop($shop);
    }

    public function delete(User $user, Shop $shop): bool
    {
        return $user->ownsShop($shop);
    }

    public function restore(User $user, Shop $shop): bool
    {
        return $user->ownsShop($shop);
    }

    public function forceDelete(User $user, Shop $shop): bool
    {
        return false;
    }

    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }
}
