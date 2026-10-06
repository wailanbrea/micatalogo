<?php

namespace App\Services;

use App\Models\Shop;

/**
 * Builds the presentation contract consumed by web and mobile clients.
 *
 * This service deliberately does not grant access. Authorization stays in
 * BusinessProfileService, SellerMenuService and the route/policy guards.
 */
class BusinessPresentationService
{
    public function __construct(private readonly BusinessProfileService $profiles)
    {
    }

    /** @return array<string, mixed> */
    public function resolve(Shop $shop): array
    {
        $profile = $this->profiles->profile($shop);
        $type = $profile['business_type'];
        $archetype = config("business-types.type_archetypes.{$type}", 'general_retail');
        $defaults = config('business-types.presentation_defaults', []);
        $archetypePresentation = config("business-types.presentation_archetypes.{$archetype}", []);
        $typePresentation = config("business-types.types.{$type}.presentation", []);
        $presentation = $this->merge($defaults, $archetypePresentation);
        $presentation = $this->merge($presentation, is_array($typePresentation) ? $typePresentation : []);
        $capabilities = $profile['capabilities'];

        $presentation['archetype'] = $archetype;
        $presentation['dashboard']['widgets'] = $this->filterWidgets($presentation['dashboard']['widgets'] ?? [], $capabilities);
        $presentation['dashboard']['quick_actions'] = $this->filterQuickActions($presentation['dashboard']['quick_actions'] ?? [], $capabilities);
        $presentation['pos']['show_wholesale'] = $this->enabled($capabilities, 'wholesale');
        $presentation['pos']['show_credit'] = $this->enabled($capabilities, 'credit');
        $presentation['pos']['show_inventory'] = $this->enabled($capabilities, 'inventory') && ($presentation['pos']['show_inventory'] ?? true);
        $presentation['catalog']['show_stock'] = $this->enabled($capabilities, 'inventory') && ($presentation['catalog']['show_stock'] ?? true);
        $presentation['inventory']['enabled'] = $this->enabled($capabilities, 'inventory');
        $presentation['customers']['show_credit'] = $this->enabled($capabilities, 'credit');

        return $presentation;
    }

    /** @param array<string, string> $capabilities */
    private function filterWidgets(array $widgets, array $capabilities): array
    {
        $requirements = [
            'sales_today' => ['sales'],
            'collections_today' => ['cash', 'credit'],
            'low_stock' => ['inventory'],
            'receivables' => ['credit'],
            'active_customers' => ['customers'],
        ];

        return array_values(array_filter($widgets, function (string $widget) use ($requirements, $capabilities): bool {
            $required = $requirements[$widget] ?? [];

            return $required === [] || collect($required)->contains(fn (string $key): bool => $this->enabled($capabilities, $key));
        }));
    }

    /** @param array<string, string> $capabilities */
    private function filterQuickActions(array $actions, array $capabilities): array
    {
        $requirements = [
            'new_sale' => 'sales',
            'new_product' => 'products',
            'inventory' => 'inventory',
            'new_customer' => 'customers',
            'collect' => 'credit',
            'open_cash' => 'cash',
        ];

        return array_values(array_filter($actions, function (string $action) use ($requirements, $capabilities): bool {
            $required = $requirements[$action] ?? null;

            return $required === null || $this->enabled($capabilities, $required);
        }));
    }

    /** @param array<string, string> $capabilities */
    private function enabled(array $capabilities, string $key): bool
    {
        return ($capabilities[$key] ?? 'disabled') === 'enabled';
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $override @return array<string, mixed> */
    private function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && ! array_is_list($value) && ! array_is_list($base[$key])) {
                $base[$key] = $this->merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
