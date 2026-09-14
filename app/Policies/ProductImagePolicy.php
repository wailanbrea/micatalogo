<?php

namespace App\Policies;

use App\Models\ProductImage;
use App\Models\User;

class ProductImagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductImage $productImage): bool
    {
        return $user->ownsShop($productImage->product->shop);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ProductImage $productImage): bool
    {
        return $user->ownsShop($productImage->product->shop);
    }

    public function delete(User $user, ProductImage $productImage): bool
    {
        return $user->ownsShop($productImage->product->shop);
    }

    public function restore(User $user, ProductImage $productImage): bool
    {
        return false;
    }

    public function forceDelete(User $user, ProductImage $productImage): bool
    {
        return false;
    }

    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }
}
