<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShopCategoryRequest;
use App\Models\Shop;
use App\Models\ShopCategory;
use App\Services\CatalogTaxonomyAccessService;
use App\Services\CatalogTaxonomyWebCommands;
use App\Services\ShopRecordSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShopCategoryController extends Controller
{
    public function index(Request $request, Shop $shop): View
    {
        $query = trim($request->validate(['q' => ['nullable', 'string', 'max:120']])['q'] ?? '');
        $access = app(CatalogTaxonomyAccessService::class);
        $categories = $shop->categories()->orderBy('sort_order')->orderBy('name')->get()
            ->filter(fn ($category) => $access->canReadCategory($category, $request->user()) && ($query === '' || mb_stripos($category->name, $query) !== false));
        $units = $access->records($shop)->where('kind', 'unit')->filter(fn ($record) => in_array($record->payload['scope'] ?? 'shared', $access->readableScopes($shop, $request->user()), true)
            && ($query === '' || mb_stripos($record->payload['name'], $query) !== false));
        return view('seller.categories.index', ['shop' => $shop, 'categories' => $categories, 'units' => $units,
            'taxonomyAccess' => $access, 'createScopes' => $access->creatableScopes($shop, $request->user()),
            'defaultScope' => $access->defaultScope($shop, $request->user()), 'tab' => $request->query('tab') === 'units' ? 'units' : 'categories']);
    }

    public function store(ShopCategoryRequest $request, Shop $shop)
    {
        return app(CatalogTaxonomyWebCommands::class)->execute($request, $shop, 'category', $request->validated());
    }

    public function update(ShopCategoryRequest $request, Shop $shop, ShopCategory $category)
    {
        abort_unless($category->shop_id === $shop->id, 404);
        return app(CatalogTaxonomyWebCommands::class)->execute($request, $shop, 'category', $request->validated(), $category);
    }

    public function destroy(Request $request, Shop $shop, ShopCategory $category)
    {
        abort_unless($category->shop_id === $shop->id, 404);
        abort_unless(app(CatalogTaxonomyAccessService::class)->canModifyCategory($category, $request->user()), 403);
        $validated = $request->validate(['expected_revision' => ['sometimes', 'nullable', 'uuid'], 'client_operation_uuid' => ['sometimes', 'uuid']]);
        $validated['expected_revision'] ??= app(CatalogTaxonomyAccessService::class)->categoryRecord($category)?->payload['revision'] ?? null;
        return app(CatalogTaxonomyWebCommands::class)->execute($request, $shop, 'category', $validated, $category, deleted: true);
    }

    private function attributes(array $input, Shop $shop, ?ShopCategory $category = null): array
    {
        $base = Str::slug($input['slug'] ?? $input['name']) ?: 'categoria';
        $slug = $base;
        $suffix = 2;

        while ($shop->categories()->where('slug', $slug)->when($category, fn ($categories) => $categories->whereKeyNot($category))->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return [...$input, 'slug' => $slug, 'sort_order' => $input['sort_order'] ?? 0];
    }
}
