<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopCategory;
use App\Models\ShopSyncRecord;
use App\Models\User;
use Illuminate\Support\Collection;

/** Derived taxonomy faculties. No menu keys, categories, products or records are written here. */
class CatalogTaxonomyAccessService
{
    public const SCOPES = ['shared', 'product', 'service'];

    public function __construct(private readonly CatalogAccessService $catalog, private readonly SellerMenuService $menus) {}

    public function readableScopes(Shop $shop, User $user): array
    {
        if (! $user->canSellAtShop($shop)) {
            return [];
        }
        $access = $this->catalog->snapshotAccess($shop, $user);
        return array_values(array_filter(['shared', $access['physical'] || $access['decants'] ? 'product' : null,
            $access['services'] ? 'service' : null], fn ($scope) => $scope !== null && ($scope !== 'shared' || in_array(true, $access, true))));
    }

    public function canOpen(Shop $shop, User $user): bool
    {
        return $this->readableScopes($shop, $user) !== []
            && array_intersect(['products', 'services', 'decants'], $this->menus->visibleForUser($shop, $user)) !== [];
    }

    public function creatableScopes(Shop $shop, User $user): array
    {
        $physical = $this->canWriteProduct($shop, $user);
        $service = $this->catalog->canWrite($shop, $user, 'service');
        return array_values(array_filter([$physical && $service && $this->menus->canManage($shop, $user) ? 'shared' : null,
            $physical ? 'product' : null, $service ? 'service' : null]));
    }

    public function defaultScope(Shop $shop, User $user): ?string
    {
        return $this->creatableScopes($shop, $user)[0] ?? null;
    }

    public function payload(Shop $shop, User $user): array
    {
        return ['read_scopes' => $this->readableScopes($shop, $user), 'create_scopes' => $this->creatableScopes($shop, $user),
            'default_scope' => $this->defaultScope($shop, $user)];
    }

    public function categoryRecord(ShopCategory $category): ?ShopSyncRecord
    {
        return $this->records($category->shop)->first(fn ($row) => $row->kind === 'category'
            && (string) ($row->payload['server_id'] ?? '') === (string) $category->id);
    }

    public function categoryScope(ShopCategory $category): string
    {
        return $this->scope($this->categoryRecord($category)?->payload['scope'] ?? 'shared');
    }

    public function unitScope(Shop $shop, string $uuid): string
    {
        $record = $this->records($shop)->first(fn ($row) => $row->kind === 'unit' && $row->record_uuid === $uuid);
        if ($record) {
            return $this->scope($record->payload['scope'] ?? 'shared');
        }
        foreach (['unit', 'bottle', 'ml', 'decant', 'service'] as $name) {
            if (app(ShopRecordSyncService::class)->localId($shop, 'unit', $name) === $uuid) {
                return $name === 'service' ? 'service' : 'product';
            }
        }
        return 'unknown';
    }

    public function canReadCategory(ShopCategory $category, User $user): bool
    {
        return in_array($this->categoryScope($category), $this->readableScopes($category->shop, $user), true);
    }

    public function canModifyCategory(ShopCategory $category, User $user): bool
    {
        $shop = $category->shop;
        return $this->canModifyScope($shop, $user, $this->categoryScope($category))
            && $category->products()->withTrashed()->get(['sale_unit'])->every(fn ($product) => $this->catalog->canWrite($shop, $user, $product->sale_unit));
    }

    public function canModifyUnit(Shop $shop, User $user, string $uuid): bool
    {
        if (! $this->canModifyScope($shop, $user, $this->unitScope($shop, $uuid))) {
            return false;
        }
        $references = $this->records($shop)->filter(fn ($row) => $row->kind === 'product_unit' && ($row->payload['unit_uuid'] ?? null) === $uuid)
            ->pluck('record_uuid');
        $products = $shop->products()->withTrashed()->whereIn('public_id', $references)->get(['public_id', 'sale_unit']);
        if ($products->count() !== $references->unique()->count()) {
            return false;
        }
        // Built-in units may be used implicitly, without a product_unit record.
        foreach (['unit', 'bottle', 'ml', 'decant', 'service'] as $name) {
            if (app(ShopRecordSyncService::class)->localId($shop, 'unit', $name) === $uuid) {
                $products = $products->concat($shop->products()->withTrashed()->where('sale_unit', $name)->get(['public_id', 'sale_unit']));
            }
        }
        return $products->every(fn ($product) => $this->catalog->canWrite($shop, $user, $product->sale_unit));
    }

    public function compatible(string $scope, ?string $saleUnit): bool
    {
        return $scope === 'shared' || $scope === ($saleUnit === 'service' ? 'service' : 'product');
    }

    public function forget(Shop $shop): void
    {
        request()->attributes->remove('taxonomy-records:'.$shop->id);
    }

    public function records(Shop $shop): Collection
    {
        $key = 'taxonomy-records:'.$shop->id;
        $cached = request()->attributes->get($key);
        if ($cached instanceof Collection) {
            return $cached;
        }
        $records = ShopSyncRecord::where('shop_id', $shop->id)->get();
        request()->attributes->set($key, $records);
        return $records;
    }

    private function scope(mixed $scope): string
    {
        return is_string($scope) && in_array($scope, self::SCOPES, true) ? $scope : 'unknown';
    }

    private function canWriteProduct(Shop $shop, User $user): bool
    {
        return $this->catalog->canWrite($shop, $user, 'unit') || $this->catalog->canWrite($shop, $user, 'decant');
    }

    private function canModifyScope(Shop $shop, User $user, string $scope): bool
    {
        if (! $user->canSellAtShop($shop)) {
            return false;
        }
        return match ($scope) {
            'service' => $this->catalog->canWrite($shop, $user, 'service'),
            'product' => $this->canWriteProduct($shop, $user),
            'shared' => $this->menus->canManage($shop, $user) && $this->canWriteProduct($shop, $user),
            default => false,
        };
    }
}
