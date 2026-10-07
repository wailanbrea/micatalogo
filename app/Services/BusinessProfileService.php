<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\User;

class BusinessProfileService
{
    /** @var array<string, array<string, string>> */
    private array $capabilitiesCache = [];

    public function types(): array
    {
        return config('business-types.types', []);
    }

    public function normalizeType(?string $type): string
    {
        return array_key_exists($type, $this->types()) ? $type : 'general_retail';
    }

    public function profile(Shop $shop): array
    {
        $type = $this->normalizeType($shop->business_type);
        $profile = $this->types()[$type] ?? $this->types()['general_retail'];

        return [
            'business_type' => $type,
            'business_type_label' => $profile['label'],
            'product_fields' => $profile['product_fields'] ?? [],
            'categories' => $profile['categories'] ?? [],
            'capabilities' => $this->capabilities($shop),
            'profile_version' => $shop->business_profile_version ?: 1,
        ];
    }

    public function capabilities(Shop $shop): array
    {
        $cacheKey = (string) ($shop->getKey() ?? spl_object_id($shop));
        if (array_key_exists($cacheKey, $this->capabilitiesCache)) {
            return $this->capabilitiesCache[$cacheKey];
        }

        $type = $this->normalizeType($shop->business_type);
        $configured = $this->types()[$type]['capabilities'] ?? $this->types()['general_retail']['capabilities'];
        // Services are catalog offers without stock. They are available across
        // business profiles because a product shop can also sell installation,
        // delivery, repair, setup, or other billable work.
        $configured['services'] ??= true;
        $overrides = is_array($shop->business_capability_overrides) ? $shop->business_capability_overrides : [];
        $legacyDecants = $shop->exists && $shop->products()->where('sale_unit', 'decant')->exists();
        $result = [];

        foreach ($configured as $key => $state) {
            if (array_key_exists($key, $overrides)) {
                $state = $overrides[$key];
            }
            if ($key === 'decants' && $legacyDecants && $type === 'general_retail') {
                $state = true;
            }
            // Existing general-retail shops that already sell decants keep that
            // capability even if their plan predates the new capability matrix.
            // This is a compatibility exception, not a way to grant new stores
            // access to a paid feature.
            $result[$key] = $key === 'decants' && $legacyDecants && $type === 'general_retail'
                ? 'enabled'
                : $this->effectiveState($shop->user, $key, $state);
        }

        foreach (config('business-types.implemented', []) as $key) {
            $result[$key] ??= $key === 'decants' && $legacyDecants && $type === 'general_retail'
                ? 'enabled'
                : $this->effectiveState($shop->user, $key, false);
        }

        return $this->capabilitiesCache[$cacheKey] = $result;
    }

    public function allows(Shop $shop, string $capability): bool
    {
        return ($this->capabilities($shop)[$capability] ?? 'disabled') === 'enabled';
    }

    public function isImplemented(string $capability): bool
    {
        return in_array($capability, config('business-types.implemented', []), true);
    }

    private function effectiveState(User $user, string $key, mixed $state): string
    {
        if ($state === 'unsupported') {
            return 'unsupported';
        }
        if ($state !== true) {
            return 'disabled';
        }
        if (! $this->isImplemented($key)) {
            return 'unsupported';
        }
        if (in_array($key, ['decants', 'wholesale'], true) && ! app(PlanLimitsService::class)->hasFeature($user, $key === 'decants' ? 'decants' : 'wholesale_pricing')) {
            return 'disabled';
        }

        return 'enabled';
    }
}
