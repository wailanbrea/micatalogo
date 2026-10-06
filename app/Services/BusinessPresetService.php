<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Str;

class BusinessPresetService
{
    public function __construct(private readonly BusinessProfileService $profiles) {}

    public function apply(Shop $shop): void
    {
        $profile = $this->profiles->types()[$this->profiles->normalizeType($shop->business_type)] ?? [];
        foreach ($profile['categories'] ?? [] as $sort => $name) {
            $shop->categories()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $sort, 'status' => 'active']
            );
        }
        if (! $shop->business_profile_version) {
            $shop->forceFill(['business_profile_version' => 1])->save();
        }
    }
}
