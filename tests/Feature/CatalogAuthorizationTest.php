<?php

use App\Models\GlobalCategory;
use App\Models\Product;
use App\Models\ProductDailyMetric;
use App\Models\ProductImage;
use App\Models\Report;
use App\Models\Shop;
use App\Models\ShopCategory;
use App\Models\ShopDailyMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a seller cannot access another sellers catalog resources', function () {
    $seller = User::factory()->create();
    $shop = Shop::factory()->create();
    $category = ShopCategory::factory()->for($shop)->create();
    $product = Product::factory()->for($shop)->create();
    $image = ProductImage::factory()->for($product)->create();
    $shopMetric = (new ShopDailyMetric)->setRelation('shop', $shop);
    $productMetric = (new ProductDailyMetric)->setRelation('product', $product);

    expect($seller->can('view', $shop))->toBeFalse()
        ->and($seller->can('update', $category))->toBeFalse()
        ->and($seller->can('delete', $product))->toBeFalse()
        ->and($seller->can('view', $image))->toBeFalse()
        ->and($seller->can('view', $shopMetric))->toBeFalse()
        ->and($seller->can('view', $productMetric))->toBeFalse();
});

test('an administrator can manage catalog resources and global categories', function () {
    $admin = User::factory()->admin()->create();
    $shop = Shop::factory()->create();
    $product = Product::factory()->for($shop)->create();
    $category = GlobalCategory::factory()->create();
    $report = Report::factory()->create();

    expect($admin->can('update', $shop))->toBeTrue()
        ->and($admin->can('delete', $product))->toBeTrue()
        ->and($admin->can('update', $category))->toBeTrue()
        ->and($admin->can('view', $report))->toBeTrue();
});

test('ownership scopes only return the sellers resources', function () {
    $seller = User::factory()->create();
    $ownedShop = Shop::factory()->for($seller)->create();
    $otherShop = Shop::factory()->create();
    $ownedCategory = ShopCategory::factory()->for($ownedShop)->create();
    $ownedProduct = Product::factory()->for($ownedShop)->create();

    expect(Shop::ownedBy($seller)->pluck('id')->all())->toBe([$ownedShop->id])
        ->and(ShopCategory::ownedBy($seller)->pluck('id')->all())->toBe([$ownedCategory->id])
        ->and(Product::ownedBy($seller)->pluck('id')->all())->toBe([$ownedProduct->id])
        ->and($otherShop->user_id)->not->toBe($seller->id);
});
