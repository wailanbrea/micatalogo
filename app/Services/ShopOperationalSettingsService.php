<?php

namespace App\Services;

use App\Models\Shop;

class ShopOperationalSettingsService
{
    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'currency' => 'DOP',
            'currency_symbol' => 'RD$',
            'timezone' => 'America/Santo_Domingo',
            'tax_rate' => null,
            'business_rnc' => null,
            'employee_count' => null,
            'payment_methods' => ['cash', 'bank_transfer', 'card'],
            'receipt' => [
                'show_logo' => true,
                'show_customer' => true,
                'show_seller' => true,
                'show_notes' => true,
            ],
            'fiscal' => [
                'enabled' => false,
                'invoice_type' => 'consumer',
            ],
            'credit' => [
                'enabled' => true,
                'default_days' => 30,
                'allow_partial_payments' => true,
            ],
            'orders' => [
                'enabled' => true,
            ],
            'quick_service' => [
                'enabled' => true,
            ],
            'recipes' => [
                'enabled' => false,
            ],
            'wholesale' => [
                'enabled' => false,
                'minimum_quantity' => 6,
            ],
            'purchases' => [
                'allow_partial_receive' => true,
                'require_supplier' => false,
            ],
            'shipping' => [
                'enabled' => false,
                'types' => ['pickup', 'delivery'],
            ],
            'decants' => [
                'enabled' => false,
                'default_ml' => [5, 10, 30],
                'as_cover' => false,
                'section_text' => null,
            ],
            'catalog' => [
                'sort' => 'name_asc',
                'offers_first' => true,
                'hide_out_of_stock' => false,
                'show_stock' => false,
                'allow_backorder' => false,
            ],
            'marketing' => [
                'meta_pixel' => null,
                'tiktok_pixel' => null,
                'ga4' => null,
                'google_site_verification' => null,
            ],
            'images' => [
                'max_per_product' => 3,
                'auto_optimize' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function forShop(Shop $shop): array
    {
        return $this->normalize($shop->operational_settings ?? []);
    }

    /** @param array<string, mixed> $settings */
    public function normalizeForStorage(array $settings): array
    {
        return $this->normalize($settings);
    }

    /** @param array<string, mixed> $input */
    public function update(Shop $shop, array $input): array
    {
        $settings = $this->normalize(array_replace_recursive($this->forShop($shop), $input));
        $shop->update(['operational_settings' => $settings]);

        return $settings;
    }

    public function taxRate(Shop $shop): float
    {
        return (float) ($this->forShop($shop)['tax_rate'] ?? 0);
    }

    /** @param array<string, mixed> $settings */
    private function normalize(array $settings): array
    {
        $merged = array_replace_recursive($this->defaults(), $settings);

        $merged['currency'] = strtoupper(substr((string) $merged['currency'], 0, 3)) ?: 'DOP';
        $merged['currency_symbol'] = trim((string) $merged['currency_symbol']) ?: 'RD$';
        $merged['timezone'] = in_array($merged['timezone'], timezone_identifiers_list(), true)
            ? $merged['timezone']
            : 'America/Santo_Domingo';
        $merged['tax_rate'] = $merged['tax_rate'] === null || $merged['tax_rate'] === ''
            ? null
            : round(max(0, min(100, (float) $merged['tax_rate'])), 2);
        $merged['business_rnc'] = filled($merged['business_rnc']) ? trim((string) $merged['business_rnc']) : null;
        $merged['employee_count'] = $merged['employee_count'] === null || $merged['employee_count'] === ''
            ? null
            : max(0, min(100000, (int) $merged['employee_count']));

        $available = array_keys(config('catalog.payment_methods', []));
        $methods = array_values(array_unique(array_filter(
            (array) ($merged['payment_methods'] ?? []),
            static fn (mixed $method): bool => in_array($method, $available, true)
        )));
        $merged['payment_methods'] = array_values(array_unique(['cash', ...$methods]));

        foreach (['show_logo', 'show_customer', 'show_seller', 'show_notes'] as $key) {
            $merged['receipt'][$key] = (bool) $merged['receipt'][$key];
        }
        $merged['fiscal']['enabled'] = (bool) $merged['fiscal']['enabled'];
        $merged['fiscal']['invoice_type'] = in_array($merged['fiscal']['invoice_type'], ['consumer', 'credit', 'special'], true)
            ? $merged['fiscal']['invoice_type']
            : 'consumer';
        $merged['credit']['enabled'] = (bool) $merged['credit']['enabled'];
        $merged['credit']['allow_partial_payments'] = (bool) $merged['credit']['allow_partial_payments'];
        $merged['quick_service']['enabled'] = (bool) $merged['quick_service']['enabled'];
        $merged['recipes']['enabled'] = (bool) $merged['recipes']['enabled'];
        $merged['wholesale']['enabled'] = (bool) $merged['wholesale']['enabled'];
        $merged['purchases']['allow_partial_receive'] = (bool) $merged['purchases']['allow_partial_receive'];
        $merged['purchases']['require_supplier'] = (bool) $merged['purchases']['require_supplier'];
        $merged['shipping']['enabled'] = (bool) $merged['shipping']['enabled'];
        $merged['shipping']['types'] = array_values(array_intersect(
            ['pickup', 'delivery'],
            array_values(array_unique((array) ($merged['shipping']['types'] ?? [])))
        ));
        $merged['images']['auto_optimize'] = (bool) $merged['images']['auto_optimize'];

        // Older records used requires_confirmation while the mobile/web
        // settings contract exposes the module as enabled. Normalize both
        // shapes so an existing store does not silently lose the toggle.
        $orders = (array) ($merged['orders'] ?? []);
        $merged['orders'] = [
            'enabled' => array_key_exists('enabled', $orders)
                ? (bool) $orders['enabled']
                : (bool) ($orders['requires_confirmation'] ?? true),
        ];

        $merged['credit']['default_days'] = max(0, min(3650, (int) ($merged['credit']['default_days'] ?? 30)));
        $merged['wholesale']['minimum_quantity'] = max(1, min(100000, (int) ($merged['wholesale']['minimum_quantity'] ?? 6)));
        $merged['images']['max_per_product'] = max(1, min(20, (int) ($merged['images']['max_per_product'] ?? 3)));
        $merged['decants']['default_ml'] = array_values(array_unique(array_filter(
            array_map(static fn (mixed $ml): int => (int) $ml, (array) ($merged['decants']['default_ml'] ?? [])),
            static fn (int $ml): bool => $ml > 0 && $ml <= 10000
        )));
        $merged['decants']['enabled'] = (bool) $merged['decants']['enabled'];
        $merged['decants']['as_cover'] = (bool) $merged['decants']['as_cover'];
        $merged['decants']['section_text'] = filled($merged['decants']['section_text']) ? trim((string) $merged['decants']['section_text']) : null;

        $merged['catalog']['sort'] = in_array($merged['catalog']['sort'], ['name_asc', 'recent', 'price_asc', 'price_desc'], true)
            ? $merged['catalog']['sort']
            : 'name_asc';
        foreach (['offers_first', 'hide_out_of_stock', 'show_stock', 'allow_backorder'] as $catalogKey) {
            $merged['catalog'][$catalogKey] = (bool) $merged['catalog'][$catalogKey];
        }
        $merged['marketing']['meta_pixel'] = filled($merged['marketing']['meta_pixel']) ? trim((string) $merged['marketing']['meta_pixel']) : null;
        $merged['marketing']['tiktok_pixel'] = filled($merged['marketing']['tiktok_pixel']) ? trim((string) $merged['marketing']['tiktok_pixel']) : null;
        $merged['marketing']['ga4'] = filled($merged['marketing']['ga4']) ? trim((string) $merged['marketing']['ga4']) : null;
        $merged['marketing']['google_site_verification'] = filled($merged['marketing']['google_site_verification']) ? trim((string) $merged['marketing']['google_site_verification']) : null;

        return $merged;
    }
}
