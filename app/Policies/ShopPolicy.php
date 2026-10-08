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
        return $user->ownsShop($shop) || $user->isActiveShopMember($shop) || $user->isActiveShopAccountant($shop);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || (! $user->hasActiveShopAssignment() && ! $user->hasActiveShopMembership());
    }

    public function update(User $user, Shop $shop): bool
    {
        return $user->ownsShop($shop) || $user->isActiveShopManager($shop);
    }

    public function viewFinance(User $user, Shop $shop): bool
    {
        return ($user->isAdmin() || $user->canSellAtShop($shop) || $user->isActiveShopAccountant($shop))
            && in_array('finance', app(\App\Services\SellerMenuService::class)->visibleForUser($shop, $user), true);
    }

    public function sell(User $user, Shop $shop): bool
    {
        // Feature pages use this ability as a shop-context gate. Mutating
        // routes still require their own menu/ability checks, so accountants
        // can read finance/help modules without gaining POS access.
        return $user->canSellAtShop($shop) || $user->isActiveShopAccountant($shop);
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
