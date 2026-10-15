<?php

namespace App\Policies;

use App\Models\ShopCategory;
use App\Models\User;
use App\Models\Shop;
use App\Services\CatalogTaxonomyAccessService;

class ShopCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ShopCategory $shopCategory): bool
    {
        return app(CatalogTaxonomyAccessService::class)->canReadCategory($shopCategory, $user);
    }

    public function create(User $user, ?Shop $shop = null, mixed $scope = null): bool
    {
        if (! $shop) return false;
        $access = app(CatalogTaxonomyAccessService::class);
        return in_array($scope ?? $access->defaultScope($shop, $user), $access->creatableScopes($shop, $user), true);
    }

    public function update(User $user, ShopCategory $shopCategory): bool
    {
        return app(CatalogTaxonomyAccessService::class)->canModifyCategory($shopCategory, $user);
    }

    public function delete(User $user, ShopCategory $shopCategory): bool
    {
        return $this->update($user, $shopCategory);
    }

    public function restore(User $user, ShopCategory $shopCategory): bool
    {
        return $this->update($user, $shopCategory);
    }

    public function forceDelete(User $user, ShopCategory $shopCategory): bool
    {
        return false;
    }

    public function before(User $user): ?bool
    {
        return null;
    }
}
