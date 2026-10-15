<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Existing menu keys authorize their own resource type; defaults never grant physical CRUD. */
class CatalogAccessService
{
    public function __construct(private readonly SellerMenuService $menus) {}

    public function keyForType(?string $unit): string
    {
        return match ($unit) {
            'service' => 'services', 'decant' => 'decants', default => 'products',
        };
    }

    public function canWrite(Shop $shop, User $user, ?string $unit): bool
    {
        if (! $user->canSellAtShop($shop) || ! in_array($this->keyForType($unit), $this->menus->visibleForUser($shop, $user), true)) {
            return false;
        }

        // Services is an explicit delegation outside legacy sales/products/printers defaults.
        return $this->menus->canManage($shop, $user) || $unit === 'service';
    }

    public function canReadCosts(Shop $shop, User $user): bool
    {
        return $user->canSellAtShop($shop) && ($this->menus->canManage($shop, $user)
            || in_array('finance', $this->menus->visibleForUser($shop, $user), true));
    }

    public function canManagePhotos(Shop $shop, User $user, ?Product $product = null): bool
    {
        return $user->canSellAtShop($shop) && (in_array('photos', $this->menus->visibleForUser($shop, $user), true)
            || ($product && $this->canWrite($shop, $user, $product->sale_unit)));
    }

    /** @return array{physical: bool, services: bool, decants: bool, routes: bool} */
    public function snapshotAccess(Shop $shop, User $user): array
    {
        $keys = $this->menus->visibleForUser($shop, $user);
        $selling = array_intersect(['sales', 'quotes'], $keys) !== [];

        return [
            'physical' => $selling || array_intersect(['products', 'inventory', 'inventory_adjustments', 'import', 'photos', 'decants', 'routes', 'purchase_invoices', 'containers', 'loads', 'pricing', 'price_health'], $keys) !== [],
            'services' => $selling || array_intersect(['services', 'photos'], $keys) !== [],
            'decants' => $selling || array_intersect(['decants', 'inventory', 'inventory_adjustments', 'photos'], $keys) !== [],
            'routes' => in_array('routes', $keys, true),
        ];
    }

    /** @return array<string, bool> */
    public function payload(Shop $shop, User $user): array
    {
        return ['manage_products' => $this->canWrite($shop, $user, 'unit'),
            'manage_services' => $this->canWrite($shop, $user, 'service'),
            'manage_decants' => $this->canWrite($shop, $user, 'decant'),
            'manage_photos' => $this->canManagePhotos($shop, $user),
            'read_costs' => $this->canReadCosts($shop, $user)];
    }

    public function assertWrite(Shop $shop, User $user, ?Product $product, array $input): void
    {
        abort_unless($user->canSellAtShop($shop), 404);
        if ($product) {
            abort_unless($product->shop_id === $shop->id, 404);
            abort_unless($this->canWrite($shop, $user, $product->sale_unit), 403);
        }
        $unit = ! empty($input['is_combo']) ? 'unit' : ($input['sale_unit'] ?? $product?->sale_unit ?? 'unit');
        abort_unless($this->canWrite($shop, $user, is_string($unit) ? $unit : 'unit'), 403);
        if (isset($input['cost_price']) && $input['cost_price'] !== '' && ! $this->canReadCosts($shop, $user)) {
            abort(403);
        }
        if ($unit === 'service') {
            if (! empty($input['track_inventory']) || (int) ($input['stock_quantity'] ?? 0) > 0 || ! empty($input['is_combo'])) {
                throw ValidationException::withMessages(['stock_quantity' => 'Un servicio no administra existencias físicas.']);
            }
        } elseif ($product && $product->inventory) {
            $stockChanged = isset($input['stock_quantity']) && (int) $input['stock_quantity'] !== (int) $product->inventory->stock_quantity;
            $trackingChanged = array_key_exists('track_inventory', $input) && filter_var($input['track_inventory'], FILTER_VALIDATE_BOOLEAN) !== (bool) $product->inventory->track_inventory;
            if ($stockChanged || $trackingChanged) {
                abort_unless(in_array('inventory_adjustments', $this->menus->visibleForUser($shop, $user), true), 403);
            }
        } elseif (! $product && (int) ($input['stock_quantity'] ?? 0) > 0) {
            abort_unless(in_array('inventory', $this->menus->visibleForUser($shop, $user), true), 403);
        }
    }
}
