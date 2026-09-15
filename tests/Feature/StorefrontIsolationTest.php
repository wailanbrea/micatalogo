<?php

namespace Tests\Feature;

use App\Enums\ProductModerationStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_to_store_only_sees_products_from_that_store(): void
    {
        $sellerA = User::factory()->create();
        $shopA = Shop::factory()->create([
            'user_id' => $sellerA->id,
            'name' => 'Boutique Elegance',
            'slug' => 'boutique-elegance',
            'status' => 'active',
            'whatsapp_country_code' => '1809',
            'whatsapp_number' => '5551111',
        ]);

        $sellerB = User::factory()->create();
        $shopB = Shop::factory()->create([
            'user_id' => $sellerB->id,
            'name' => 'Zapatería El Paso',
            'slug' => 'zapateria-el-paso',
            'status' => 'active',
            'whatsapp_country_code' => '1829',
            'whatsapp_number' => '5552222',
        ]);

        $productA1 = Product::factory()->create([
            'shop_id' => $shopA->id,
            'name' => 'Vestido de Gala Seda',
            'slug' => 'vestido-de-gala-seda',
            'price' => 7500,
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $productA2 = Product::factory()->create([
            'shop_id' => $shopA->id,
            'name' => 'Blusa de Encaje',
            'slug' => 'blusa-de-encaje',
            'price' => 2800,
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $productB = Product::factory()->create([
            'shop_id' => $shopB->id,
            'name' => 'Botas de Montaña Cuero',
            'slug' => 'botas-de-montana-cuero',
            'price' => 6200,
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $response = $this->get(route('shops.show', $shopA));

        $response->assertOk();
        // Assert Shop A details and products are rendered
        $response->assertSee('Boutique Elegance');
        $response->assertSee('Vestido de Gala Seda');
        $response->assertSee('Blusa de Encaje');
        $response->assertSee('5551111');

        // Assert Shop B details and products are completely absent
        $response->assertDontSee('Zapatería El Paso');
        $response->assertDontSee('Botas de Montaña Cuero');
        $response->assertDontSee('5552222');
    }

    public function test_internal_shop_search_only_returns_products_of_that_shop(): void
    {
        $sellerA = User::factory()->create();
        $shopA = Shop::factory()->create(['user_id' => $sellerA->id, 'status' => 'active', 'name' => 'Tienda Tech A']);

        $sellerB = User::factory()->create();
        $shopB = Shop::factory()->create(['user_id' => $sellerB->id, 'status' => 'active', 'name' => 'Tienda Tech B']);

        // Both shops have a product named "Laptop Pro Gamer"
        $prodA = Product::factory()->create([
            'shop_id' => $shopA->id,
            'name' => 'Laptop Pro Gamer - Edición Alfa',
            'slug' => 'laptop-pro-gamer-alfa',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $prodB = Product::factory()->create([
            'shop_id' => $shopB->id,
            'name' => 'Laptop Pro Gamer - Edición Beta',
            'slug' => 'laptop-pro-gamer-beta',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        // Search in Shop A
        $responseA = $this->get(route('shops.show', ['shop' => $shopA, 'q' => 'Laptop Pro Gamer']));
        $responseA->assertOk();
        $responseA->assertSee('Laptop Pro Gamer - Edición Alfa');
        $responseA->assertDontSee('Laptop Pro Gamer - Edición Beta');

        // Search in Shop B
        $responseB = $this->get(route('shops.show', ['shop' => $shopB, 'q' => 'Laptop Pro Gamer']));
        $responseB->assertOk();
        $responseB->assertSee('Laptop Pro Gamer - Edición Beta');
        $responseB->assertDontSee('Laptop Pro Gamer - Edición Alfa');
    }

    public function test_shop_category_filtering_is_strictly_scoped_to_the_shop(): void
    {
        $sellerA = User::factory()->create();
        $shopA = Shop::factory()->create(['user_id' => $sellerA->id, 'status' => 'active']);

        $sellerB = User::factory()->create();
        $shopB = Shop::factory()->create(['user_id' => $sellerB->id, 'status' => 'active']);

        $catA = ShopCategory::factory()->create([
            'shop_id' => $shopA->id,
            'name' => 'Pantalones',
            'slug' => 'pantalones',
        ]);

        $catB = ShopCategory::factory()->create([
            'shop_id' => $shopB->id,
            'name' => 'Pantalones',
            'slug' => 'pantalones',
        ]);

        $prodA = Product::factory()->create([
            'shop_id' => $shopA->id,
            'shop_category_id' => $catA->id,
            'name' => 'Pantalón Jean de Shop A',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $prodB = Product::factory()->create([
            'shop_id' => $shopB->id,
            'shop_category_id' => $catB->id,
            'name' => 'Pantalón Jean de Shop B',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $response = $this->get(route('shops.show', ['shop' => $shopA, 'categoria' => 'pantalones']));
        $response->assertOk();
        $response->assertSee('Pantalón Jean de Shop A');
        $response->assertDontSee('Pantalón Jean de Shop B');
    }

    public function test_product_detail_and_related_products_belong_strictly_to_same_shop(): void
    {
        $shopA = Shop::factory()->create(['status' => 'active', 'name' => 'Boutique Alfa']);
        $shopB = Shop::factory()->create(['status' => 'active', 'name' => 'Boutique Beta']);

        $catA = ShopCategory::factory()->create(['shop_id' => $shopA->id, 'name' => 'Vestidos']);

        $targetProduct = Product::factory()->create([
            'shop_id' => $shopA->id,
            'shop_category_id' => $catA->id,
            'name' => 'Vestido Rojo Fiesta',
            'slug' => 'vestido-rojo-fiesta',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $relatedA = Product::factory()->create([
            'shop_id' => $shopA->id,
            'shop_category_id' => $catA->id,
            'name' => 'Vestido Negro Noche',
            'slug' => 'vestido-negro-noche',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $productB = Product::factory()->create([
            'shop_id' => $shopB->id,
            'name' => 'Vestido Azul Competencia',
            'slug' => 'vestido-azul-competencia',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $response = $this->get(route('products.show', [$shopA, $targetProduct]));
        $response->assertOk();
        $response->assertSee('Vestido Rojo Fiesta');
        $response->assertSee('Vestido Negro Noche');
        $response->assertSee('Más productos de Boutique Alfa');
        $response->assertDontSee('Vestido Azul Competencia');
        $response->assertDontSee('Boutique Beta');
    }

    public function test_tampered_url_with_product_of_another_shop_returns_404(): void
    {
        $shopA = Shop::factory()->create(['status' => 'active', 'slug' => 'tienda-alfa']);
        $shopB = Shop::factory()->create(['status' => 'active', 'slug' => 'tienda-beta']);

        $productB = Product::factory()->create([
            'shop_id' => $shopB->id,
            'name' => 'Producto de Tienda Beta',
            'slug' => 'producto-de-tienda-beta',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        // Attempting to access Product B through Shop A's URL
        $tamperedUrl = "/tienda/{$shopA->slug}/producto/{$productB->slug}";

        $response = $this->get($tamperedUrl);

        // MUST be 404 (never 200, never 302 redirect)
        $response->assertNotFound();
    }

    public function test_whatsapp_tracking_returns_404_if_product_does_not_belong_to_shop(): void
    {
        $shopA = Shop::factory()->create(['status' => 'active', 'slug' => 'tienda-a']);
        $shopB = Shop::factory()->create(['status' => 'active', 'slug' => 'tienda-b']);

        $productB = Product::factory()->create([
            'shop_id' => $shopB->id,
            'slug' => 'producto-b',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $response = $this->get(route('track.wa.product', [$shopA, $productB]));

        $response->assertNotFound();
    }

    public function test_homepage_is_seller_landing_page_without_cross_store_products(): void
    {
        $shop = Shop::factory()->create(['status' => 'active', 'name' => 'Tienda Secreta']);
        Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Articulo Ultra Secreto',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Crea tu catálogo gratis');
        $response->assertSee('Sube tus productos una vez');
        $response->assertSee('Comparte un solo enlace con tus clientes');
        $response->assertSee('Vitrina 100% Aislada');

        // Verify no products or shops are listed
        $response->assertDontSee('Articulo Ultra Secreto');
        $response->assertDontSee('Tienda Secreta');
    }

    public function test_seller_cannot_access_or_manage_another_sellers_shop(): void
    {
        $sellerA = User::factory()->create();
        $shopA = Shop::factory()->create(['user_id' => $sellerA->id]);

        $sellerB = User::factory()->create();
        $shopB = Shop::factory()->create(['user_id' => $sellerB->id]);

        // Seller A tries to edit Shop B
        $response = $this->actingAs($sellerA)->get(route('seller.shops.edit', $shopB));
        $response->assertForbidden();

        // Seller A tries to view products of Shop B
        $responseProducts = $this->actingAs($sellerA)->get(route('seller.shops.products.index', $shopB));
        $responseProducts->assertForbidden();
    }

    public function test_shop_model_has_discovery_enabled_defaulting_to_false(): void
    {
        $shop = Shop::factory()->create();

        $this->assertFalse($shop->discovery_enabled);
    }
}
