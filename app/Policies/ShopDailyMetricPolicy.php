<?php

namespace App\Policies;

use App\Models\ShopDailyMetric;
use App\Models\User;

class ShopDailyMetricPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ShopDailyMetric $shopDailyMetric): bool
    {
        return $user->ownsShop($shopDailyMetric->shop);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ShopDailyMetric $shopDailyMetric): bool
    {
        return false;
    }

    public function delete(User $user, ShopDailyMetric $shopDailyMetric): bool
    {
        return false;
    }

    public function restore(User $user, ShopDailyMetric $shopDailyMetric): bool
    {
        return false;
    }

    public function forceDelete(User $user, ShopDailyMetric $shopDailyMetric): bool
    {
        return false;
    }

    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }
}
