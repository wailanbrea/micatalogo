<?php

use App\Enums\ProductModerationStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CatalogSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('catalog search service filters by shop shipping availability', function () {
    $shippingShop = Shop::factory()->create(['offers_shipping' => true, 'status' => 'active']);
    $pickupShop = Shop::factory()->create(['offers_shipping' => false, 'status' => 'active']);
    $shippingProduct = Product::factory()->for($shippingShop)->create(['name' => 'Producto con envio', 'moderation_status' => ProductModerationStatus::Active]);
    $pickupProduct = Product::factory()->for($pickupShop)->create(['name' => 'Producto sin envio', 'moderation_status' => ProductModerationStatus::Active]);

    $service = new CatalogSearchService;

    $availableResults = $service->search(['shipping' => 'available']);
    expect($availableResults->pluck('name')->toArray())
        ->toContain('Producto con envio')
        ->not->toContain('Producto sin envio');

    $unavailableResults = $service->search(['shipping' => 'unavailable']);
    expect($unavailableResults->pluck('name')->toArray())
        ->toContain('Producto sin envio')
        ->not->toContain('Producto con envio');
});
