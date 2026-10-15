<?php

use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

uses(RefreshDatabase::class);

function navigationRestrictedReadFixture(string $permission, string $productName = 'QA_NAV_REPORT_ITEM'): array
{
    $owner = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($owner)->create(['business_type' => 'perfume_store']);
    $seller = User::factory()->create();
    ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $seller->id, 'is_active' => true,
        'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions' => [$permission]]);
    $product = Product::factory()->for($shop)->create(['name' => $productName, 'price' => '300.00', 'sale_unit' => 'unit']);
    ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 4, 'cost_price' => '79.31']);
    app(InventoryService::class)->recordSale($product, 1, 'QA_NAV_Only', $owner->id, true, null, 'cash');

    return [$shop, $seller];
}

test('reports only keeps operational reads while financial results stay absent in Web and API', function () {
    [$shop, $seller] = navigationRestrictedReadFixture('reports');
    $token = $seller->createToken('qa-nav-reports')->plainTextToken;
    $response = $this->withToken($token)->getJson('/api/v1/shops/'.$shop->public_id.'/features/reports')->assertOk();
    $payload = json_encode($response->json('module'), JSON_THROW_ON_ERROR);
    expect($payload)->toContain('QA_NAV_REPORT_ITEM');
    foreach (['220.69', '79.31', '237.93', 'Ganancia bruta', 'Inventario al costo'] as $forbidden) {
        expect($payload)->not->toContain($forbidden);
    }
    $this->actingAs($seller)->get(route('seller.shops.feature', [$shop, 'feature' => 'reports']))->assertOk()
        ->assertSee('QA_NAV_REPORT_ITEM')->assertDontSee('220.69')->assertDontSee('237.93')->assertDontSee('Ganancia bruta');
    $this->get(route('seller.shops.business', $shop))->assertForbidden();
});

test('reports only CSV and XLSX share the same nonfinancial projection', function () {
    [$shop, $seller] = navigationRestrictedReadFixture('reports');
    $this->actingAs($seller);
    $csv = $this->get(route('seller.shops.reports.export', [$shop, 'format' => 'csv']))->assertOk()->streamedContent();
    expect($csv)->toContain('QA_NAV_REPORT_ITEM');
    foreach (['79.31', '220.69', '237.93', 'Costo FIFO', 'Ganancia bruta', 'Margen'] as $forbidden) {
        expect($csv)->not->toContain($forbidden);
    }
    $response = $this->get(route('seller.shops.reports.export', [$shop, 'format' => 'xlsx']))->assertOk();
    $directory = base_path('qa/artifacts/navigation-reports');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $path = $directory.'/'.Str::uuid().'.xlsx';
    file_put_contents($path, $response->streamedContent());
    $book = (new Xlsx)->load($path);
    $values = json_encode($book->getActiveSheet()->toArray(null, false, false, false), JSON_THROW_ON_ERROR);
    expect($values)->toContain('QA_NAV_REPORT_ITEM');
    foreach (['79.31', '220.69', '237.93', 'Costo FIFO', 'Ganancia bruta', 'Margen'] as $forbidden) {
        expect($values)->not->toContain($forbidden);
    }
    $book->disconnectWorksheets();
});

test('services only cannot read input costs or profit through its feature read model', function () {
    [$shop, $seller] = navigationRestrictedReadFixture('services');
    $service = Product::factory()->for($shop)->create(['name' => 'QA_NAV_SERVICE_OFFER', 'price' => '150.00', 'sale_unit' => 'service']);
    ProductInventory::create(['product_id' => $service->id, 'track_inventory' => false, 'stock_quantity' => 0, 'cost_price' => '79.31']);
    $token = $seller->createToken('qa-nav-services')->plainTextToken;
    $payload = $this->withToken($token)->getJson('/api/v1/shops/'.$shop->public_id.'/features/services')->assertOk()->json('module');
    expect(json_encode($payload, JSON_THROW_ON_ERROR))->toContain('QA_NAV_SERVICE_OFFER');
    foreach (['79.31', 'Ganancia'] as $forbidden) {
        expect(json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain($forbidden);
    }
});

test('explicit finance permission restores financial report fields', function () {
    [$shop, $seller] = navigationRestrictedReadFixture('reports');
    $shop->sellers()->where('user_id', $seller->id)->firstOrFail()->update(['menu_permissions' => ['reports', 'finance']]);
    $csv = $this->actingAs($seller)->get(route('seller.shops.reports.export', [$shop, 'format' => 'csv']))->assertOk()->streamedContent();
    expect($csv)->toContain('Costo FIFO', '79.31', '220.69');
});

test('exported product labels cannot become spreadsheet formulas', function () {
    [$shop, $seller] = navigationRestrictedReadFixture('reports', '=1+1');
    $csv = $this->actingAs($seller)->get(route('seller.shops.reports.export', [$shop, 'format' => 'csv']))->assertOk()->streamedContent();
    expect($csv)->toContain("'=1+1");
});

test('pricing and price health have independent read permissions without leaking costs', function (string $permission, string $sibling) {
    [$shop, $seller] = navigationRestrictedReadFixture($permission, 'QA_NAV_PRICE_ONLY');
    $this->withToken($seller->createToken('qa-pricing')->plainTextToken);
    $payload = $this->getJson('/api/v1/shops/'.$shop->public_id.'/features/'.$permission)->assertOk()->json('module');
    expect(json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain('79.31', '220.69');
    $this->getJson('/api/v1/shops/'.$shop->public_id.'/features/'.$sibling)->assertForbidden();
    $this->actingAs($seller)->get(route('seller.shops.feature', [$shop, 'feature' => $permission]))->assertOk()->assertDontSee('79.31');
    $this->get(route('seller.shops.feature', [$shop, 'feature' => $sibling]))->assertForbidden();
    if ($permission === 'price_health') {
        $this->postJson('/api/v1/shops/'.$shop->public_id.'/pricing/recalculate')->assertForbidden();
        $this->postJson(route('seller.shops.pricing.bulk-rule', $shop))->assertForbidden();
    }
})->with([['price_health', 'pricing'], ['pricing', 'price_health']]);
