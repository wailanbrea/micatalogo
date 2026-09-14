<?php

namespace Tests\Feature;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicShopStorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_shop_renders_public_profile_with_products(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create([
            'user_id' => $user->id,
            'name' => 'Brea Calzados',
            'slug' => 'brea-calzados',
            'description' => 'Tienda de zapatos finos',
            'whatsapp_country_code' => '1809',
            'whatsapp_number' => '5551234',
            'instagram' => '@breacalzados',
            'status' => 'active',
        ]);

        $category = ShopCategory::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Deportivos',
            'slug' => 'deportivos',
        ]);

        $product1 = Product::factory()->create([
            'shop_id' => $shop->id,
            'shop_category_id' => $category->id,
            'name' => 'Nike Air Runner',
            'slug' => 'nike-air-runner',
            'price' => 4500,
            'moderation_status' => ProductModerationStatus::Active,
            'availability_status' => ProductAvailabilityStatus::Available,
        ]);

        $product2 = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Mocasines Cuero',
            'slug' => 'mocasines-cuero',
            'price' => 3200,
            'moderation_status' => ProductModerationStatus::Active,
            'availability_status' => ProductAvailabilityStatus::OutOfStock,
        ]);

        $response = $this->get('/tienda/brea-calzados');

        $response->assertOk();
        $response->assertSee('Brea Calzados');
        $response->assertSee('Tienda de zapatos finos');
        $response->assertSee('Nike Air Runner');
        $response->assertSee('Mocasines Cuero');
        $response->assertSee('RD$ 4,500');
        $response->assertSee('RD$ 3,200');
        $response->assertSee('Deportivos');
        $response->assertSee('Contactar por WhatsApp');
        $response->assertSee('https://wa.me/18095551234', false);
    }

    public function test_suspended_shop_returns_404(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create([
            'user_id' => $user->id,
            'slug' => 'tienda-suspendida',
            'status' => 'suspended',
        ]);

        $response = $this->get('/tienda/tienda-suspendida');

        $response->assertNotFound();
    }

    public function test_shop_category_filtering_works(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create([
            'user_id' => $user->id,
            'slug' => 'tienda-ropa',
            'status' => 'active',
        ]);

        $catPants = ShopCategory::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Pantalones',
            'slug' => 'pantalones',
        ]);

        $catShirts = ShopCategory::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Camisas',
            'slug' => 'camisas',
        ]);

        Product::factory()->create([
            'shop_id' => $shop->id,
            'shop_category_id' => $catPants->id,
            'name' => 'Jean Clásico Slim',
            'slug' => 'jean-clasico-slim',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        Product::factory()->create([
            'shop_id' => $shop->id,
            'shop_category_id' => $catShirts->id,
            'name' => 'Camisa Lino Blanca',
            'slug' => 'camisa-lino-blanca',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $response = $this->get('/tienda/tienda-ropa?categoria=pantalones');

        $response->assertOk();
        $response->assertSee('Jean Clásico Slim');
        $response->assertDontSee('Camisa Lino Blanca');
    }

    public function test_product_detail_page_shows_whatsapp_link_and_related_products(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create([
            'user_id' => $user->id,
            'name' => 'Tienda Tech',
            'slug' => 'tienda-tech',
            'whatsapp_country_code' => '1829',
            'whatsapp_number' => '7654321',
            'status' => 'active',
        ]);

        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Laptop Gamer RTX',
            'slug' => 'laptop-gamer-rtx',
            'price' => 65000,
            'description' => 'Potente laptop con pantalla 144Hz.',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $otherProduct = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Mouse Inalámbrico',
            'slug' => 'mouse-inalambrico',
            'price' => 1500,
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $response = $this->get('/tienda/tienda-tech/producto/laptop-gamer-rtx');

        $response->assertOk();
        $response->assertSee('Laptop Gamer RTX');
        $response->assertSee('RD$ 65,000');
        $response->assertSee('Potente laptop con pantalla 144Hz.');
        $response->assertSee('https://wa.me/18297654321', false);
        $response->assertSee(rawurlencode('Hola, me interesa "Laptop Gamer RTX" que vi en MiCatalogo:'), false);
        $response->assertSee('Mouse Inalámbrico');
        $response->assertSee('Volver a Tienda Tech');
    }
}
