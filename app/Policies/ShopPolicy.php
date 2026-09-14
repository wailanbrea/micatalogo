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
        return $user->ownsShop($shop);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Shop $shop): bool
    {
        return $user->ownsShop($shop);
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
