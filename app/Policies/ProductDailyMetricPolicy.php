<?php

namespace App\Policies;

use App\Models\ProductDailyMetric;
use App\Models\User;

class ProductDailyMetricPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductDailyMetric $productDailyMetric): bool
    {
        return $user->ownsShop($productDailyMetric->product->shop);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ProductDailyMetric $productDailyMetric): bool
    {
        return false;
    }

    public function delete(User $user, ProductDailyMetric $productDailyMetric): bool
    {
        return false;
    }

    public function restore(User $user, ProductDailyMetric $productDailyMetric): bool
    {
        return false;
    }

    public function forceDelete(User $user, ProductDailyMetric $productDailyMetric): bool
    {
        return false;
    }

    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }
}
