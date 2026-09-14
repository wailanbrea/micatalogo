<?php

use App\Enums\ProductModerationStatus;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public products filter by their shops shipping availability', function () {
    $shippingShop = Shop::factory()->create(['offers_shipping' => true]);
    $pickupShop = Shop::factory()->create(['offers_shipping' => false]);
    $shippingProduct = Product::factory()->for($shippingShop)->create(['name' => 'Producto con envio', 'moderation_status' => ProductModerationStatus::Active]);
    $pickupProduct = Product::factory()->for($pickupShop)->create(['name' => 'Producto sin envio', 'moderation_status' => ProductModerationStatus::Active]);

    $this->get('/?shipping=available')->assertOk()->assertSee($shippingProduct->name)->assertDontSee($pickupProduct->name);
    $this->get('/?shipping=unavailable')->assertOk()->assertSee($pickupProduct->name)->assertDontSee($shippingProduct->name);
});
