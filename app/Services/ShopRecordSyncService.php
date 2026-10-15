<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopCategory;
use App\Models\ShopSyncRecord;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Called inside the shop-locked, idempotent MobileOperationService transaction. */
class ShopRecordSyncService
{
    private array $readCache = [];
    public const TYPES = ['category_upsert', 'unit_upsert', 'adjustment_reason_upsert'];

    public function apply(Shop $shop, array $input, ?User $user = null, string $source = 'android'): array
    {
        unset($this->readCache[$shop->id]);
        if (in_array($input['type'], ['category_upsert', 'unit_upsert'], true)) {
            abort_unless($user, 403);
            return app(CatalogTaxonomyService::class)->apply($shop, $user, $input, $source);
        }
        $kind = match ($input['type']) {
            'category_upsert' => 'category',
            'unit_upsert' => 'unit',
            'adjustment_reason_upsert' => 'adjustment_reason',
        };
        $rules = [
            'record_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'is_deleted' => ['required', 'boolean'],
            'created_at' => ['required', 'date'],
        ];
        $rules += match ($kind) {
            'category' => ['description' => ['nullable', 'string', 'max:2000'], 'icon' => ['required', 'string', 'max:80'], 'sort_order' => ['required', 'integer', 'min:0']],
            'unit' => ['abbreviation' => ['required', 'string', 'max:12']],
            'adjustment_reason' => ['direction' => ['required', 'in:IN,OUT,BOTH']],
        };
        $data = Validator::make($input, $rules)->validate();
        $data['name'] = trim($data['name']);
        if ($data['name'] === '') {
            throw ValidationException::withMessages(['name' => 'Indica un nombre.']);
        }
        $existing = $this->query($shop, $kind)->where('record_uuid', $data['record_id'])->first();
        $collision = $this->query($shop, $kind)->where('record_uuid', '!=', $data['record_id'])->get()
            ->contains(fn ($row) => ! ($row->payload['is_deleted'] ?? false) && mb_strtolower($row->payload['name'] ?? '') === mb_strtolower($data['name']));
        if ($collision) {
            throw ValidationException::withMessages(['name' => 'Ya existe un registro con ese nombre en esta tienda.']);
        }
        if ($kind === 'category') {
            $category = $this->category($shop, $data['record_id']);
            $duplicate = $shop->categories()->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
                ->when($category, fn ($query) => $query->whereKeyNot($category->id))->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['name' => 'Ya existe una categoría con ese nombre.']);
            }
            $category ??= $shop->categories()->make();
            $category->fill(['name' => $data['name'], 'status' => $data['is_active'] && ! $data['is_deleted'] ? 'active' : 'inactive',
                'sort_order' => $data['sort_order']]);
            if (! $category->exists) {
                $category->slug = (Str::slug($data['name']) ?: 'categoria').'-'.substr($data['record_id'], 0, 8);
            }
            $category->save();
            $data['server_id'] = (string) $category->id;
        }
        // Preserve original creation time and historical references on deactivation.
        if ($existing) {
            $data['created_at'] = $existing->payload['created_at'];
        }
        $row = ShopSyncRecord::updateOrCreate(['shop_id' => $shop->id, 'kind' => $kind, 'record_uuid' => $data['record_id']], ['payload' => $data]);

        return ['record_id' => $row->record_uuid, 'record' => $row->payload];
    }

    public function category(Shop $shop, string $localId): ?ShopCategory
    {
        $record = $this->query($shop, 'category')->where('record_uuid', $localId)->first();
        if ($record) {
            return $shop->categories()->whereKey($record->payload['server_id'])->first();
        }
        // Matches the existing Android UUID.nameUUIDFromBytes identity exactly.
        // No name guessing, historical reassignment or cross-shop lookup.
        return $shop->categories()->get()->first(fn ($category) => $this->localId($shop, 'category', (string) $category->id) === $localId);
    }

    public function categoryMetadata(Shop $shop): array
    {
        return $this->readRows($shop)->where('kind', 'category')->keyBy(fn ($row) => $row->payload['server_id'])->all();
    }

    public function snapshot(Shop $shop, ?User $user = null): array
    {
        $taxonomy = app(CatalogTaxonomyAccessService::class);
        return $this->readRows($shop)->whereIn('kind', ['unit', 'adjustment_reason'])
            ->filter(fn ($row) => ! $user || ($row->kind === 'unit'
                ? in_array($taxonomy->unitScope($shop, $row->record_uuid), $taxonomy->readableScopes($shop, $user), true)
                : in_array('inventory_adjustments', app(SellerMenuService::class)->visibleForUser($shop, $user), true)))
            ->map(fn ($row) => ['kind' => $row->kind, 'record_id' => $row->record_uuid,
                'payload' => $row->kind === 'unit' ? $row->payload + ['scope' => $taxonomy->unitScope($shop, $row->record_uuid)] : $row->payload,
                'permissions' => ['update' => $user && $row->kind === 'unit' && $taxonomy->canModifyUnit($shop, $user, $row->record_uuid)],
                'updated_at' => $row->updated_at->toISOString()])->values()->all();
    }

    public function saveProductUnit(Shop $shop, string $productId, string $unitId, User $user): void
    {
        $product = $shop->products()->withTrashed()->where('public_id', $productId)->firstOrFail();
        abort_unless(app(CatalogAccessService::class)->canWrite($shop, $user, $product->sale_unit), 403);
        app(CatalogTaxonomyService::class)->assertUnitAssignment($shop, $user, $unitId, $product->sale_unit, $this->productUnits($shop)[$productId] ?? null);
        ShopSyncRecord::updateOrCreate(['shop_id' => $shop->id, 'kind' => 'product_unit', 'record_uuid' => $productId],
            ['payload' => ['unit_uuid' => $unitId]]);
        app(CatalogTaxonomyAccessService::class)->forget($shop);
    }

    public function productUnits(Shop $shop): array
    {
        return $this->readRows($shop)->where('kind', 'product_unit')->mapWithKeys(fn ($row) => [$row->record_uuid => $row->payload['unit_uuid']])->all();
    }

    public function localId(Shop $shop, string $kind, string $id): string
    {
        $bytes = md5("micatalogo:$kind:$shop->public_id:$id", true);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x30);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }

    private function query(Shop $shop, ?string $kind = null)
    {
        return ShopSyncRecord::where('shop_id', $shop->id)->when($kind, fn ($query) => $query->where('kind', $kind));
    }
    private function readRows(Shop $shop)
    {
        return app(CatalogTaxonomyAccessService::class)->records($shop)->sortBy('id');
    }
}
