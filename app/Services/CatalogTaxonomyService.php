<?php

namespace App\Services;

use App\Models\{Shop, ShopCategory, ShopSyncRecord, User};
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Common category/unit command path, called inside the shop-locked operation transaction. */
class CatalogTaxonomyService
{
    public function __construct(private readonly CatalogTaxonomyAccessService $access) {}

    public function authorize(Shop $shop, User $user, array $input): void
    {
        abort_unless($user->canSellAtShop($shop), 404);
        [$kind, $record, $category, $scope, $existingScope] = $this->resolve($shop, $user, $input);
        if ($category) {
            abort_unless($this->access->canModifyCategory($category, $user), 403);
        } elseif ($record || $existingScope !== null) {
            abort_unless($this->access->canModifyUnit($shop, $user, $input['record_id']), 403);
        } else {
            abort_unless(in_array($scope, $this->access->creatableScopes($shop, $user), true), 403);
        }
        if ($existingScope !== null && $scope !== $existingScope) {
            abort_unless($user->ownsShop($shop) || $user->isActiveShopManager($shop), 403);
            abort_unless(in_array($scope, $this->access->creatableScopes($shop, $user), true), 403);
        }
    }

    public function apply(Shop $shop, User $user, array $input, string $source): array
    {
        $this->authorize($shop, $user, $input);
        [$kind, $existing, $category, $scope, $oldScope] = $this->resolve($shop, $user, $input);
        $data = Validator::make($input, [
            'record_id' => ['required', 'uuid'], 'name' => ['required', 'string', 'max:'.($kind === 'category' ? 120 : 100)],
            'is_active' => ['required', 'boolean'], 'is_deleted' => ['required', 'boolean'], 'created_at' => ['required', 'date'],
            'scope' => ['sometimes', 'string', Rule::in(CatalogTaxonomyAccessService::SCOPES)],
            'expected_revision' => ['sometimes', 'nullable', 'uuid'],
            ...($kind === 'category' ? ['description' => ['nullable', 'string', 'max:2000'], 'icon' => ['required', 'string', 'max:80'],
                'sort_order' => ['required', 'integer', 'min:0', 'max:65535'], 'slug' => ['sometimes', 'nullable', 'string', 'max:120']] : ['abbreviation' => ['required', 'string', 'max:12']]),
        ])->validate();
        $data['name'] = trim($data['name']);
        if ($data['name'] === '') throw ValidationException::withMessages(['name' => 'Indica un nombre.']);
        if ($oldScope !== null && $oldScope !== $scope && ($this->used($shop, $kind, $input['record_id'], $category)
            || $this->builtIn($shop, $input['record_id']) !== null)) {
            throw ValidationException::withMessages(['scope' => 'No cambies el alcance de taxonomía referenciada o de una unidad del sistema.']);
        }
        $old = $existing?->payload ?? [];
        if (array_key_exists('expected_revision', $data) && ($data['expected_revision'] ?? null) !== ($old['revision'] ?? null)) {
            throw new InvalidArgumentException('La taxonomía cambió en otro cliente. Recarga y concilia el borrador.', 409);
        }
        $data['scope'] = $scope;
        $data['created_at'] = $old['created_at'] ?? $category?->created_at?->toISOString() ?? $data['created_at'];
        unset($data['expected_revision']);
        $compareFields = ['name', 'scope', 'is_active', 'is_deleted', ...($kind === 'category' ? ['description', 'icon', 'sort_order'] : ['abbreviation'])];
        $changed = collect($compareFields)->contains(fn ($key) => ($data[$key] ?? null) !== ($old[$key] ?? null));
        if ($changed && ! array_key_exists('expected_revision', $input) && ($old['last_writer'] ?? '') === 'web') {
            throw new InvalidArgumentException('Este registro fue editado en Web. Actualiza el cliente o concilia antes de sobrescribirlo.', 409);
        }
        if ($existing && ! $changed) return ['record_id' => $existing->record_uuid, 'record' => $existing->payload];
        $collision = $this->access->records($shop)->contains(fn ($row) => $row->kind === $kind && $row->record_uuid !== $data['record_id']
            && ! ($row->payload['is_deleted'] ?? false) && mb_strtolower(trim($row->payload['name'] ?? '')) === mb_strtolower($data['name']));
        if ($collision) throw ValidationException::withMessages(['name' => 'Ya existe un registro con ese nombre en esta tienda.']);
        if ($kind === 'category') {
            if ($shop->categories()->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
                ->when($category, fn ($query) => $query->whereKeyNot($category->id))->exists()) {
                throw ValidationException::withMessages(['name' => 'Ya existe una categoría con ese nombre.']);
            }
            $category ??= $shop->categories()->make();
            $category->fill(['name' => $data['name'], 'sort_order' => $data['sort_order'],
                'status' => $data['is_active'] && ! $data['is_deleted'] ? 'active' : 'inactive']);
            if (! $category->exists || ! empty($data['slug'])) {
                $base = Str::slug($data['slug'] ?? $data['name']) ?: 'categoria';
                $slug = $base;
                $suffix = 2;
                while ($shop->categories()->where('slug', $slug)->when($category->exists, fn ($query) => $query->whereKeyNot($category->id))->exists()) $slug = $base.'-'.$suffix++;
                $category->slug = $slug;
            }
            $category->save();
            $data['server_id'] = (string) $category->id;
        }
        $data['revision'] = (string) Str::uuid();
        $data['last_writer'] = $source;
        $record = ShopSyncRecord::updateOrCreate(['shop_id' => $shop->id, 'kind' => $kind, 'record_uuid' => $data['record_id']], ['payload' => $data]);
        $this->access->forget($shop);
        return ['record_id' => $record->record_uuid, 'record' => $record->payload];
    }

