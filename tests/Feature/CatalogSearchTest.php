<?php

namespace Tests\Feature;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Models\GlobalCategory;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\CatalogSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_service_prioritizes_exact_match_over_partial_matches(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'status' => 'active']);

        $partial = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Pantalón para Camisa',
            'slug' => 'pantalon-para-camisa',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $exact = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Camisa',
            'slug' => 'camisa',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $prefix = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Camisa de Lino',
            'slug' => 'camisa-de-lino',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $service = new CatalogSearchService;
        $results = $service->search(['q' => 'Camisa']);

        $names = $results->pluck('name')->toArray();

        $this->assertEquals('Camisa', $names[0]);
        $this->assertEquals('Camisa de Lino', $names[1]);
        $this->assertEquals('Pantalón para Camisa', $names[2]);
    }

    public function test_search_service_matches_by_shop_name(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create([
            'user_id' => $seller->id,
            'name' => 'Ferretería El Tornillo',
            'status' => 'active',
        ]);

        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Martillo de Acero',
            'slug' => 'martillo-de-acero',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $service = new CatalogSearchService;
        $results = $service->search(['q' => 'Tornillo']);

        $this->assertCount(1, $results);
        $this->assertEquals('Martillo de Acero', $results->first()->name);
    }

    public function test_search_service_filters_by_global_category(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'status' => 'active']);

        $catTech = GlobalCategory::factory()->create(['name' => 'Tecnología', 'slug' => 'tecnologia']);
        $catHome = GlobalCategory::factory()->create(['name' => 'Hogar', 'slug' => 'hogar']);

        $techProd = Product::factory()->create([
            'shop_id' => $shop->id,
            'global_category_id' => $catTech->id,
            'name' => 'Auriculares Bluetooth',
            'slug' => 'auriculares-bluetooth',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $homeProd = Product::factory()->create([
            'shop_id' => $shop->id,
            'global_category_id' => $catHome->id,
            'name' => 'Lámpara de Mesa',
            'slug' => 'lampara-de-mesa',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $service = new CatalogSearchService;
        $results = $service->search(['categoria' => 'tecnologia']);

        $this->assertTrue($results->contains('id', $techProd->id));
        $this->assertFalse($results->contains('id', $homeProd->id));
    }

    public function test_search_service_excludes_suspended_shops_and_products(): void
    {
        $seller = User::factory()->create();

        $suspendedShop = Shop::factory()->create([
            'user_id' => $seller->id,
            'status' => 'suspended',
            'name' => 'Tienda Suspendida',
        ]);
        Product::factory()->create([
            'shop_id' => $suspendedShop->id,
            'name' => 'Zapato Suspendido',
            'slug' => 'zapato-suspendido',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $activeShop = Shop::factory()->create([
            'user_id' => $seller->id,
            'status' => 'active',
        ]);
        Product::factory()->create([
            'shop_id' => $activeShop->id,
            'name' => 'Zapato No Moderado',
            'slug' => 'zapato-no-moderado',
            'moderation_status' => ProductModerationStatus::Draft,
        ]);

        $service = new CatalogSearchService;
        $results = $service->search(['q' => 'Zapato']);

        $this->assertCount(0, $results);
    }

    public function test_home_page_performs_search_query(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'status' => 'active']);

        Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Laptop Ultra Pro',
            'slug' => 'laptop-ultra-pro',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Sofá Cama 3 Puestos',
            'slug' => 'sofa-cama-3-puestos',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $response = $this->get('/?q=Laptop');

        $response->assertOk();
        $response->assertSee('Resultados para');
        $response->assertSee('"Laptop"', false);
        $response->assertSee('Laptop Ultra Pro');
        $response->assertDontSee('Sofá Cama 3 Puestos');
    }

    public function test_search_service_filters_by_price_range(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'status' => 'active']);

        $cheap = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Cable USB-C Económico',
            'price' => 300,
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $mid = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Teclado Mecánico',
            'price' => 2500,
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $expensive = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Monitor 4K',
            'price' => 18000,
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $service = new CatalogSearchService;

        // Min price filter
        $resultsMin = $service->search(['min_price' => 1000]);
        $this->assertFalse($resultsMin->contains('id', $cheap->id));
        $this->assertTrue($resultsMin->contains('id', $mid->id));
        $this->assertTrue($resultsMin->contains('id', $expensive->id));

        // Max price filter
        $resultsMax = $service->search(['max_price' => 5000]);
        $this->assertTrue($resultsMax->contains('id', $cheap->id));
        $this->assertTrue($resultsMax->contains('id', $mid->id));
        $this->assertFalse($resultsMax->contains('id', $expensive->id));

        // Range filter
        $resultsRange = $service->search(['min_price' => 500, 'max_price' => 5000]);
        $this->assertFalse($resultsRange->contains('id', $cheap->id));
        $this->assertTrue($resultsRange->contains('id', $mid->id));
        $this->assertFalse($resultsRange->contains('id', $expensive->id));
    }

    public function test_search_service_filters_by_stock_availability(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'status' => 'active']);

        $inStock = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Producto Disponible',
            'availability_status' => ProductAvailabilityStatus::Available,
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $outOfStock = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Producto Agotado',
            'availability_status' => ProductAvailabilityStatus::OutOfStock,
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $service = new CatalogSearchService;
        $results = $service->search(['stock' => 'available']);

        $this->assertTrue($results->contains('id', $inStock->id));
        $this->assertFalse($results->contains('id', $outOfStock->id));
    }

    public function test_search_service_filters_by_shop(): void
    {
        $seller = User::factory()->create();
        $shop1 = Shop::factory()->create(['user_id' => $seller->id, 'status' => 'active', 'slug' => 'tienda-alfa']);
        $shop2 = Shop::factory()->create(['user_id' => $seller->id, 'status' => 'active', 'slug' => 'tienda-beta']);

        $prod1 = Product::factory()->create([
            'shop_id' => $shop1->id,
            'name' => 'Producto de Alfa',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $prod2 = Product::factory()->create([
            'shop_id' => $shop2->id,
            'name' => 'Producto de Beta',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $service = new CatalogSearchService;
        $results = $service->search(['tienda' => 'tienda-alfa']);

        $this->assertTrue($results->contains('id', $prod1->id));
        $this->assertFalse($results->contains('id', $prod2->id));
    }

    public function test_search_service_sorts_products(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'status' => 'active']);

        Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Articulo B',
            'price' => 500,
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Articulo A',
            'price' => 100,
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $service = new CatalogSearchService;

        // Sort price asc
        $asc = $service->search(['sort' => 'price_asc'])->pluck('name')->toArray();
        $this->assertEquals('Articulo A', $asc[0]);
        $this->assertEquals('Articulo B', $asc[1]);

        // Sort price desc
        $desc = $service->search(['sort' => 'price_desc'])->pluck('name')->toArray();
        $this->assertEquals('Articulo B', $desc[0]);
        $this->assertEquals('Articulo A', $desc[1]);

        // Sort name asc
        $nameAsc = $service->search(['sort' => 'name_asc'])->pluck('name')->toArray();
        $this->assertEquals('Articulo A', $nameAsc[0]);
        $this->assertEquals('Articulo B', $nameAsc[1]);
    }

    public function test_home_page_displays_filters_and_active_chips(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'status' => 'active', 'slug' => 'tienda-demo']);

        Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Camisa Formal Azul',
            'price' => 1500,
            'availability_status' => ProductAvailabilityStatus::Available,
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        // Request with active filters
        $response = $this->get('/?q=Camisa&min_price=1000&max_price=2000&stock=available');

        $response->assertOk();
        $response->assertSee('Filtros aplicados:');
        $response->assertSee('Solo en stock');
        $response->assertSee('RD$ 1,000 - 2,000');
        $response->assertSee('Limpiar todos');
        $response->assertSee('Camisa Formal Azul');
    }
}
