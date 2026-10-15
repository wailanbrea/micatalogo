<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\ShopSeller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function renderedNavigation(string $html, string $format): array
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    $items = [];
    foreach ($xpath->query("//*[@data-navigation-tree='$format']//a[@data-navigation-id]") as $node) {
        $items[$node->getAttribute('data-navigation-id')] = [
            'url' => $node->getAttribute('href'),
            'active' => $node->getAttribute('aria-current') === 'page',
        ];
    }

    return $items;
}

test('desktop and mobile share the same authorized navigation for every role', function (string $role) {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create(['business_type' => 'perfume_store']);
    $actor = $role === 'owner' ? $owner : User::factory()->create();
    if (in_array($role, ['manager', 'accountant'], true)) {
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $actor->id, 'role' => $role, 'is_active' => true]);
    } elseif ($role !== 'owner') {
        ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $actor->id, 'is_active' => true,
            'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions' => [$role]]);
    }
    $html = $this->actingAs($actor)->get(route('seller.shops.summary', $shop))->assertOk()->getContent();
    $desktop = renderedNavigation($html, 'desktop');
    expect($desktop)->not->toBeEmpty()->toBe(renderedNavigation($html, 'mobile'))
        ->and(array_keys($desktop))->toContain('summary', 'account', 'help');

    if ($role === 'sales') {
        expect(array_keys($desktop))->toContain('pos', 'sales_history')->not->toContain('inventory', 'finance', 'services');
    }
    if ($role === 'services') {
        expect(array_keys($desktop))->toContain('services')->not->toContain('products', 'pos', 'finance');
    }
    if ($role === 'reports') {
        expect(array_keys($desktop))->toContain('reports')->not->toContain('finance', 'expenses');
    }
    if ($role === 'accountant') {
        expect(array_keys($desktop))->toContain('finance', 'reports')->not->toContain('shop_menus', 'accountant', 'sellers', 'cash', 'expenses');
    }
})->with(['owner', 'manager', 'accountant', 'sales', 'services', 'reports', 'cash', 'expenses']);

test('a legacy nested route selects exactly one specific destination per navigation tree', function (string $routeName, string $destination) {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create(['business_type' => 'perfume_store']);
    $html = $this->actingAs($owner)->get(route($routeName, $shop))->assertOk()->getContent();
    foreach (['desktop', 'mobile'] as $format) {
        $active = array_keys(array_filter(renderedNavigation($html, $format), fn ($item) => $item['active']));
        expect($active)->toBe([$destination]);
    }
})->with([
    ['seller.shops.summary', 'summary'], ['seller.shops.business', 'finance'],
    ['seller.shops.inventory.lots', 'fifo'], ['seller.shops.products.import.create', 'import'],
    ['seller.shops.products.bulk.create', 'import'], ['seller.shops.menus.edit', 'shop_menus'],
]);

test('account and help remain reachable without permissions while business siblings remain forbidden', function () {
    $shop = Shop::factory()->create(['enabled_menu_keys' => []]);
    $seller = User::factory()->create();
    ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $seller->id, 'is_active' => true,
        'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions' => []]);

    $this->actingAs($seller)->get(route('seller.shops.feature', [$shop, 'feature' => 'account']))->assertOk();
    $this->get(route('seller.shops.feature', [$shop, 'feature' => 'help']))->assertOk();
    $this->get(route('seller.shops.edit', $shop))->assertForbidden();
    $this->get(route('seller.shops.business', $shop))->assertForbidden();
    $this->get(route('seller.shops.feature', [$shop, 'feature' => 'services']))->assertForbidden();
    $other = Shop::factory()->create();
    $this->get(route('seller.shops.feature', [$other, 'feature' => 'account']))->assertForbidden();
});

test('catalog type tabs keep physical products and services separate on legacy URLs', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create(['business_type' => 'perfume_store']);
    Product::factory()->for($shop)->create(['name' => 'QA_NAV_PHYSICAL_ONLY', 'sale_unit' => 'unit']);
    Product::factory()->for($shop)->create(['name' => 'QA_NAV_SERVICE_ONLY', 'sale_unit' => 'service']);

    $this->actingAs($owner)->get(route('seller.shops.products.index', $shop))->assertOk()
        ->assertSee('Tipos de catálogo')->assertSee('QA_NAV_PHYSICAL_ONLY')->assertDontSee('QA_NAV_SERVICE_ONLY');
    $this->get(route('seller.shops.feature', [$shop, 'feature' => 'services']))->assertOk()
        ->assertSee('Tipos de catálogo')->assertSee('QA_NAV_SERVICE_ONLY')->assertDontSee('QA_NAV_PHYSICAL_ONLY');
});
