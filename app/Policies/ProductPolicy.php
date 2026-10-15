<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return $user->ownsShop($product->shop);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Product $product): bool
    {
        return $user->ownsShop($product->shop) || $user->isActiveShopManager($product->shop)
            || ($product->isService() && app(\App\Services\CatalogAccessService::class)->canWrite($product->shop, $user, 'service'));
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    public function restore(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    public function updateImages(User $user, Product $product): bool
    {
        return app(\App\Services\CatalogAccessService::class)->canManagePhotos($product->shop, $user, $product);
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return false;
    }

    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }
}