    public function categoryIdentity(ShopCategory $category): string
    {
        return $this->access->categoryRecord($category)?->record_uuid
            ?? app(ShopRecordSyncService::class)->localId($category->shop, 'category', (string) $category->id);
    }

    public function assertCategoryAssignment(Shop $shop, User $user, ShopCategory $category, ?string $saleUnit, ?int $currentId = null): void
    {
        abort_unless($category->shop_id === $shop->id && $this->access->canReadCategory($category, $user), 403);
        abort_unless($this->access->compatible($this->access->categoryScope($category), $saleUnit), 403);
        if ($category->status->value !== 'active' && $currentId !== $category->id) {
            throw ValidationException::withMessages(['category_uuid' => 'La categoría está desactivada.']);
        }
    }

    public function categoryByName(Shop $shop, User $user, string $name, ?string $saleUnit): ShopCategory
    {
        $category = $shop->categories()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->first();
        if (! $category) {
            $scope = $saleUnit === 'service' ? 'service' : 'product';
            $ack = $this->apply($shop, $user, ['type' => 'category_upsert', 'record_id' => (string) Str::uuid(),
                'name' => $name, 'scope' => $scope, 'is_active' => true, 'is_deleted' => false, 'created_at' => now()->toISOString(),
                'icon' => 'category_default', 'sort_order' => 0], 'android');
            $category = $shop->categories()->whereKey($ack['record']['server_id'])->firstOrFail();
        }
        $this->assertCategoryAssignment($shop, $user, $category, $saleUnit);
        return $category;
    }

    public function assertUnitAssignment(Shop $shop, User $user, string $uuid, ?string $saleUnit, ?string $currentId = null): void
    {
        $scope = $this->access->unitScope($shop, $uuid);
        abort_unless(in_array($scope, $this->access->readableScopes($shop, $user), true) && $this->access->compatible($scope, $saleUnit), 403);
        $record = $this->access->records($shop)->first(fn ($row) => $row->kind === 'unit' && $row->record_uuid === $uuid);
        if ($record && (! ($record->payload['is_active'] ?? false) || ($record->payload['is_deleted'] ?? false)) && $currentId !== $uuid) {
            throw ValidationException::withMessages(['unit_uuid' => 'La unidad está desactivada.']);
        }
    }

    private function resolve(Shop $shop, User $user, array $input): array
    {
        $this->access->forget($shop);
        $kind = $input['type'] === 'category_upsert' ? 'category' : 'unit';
        $uuid = $input['record_id'];
        abort_if(ShopSyncRecord::where('kind', $kind)->where('record_uuid', $uuid)->where('shop_id', '!=', $shop->id)->exists(), 404);
        $record = $this->access->records($shop)->first(fn ($row) => $row->kind === $kind && $row->record_uuid === $uuid);
        $category = $kind === 'category' ? app(ShopRecordSyncService::class)->category($shop, $uuid) : null;
        if ($kind === 'category' && $record && ! $category) abort(404);
        if ($category && ($canonical = $this->access->categoryRecord($category)) && $canonical->record_uuid !== $uuid) {
            throw new InvalidArgumentException('La categoría ya tiene otra identidad canónica; requiere conciliación.', 409);
        }
        $oldScope = $category ? $this->access->categoryScope($category) : ($record ? ($record->payload['scope'] ?? 'shared') : null);
        if ($kind === 'unit' && $oldScope === null && $this->builtIn($shop, $uuid) !== null) $oldScope = $this->access->unitScope($shop, $uuid);
        $scope = $input['scope'] ?? $oldScope ?? $this->access->defaultScope($shop, $user);
        return [$kind, $record, $category, $scope, $oldScope];
    }

    private function builtIn(Shop $shop, string $uuid): ?string
    {
        foreach (['unit', 'bottle', 'ml', 'decant', 'service'] as $name) {
            if (app(ShopRecordSyncService::class)->localId($shop, 'unit', $name) === $uuid) return $name;
        }
        return null;
    }

    private function used(Shop $shop, string $kind, string $uuid, ?ShopCategory $category): bool
    {
        return $kind === 'category' ? ($category?->products()->withTrashed()->exists() ?? false)
            : $this->access->records($shop)->contains(fn ($row) => $row->kind === 'product_unit' && ($row->payload['unit_uuid'] ?? null) === $uuid);
    }
}
