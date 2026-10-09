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

    /**
     * Return the menu keys enabled for a shop. A null column is intentional:
     * it preserves the legacy behavior for every existing shop until its
     * owner explicitly saves a restriction.
     *
     * @return list<string>
     */
    public function enabledKeys(Shop $shop): array
    {
        if (! is_array($shop->enabled_menu_keys)) {
            return $this->keys();
        }

        return $this->normalizeEnabled($shop->enabled_menu_keys);
    }

    /** @return list<string> */
    public function normalizeEnabled(?array $keys): array
    {
        return array_values(array_unique(array_intersect($this->keys(), $keys ?? [])));
    }

    /**
     * The owner/admin can always reach the control entries so a disabled
     * operational module can be re-enabled later.
     *
     * @return list<string>
     */
    public function ownerControlKeys(): array
    {
        return array_values(array_intersect(['settings', 'shop_settings', 'sellers'], $this->keys()));
    }

    public function canManageMenuVisibility(Shop $shop, User $user): bool
    {
        return $this->rememberForRequest(
            'menu-visibility:'.$shop->getKey().':'.$user->getKey(),
            fn (): bool => $user->isAdmin() || $user->ownsShop($shop),
        );
    }

    /** @return array<int, array{key: string, label: string, group: string, protected: bool}> */
    public function ownerMenuOptions(): array
    {
        return collect($this->options())->map(function (string $label, string $key): array {
            return [
                'key' => $key,
                'label' => $label,
                'group' => $this->groupFor($key),
                'protected' => in_array($key, $this->ownerControlKeys(), true),
            ];
        })->values()->all();
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
        return $this->rememberForRequest(
            'manage:'.$shop->getKey().':'.$user->getKey(),
            fn (): bool => $user->isAdmin() || $user->ownsShop($shop) || $shop->members()
                ->where('user_id', $user->id)
                ->where('role', 'manager')
                ->where('is_active', true)
                ->exists(),
        );
    }

    /** @return list<string> */
    public function forUser(Shop $shop, User $user): array
    {
        return $this->rememberForRequest(
            'for-user:'.$shop->getKey().':'.$user->getKey(),
            function () use ($shop, $user): array {
                if ($this->canManage($shop, $user)) {
                    return $this->keys();
                }

                if ($shop->members()->where('user_id', $user->id)->where('role', 'accountant')->where('is_active', true)->exists()) {
                    // An accountant is a read-only financial collaborator. The
                    // accountant module contains owner/manager controls to grant or
                    // revoke other accountant access, so it must never be exposed to
                    // the accountant themselves.
                    return ['finance', 'reports'];
                }

                $assignment = $shop->sellers()
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->first();

                return $this->forAssignment($assignment);
            },
        );
    }

    /** @return list<string> */
    public function visibleForUser(Shop $shop, User $user): array
    {
        return $this->rememberForRequest(
            'visible:'.$shop->getKey().':'.$user->getKey(),
            function () use ($shop, $user): array {
                $menus = $this->forUser($shop, $user);
                $capabilities = $this->profiles->capabilities($shop);
                $enabled = array_flip($this->enabledKeys($shop));
                $ownerControls = $this->canManageMenuVisibility($shop, $user)
                    ? array_flip($this->ownerControlKeys())
                    : [];

                return array_values(array_filter($menus, fn (string $menu): bool =>
                    isset($ownerControls[$menu]) ||
                    (isset($enabled[$menu]) && $this->menuIsAvailable($menu, $capabilities))
                ));
            },
        );
    }

    private function groupFor(string $key): string
    {
        return match ($key) {
            'sales', 'quotes', 'orders', 'encargos', 'shipments', 'day_close' => 'Operación',
            'containers', 'loads', 'suppliers', 'purchase_invoices' => 'Compras',
            'products', 'photos', 'storefront', 'services', 'price_health', 'pricing', 'decants', 'attributes', 'import' => 'Catálogo',
            'customers', 'credit', 'collections' => 'Cobros',
            'cash', 'finance', 'inventory_adjustments', 'partners', 'expenses' => 'Finanzas',
            'reports', 'metrics' => 'Análisis',
            'commissions', 'authorizations', 'sellers' => 'Equipo',
            default => 'Ajustes',
        };
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

    private function rememberForRequest(string $key, callable $resolver): mixed
    {
        if (! app()->bound('request')) {
            return $resolver();
        }

        $request = request();
        $cache = $request->attributes->get('seller_menu_cache', []);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $value = $resolver();
        $cache[$key] = $value;
        $request->attributes->set('seller_menu_cache', $cache);

        return $value;
    }
}
