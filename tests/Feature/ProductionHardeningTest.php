<?php

namespace Tests\Feature;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Models\GlobalCategory;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_applied_to_all_responses(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_sitemap_returns_valid_xml_with_public_shops_and_products(): void
    {
        $user = User::factory()->create();
        $category = GlobalCategory::factory()->create(['status' => 'active']);
        $shop = Shop::factory()->for($user)->create([
            'status' => 'active',
            'slug' => 'tienda-sitemap',
        ]);
        $product = Product::factory()->for($shop)->create([
            'name' => 'Producto Sitemap',
            'slug' => 'producto-sitemap',
            'moderation_status' => ProductModerationStatus::Active,
            'availability_status' => ProductAvailabilityStatus::Available,
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee(route('home'));
        $response->assertSee(route('shops.show', $shop));
        $response->assertSee(route('products.show', [$shop, $product]));
    }

    public function test_custom_404_view_renders_for_missing_pages(): void
    {
        $response = $this->get('/tienda/tienda-inexistente-xyz');

        $response->assertNotFound();
        $response->assertSee('404');
        $response->assertSee('Página o producto no disponible');
        $response->assertSee('Explorar el catálogo');
    }

    public function test_robots_meta_tag_marks_search_and_auth_as_noindex(): void
    {
        // Public home is indexed
        $response = $this->get('/');
        $response->assertSee('<meta name="robots" content="index, follow">', false);

        // Search query has noindex
        $searchResponse = $this->get('/?q=laptop');
        $searchResponse->assertSee('<meta name="robots" content="noindex, follow">', false);

        // Login has noindex
        $loginResponse = $this->get('/login');
        $loginResponse->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_ad_slot_is_hidden_when_ads_are_disabled(): void
    {
        config()->set('catalog.ads.enabled', false);

        $response = $this->get('/');
        $response->assertDontSee('Espacio publicitario');
    }

    public function test_ad_slot_is_rendered_when_ads_are_enabled(): void
    {
        config()->set('catalog.ads.enabled', true);

        $response = $this->get('/');
        $response->assertSee('Espacio publicitario');
        $response->assertSee('Publicidad');
    }
}
