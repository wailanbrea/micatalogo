<?php

namespace App\Policies;

use App\Models\GlobalCategory;
use App\Models\User;

class GlobalCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, GlobalCategory $globalCategory): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, GlobalCategory $globalCategory): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, GlobalCategory $globalCategory): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, GlobalCategory $globalCategory): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, GlobalCategory $globalCategory): bool
    {
        return false;
    }
}
