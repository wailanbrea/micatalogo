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
            return array_values(array_diff($this->assignableKeys(), ['finance']));
        }

        return array_values(array_intersect($this->assignableKeys(), $permissions));
    }

    public function canManage(Shop $shop, User $user): bool
    {
        return $user->isAdmin() || $user->ownsShop($shop) || $user->isActiveShopMember($shop);
    }

    /** @return list<string> */
    public function forUser(Shop $shop, User $user): array
    {
        if ($this->canManage($shop, $user)) {
            return $this->keys();
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

        return array_values(array_filter($menus, fn (string $menu): bool => $this->menuIsAvailable($shop, $menu)));
    }

    private function menuIsAvailable(Shop $shop, string $menu): bool
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
            'public_catalog', 'metrics' => 'public_catalog',
            default => null,
        };

        return $capability === null || $this->profiles->allows($shop, $capability);
    }

    /** @return list<string> */
    public function forAssignment(?ShopSeller $assignment): array
    {
        if (! $assignment) {
            return ['sales', 'products', 'printers'];
        }

        return array_values(array_unique(array_merge(
            ['sales', 'products', 'printers'],
            $this->normalize($assignment->menu_permissions),
        )));
    }
}
