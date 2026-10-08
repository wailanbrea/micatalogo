<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;

class SellerMenuService
{
    public function __construct(private readonly BusinessProfileService $profiles) {}

    /** @return array<string, string> */
    public function options(): array
    {
        return config('bspos.seller_menu_options', []);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->options());
    }

    /** @return array<string, string> */
    public function assignableOptions(): array
    {
        return array_diff_key($this->options(), array_flip($this->ownerOnlyKeys()));
    }

    /** @return list<string> */
    public function assignableKeys(): array
    {
        return array_keys($this->assignableOptions());
    }

    /** @return list<string> */
    public function ownerOnlyKeys(): array
    {
        return config('bspos.owner_only_menu_options', []);
    }

    /** @return list<string> */
    public function normalize(?array $permissions): array
    {
        if ($permissions === null) {
            return [];
        }

        return array_values(array_intersect($this->assignableKeys(), $permissions));
    }

    /** @return list<string> */
    public function defaultPermissions(): array
    {
        return ['sales', 'products', 'printers'];
    }

    public function canManage(Shop $shop, User $user): bool
    {
        return $user->isAdmin() || $user->ownsShop($shop) || $shop->members()
            ->where('user_id', $user->id)
            ->where('role', 'manager')
            ->where('is_active', true)
            ->exists();
    }

    /** @return list<string> */
    public function forUser(Shop $shop, User $user): array
    {
        if ($this->canManage($shop, $user)) {
            return $this->keys();
        }

        if ($shop->members()->where('user_id', $user->id)->where('role', 'accountant')->where('is_active', true)->exists()) {
            return ['accountant', 'finance', 'reports'];
        }

        $assignment = $shop->sellers()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        return $this->forAssignment($assignment);
    }

    /** @return list<string> */
    public function visibleForUser(Shop $shop, User $user): array
    {
        $menus = $this->forUser($shop, $user);
        $capabilities = $this->profiles->capabilities($shop);

        return array_values(array_filter($menus, fn (string $menu): bool => $this->menuIsAvailable($menu, $capabilities)));
    }

    /** @param array<string, string> $capabilities */
    private function menuIsAvailable(string $menu, array $capabilities): bool
    {
        $capability = match ($menu) {
            'products' => 'products',
            'inventory' => 'inventory',
            'sales' => 'sales',
            'customers' => 'customers',
            'collections' => 'credit',
            'cash' => 'cash',
            'finance' => 'finance',
            'expenses' => 'expenses',
            'services' => 'services',
            'decants' => 'decants',
            'public_catalog', 'metrics' => 'public_catalog',
            default => null,
        };

        return $capability === null || ($capabilities[$capability] ?? 'disabled') === 'enabled';
    }

    /** @return list<string> */
    public function forAssignment(?ShopSeller $assignment): array
    {
        if (! $assignment) {
            return [];
        }

        return $assignment->menu_permissions === null
            ? $this->defaultPermissions()
            : $this->normalize($assignment->menu_permissions);
    }
}
