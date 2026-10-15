<?php

namespace App\Services;

use App\Models\{Shop, ShopCategory, ShopSyncRecord};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CatalogTaxonomyWebCommands
{
    public function execute(Request $request, Shop $shop, string $kind, array $validated, ?ShopCategory $category = null, ?ShopSyncRecord $unit = null, bool $deleted = false)
    {
        $access = app(CatalogTaxonomyAccessService::class);
        $record = $category ? $access->categoryRecord($category) : $unit;
        if (! $record && isset($validated['record_id'])) {
            $record = $access->records($shop)->first(fn ($row) => $row->kind === $kind && $row->record_uuid === $validated['record_id']);
            if ($record && $kind === 'category') $category = $shop->categories()->whereKey($record->payload['server_id'])->firstOrFail();
        }
        $old = $record?->payload ?? [];
        $uuid = $record?->record_uuid ?? ($category ? app(CatalogTaxonomyService::class)->categoryIdentity($category) : ($validated['record_id'] ?? (string) Str::uuid()));
        $data = ['type' => $kind.'_upsert', 'record_id' => $uuid,
            'client_operation_uuid' => $validated['client_operation_uuid'] ?? (string) Str::uuid(),
            'name' => $validated['name'] ?? $category?->name ?? $old['name'],
            'is_active' => ! $deleted && ($validated['status'] ?? 'active') === 'active', 'is_deleted' => $deleted,
            'created_at' => $old['created_at'] ?? $category?->created_at?->toISOString() ?? now()->toISOString()];
        if (array_key_exists('scope', $validated)) $data['scope'] = $validated['scope'];
        if ($kind === 'category' && array_key_exists('slug', $validated)) $data['slug'] = $validated['slug'];
        if (array_key_exists('expected_revision', $validated)) $data['expected_revision'] = $validated['expected_revision'];
        $data += $kind === 'category' ? ['description' => $validated['description'] ?? $old['description'] ?? null,
            'icon' => $old['icon'] ?? 'category_default', 'sort_order' => (int) ($validated['sort_order'] ?? $category?->sort_order ?? 0)]
            : ['abbreviation' => strtoupper(trim($validated['abbreviation'] ?? $old['abbreviation']))];
        $parameters = ['shop' => $shop, 'tab' => $kind === 'unit' ? 'units' : 'categories'];
        try {
            app(MobileOperationService::class)->apply($shop, $request->user(), $data, 'web');
        } catch (InvalidArgumentException $error) {
            if ($request->expectsJson()) return response()->json(['message' => $error->getMessage()], $error->getCode() ?: 422);
            return to_route('seller.shops.categories.index', $parameters)->withInput()->withErrors(['taxonomy' => $error->getMessage()]);
        }
        return to_route('seller.shops.categories.index', $parameters)->with('status', $deleted ? 'Registro desactivado; se conserva el histórico.' : 'Taxonomía guardada y confirmada.');
    }
}
