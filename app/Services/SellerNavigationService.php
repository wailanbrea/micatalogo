<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** A shared presentation tree, never an authorization substitute. */
class SellerNavigationService
{
    public function __construct(
        private readonly SellerMenuService $menus,
        private readonly BusinessPresentationService $presentation,
    ) {}

    public function contextShop(User $user, mixed $routeShop = null): ?Shop
    {
        if ($routeShop instanceof Shop) {
            return $user->canSellAtShop($routeShop) || $user->isActiveShopAccountant($routeShop) ? $routeShop : null;
        }

        return $user->shops()->first()
            ?? $user->shopMemberships()->with('shop')->where('is_active', true)->first()?->shop
            ?? $user->shopSellerAssignments()->with('shop')->where('is_active', true)->first()?->shop;
    }

    /** @return array<string, mixed> */
    public function forUser(Shop $shop, User $user, string $platform = 'android'): array
    {
        // The sidebar, header and mobile drawer render the same tree. Reuse it
        // only within this HTTP request; never cache permissions across requests.
        $request = request();
        $cacheKey = 'navigation:'.$platform.':'.$shop->getKey().':'.$user->getKey();
        $cached = $request->attributes->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }
        // Gate::before for platform admin must not turn this into a global tenant selector.
        abort_unless($user->ownsShop($shop) || $user->canSellAtShop($shop) || $user->isActiveShopAccountant($shop), 404);
        $visible = $this->menus->visibleForUser($shop, $user);
        $presentation = $this->presentation->resolve($shop);
        $assignedSellerOnly = $user->isAssignedSellerOnly();
        $items = fn (array $definitions): array => array_values(array_filter(array_map(
            function (array $definition) use ($shop, $user, $platform, $visible, $presentation, $assignedSellerOnly): ?array {
                if (($definition['web_only'] ?? false) && $platform !== 'web') {
                    return null;
                }
                if (($definition['manage_shops'] ?? false) && $assignedSellerOnly) {
                    return null;
                }
                if ($definition['taxonomy'] ?? false) {
                    if (! app(CatalogTaxonomyAccessService::class)->canOpen($shop, $user)) return null;
                    if (! in_array('products', $visible, true)) $definition['key'] = in_array('services', $visible, true) ? 'services' : 'decants';
                }
                if ($definition['key'] !== '' && ! ($definition['authenticated'] ?? false) && ! in_array($definition['key'], $visible, true)) {
                    return null;
                }
                if (($definition['owner_only'] ?? false) && ! $this->menus->canManageMenuVisibility($shop, $user)) {
                    return null;
                }
                $route = $definition['route'];
                $feature = $definition['feature'] ?? null;
                if (($definition['manage_route'] ?? false) && ! Gate::forUser($user)->allows('update', $shop)) {
                    $route = 'seller.shops.feature';
                    $feature = $definition['feature_fallback'];
                }
                if ($platform === 'web' && $route === null) {
                    return null;
                }
                $label = $definition['label'];
                $parameters = ($definition['without_shop'] ?? false) ? [] : ['shop' => $shop];
                if ($feature !== null) {
                    $parameters['feature'] = $feature;
                }
                $parameters = [...$parameters, ...($definition['query'] ?? [])];

                return [
                    'id' => $definition['id'], 'key' => $definition['key'], 'label' => $label,
                    'business_label' => isset($definition['term']) ? ($presentation['terminology'][$definition['term']] ?? $label) : $label,
                    'destination' => $definition['android_route'],
                    'url' => $route ? route($route, $parameters) : null,
                    'route' => $route, 'feature' => $feature,
                    'active_routes' => $definition['active_routes'] ?? ($route ? [$route] : []),
                    'external' => $definition['external'] ?? false,
                    'preferred_query' => $definition['preferred_query'] ?? [],
                ];
            }, $definitions
        )));
        $sections = function (array $definitions) use ($items): array {
            $result = [];
            foreach ($definitions as $id => $definition) {
                $children = $items($definition['items']);
                if ($children !== []) {
                    $result[] = ['id' => $id, 'label' => $definition['label'], 'items' => $children]
                        + array_intersect_key($definition, ['requires_local_setting' => true]);
                }
            }

            return $result;
        };

        $manifest = [
            'schema_version' => 1,
            'sections' => $sections(config('seller-navigation.sections')),
            'secondary' => $items(config('seller-navigation.secondary')),
            'contextual_sections' => $sections(config('seller-navigation.contextual')),
        ];

        $manifest = $platform === 'web' ? $this->withActiveDestination($manifest) : $manifest;
        $request->attributes->set($cacheKey, $manifest);

        return $manifest;
    }

    /** Mark only the most specific match, including legacy routes and feature aliases. */
    private function withActiveDestination(array $manifest): array
    {
        $selected = null;
        $bestScore = 0;
        foreach (collect($manifest['sections'])->pluck('items')->flatten(1)->concat($manifest['secondary']) as $item) {
            if ($item['external']) {
                continue;
            }
            $score = 0;
            if ($item['feature'] !== null) {
                $score = request()->routeIs('seller.shops.feature') && request()->route('feature') === $item['feature'] ? 1000 : 0;
            } else {
                foreach ($item['active_routes'] as $pattern) {
                    if (request()->routeIs($pattern)) {
                        $score = max($score, str_contains($pattern, '*') ? strlen($pattern) : 1000);
                    }
                }
            }
            if ($score > 0 && $item['preferred_query'] !== []
                && collect($item['preferred_query'])->every(fn ($value, $key): bool => request()->query($key) === $value)) {
                $score += 1000;
            }
            if ($score > $bestScore) {
                $selected = $item['id'];
                $bestScore = $score;
            }
        }
        foreach ($manifest['sections'] as &$section) {
            foreach ($section['items'] as &$item) {
                $item['active'] = $item['id'] === $selected;
            }
            unset($item);
            $section['active'] = collect($section['items'])->contains('active', true);
        }
        unset($section);
        foreach ($manifest['secondary'] as &$item) {
            $item['active'] = $item['id'] === $selected;
        }
        unset($item);

        return $manifest;
    }
}
