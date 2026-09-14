<?php

namespace App\Policies;

use App\Models\ShopCategory;
use App\Models\User;

class ShopCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ShopCategory $shopCategory): bool
    {
        return $user->ownsShop($shopCategory->shop);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ShopCategory $shopCategory): bool
    {
        return $user->ownsShop($shopCategory->shop);
    }

    public function delete(User $user, ShopCategory $shopCategory): bool
    {
        return $user->ownsShop($shopCategory->shop);
    }

    public function restore(User $user, ShopCategory $shopCategory): bool
    {
        return $user->ownsShop($shopCategory->shop);
    }

    public function forceDelete(User $user, ShopCategory $shopCategory): bool
    {
        return false;
    }

    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }
}
