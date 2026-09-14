<?php

namespace Tests\Feature;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SellerShopMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_verified_seller_can_access_their_shop_metrics_dashboard(): void
    {
        $seller = User::factory()->create(['email_verified_at' => now()]);
        $shop = Shop::factory()->create([
            'user_id' => $seller->id,
            'name' => 'ElectroTienda Brea',
            'slug' => 'electrotienda-brea',
            'status' => 'active',
        ]);

        $response = $this->actingAs($seller)->get(route('seller.shops.metrics.index', $shop));

        $response->assertOk();
        $response->assertSee('ElectroTienda Brea');
        $response->assertSee('Métricas &amp; QR', false);
        $response->assertSee('Visitas (30 días)');
        $response->assertSee('Contactos WhatsApp');
        $response->assertSee('Código QR de tu Tienda');
    }

    public function test_a_seller_cannot_view_metrics_of_another_sellers_shop(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $attacker = User::factory()->create(['email_verified_at' => now()]);

        $shop = Shop::factory()->create([
            'user_id' => $owner->id,
            'slug' => 'tienda-privada',
            'status' => 'active',
        ]);

        $response = $this->actingAs($attacker)->get(route('seller.shops.metrics.index', $shop));

        $response->assertForbidden();
    }

    public function test_metrics_service_accurately_aggregates_views_and_whatsapp_clicks(): void
    {
        $seller = User::factory()->create(['email_verified_at' => now()]);
        $shop = Shop::factory()->create(['user_id' => $seller->id]);

        $today = now()->toDateString();
        $fiveDaysAgo = now()->subDays(5)->toDateString();
        $twentyDaysAgo = now()->subDays(20)->toDateString();
        $fortyDaysAgo = now()->subDays(40)->toDateString();

        DB::table('shop_daily_metrics')->insert([
            ['shop_id' => $shop->id, 'date' => $today, 'page_views' => 10, 'whatsapp_clicks' => 2],
            ['shop_id' => $shop->id, 'date' => $fiveDaysAgo, 'page_views' => 20, 'whatsapp_clicks' => 4],
            ['shop_id' => $shop->id, 'date' => $twentyDaysAgo, 'page_views' => 30, 'whatsapp_clicks' => 6],
            ['shop_id' => $shop->id, 'date' => $fortyDaysAgo, 'page_views' => 50, 'whatsapp_clicks' => 10],
        ]);

        $service = app(ShopAnalyticsService::class);
        $summary = $service->getMetricsSummary($shop);

        // 7 días: hoy (10) + hace 5 días (20) = 30 vistas, 2 + 4 = 6 clics
        $this->assertEquals(30, $summary['views_7d']);
        $this->assertEquals(6, $summary['clicks_7d']);

        // 30 días: hoy (10) + hace 5 días (20) + hace 20 días (30) = 60 vistas, 12 clics
        $this->assertEquals(60, $summary['views_30d']);
        $this->assertEquals(12, $summary['clicks_30d']);

        // Histórico total: 110 vistas, 22 clics
        $this->assertEquals(110, $summary['total_views']);
        $this->assertEquals(22, $summary['total_clicks']);

        // Conversión 30 días: (12 / 60) * 100 = 20.0%
        $this->assertEquals(20.0, $summary['conversion_rate_30d']);
    }

    public function test_top_products_are_ranked_by_whatsapp_inquiries_and_views(): void
    {
        $seller = User::factory()->create(['email_verified_at' => now()]);
        $shop = Shop::factory()->create(['user_id' => $seller->id]);

        $productA = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Laptop Gamer Pro',
            'availability_status' => ProductAvailabilityStatus::Available,
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $productB = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Mouse Inalambrico',
            'availability_status' => ProductAvailabilityStatus::Available,
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        DB::table('product_daily_metrics')->insert([
            ['product_id' => $productA->id, 'date' => now()->toDateString(), 'page_views' => 50, 'whatsapp_clicks' => 15],
            ['product_id' => $productB->id, 'date' => now()->toDateString(), 'page_views' => 100, 'whatsapp_clicks' => 3],
        ]);

        $service = app(ShopAnalyticsService::class);
        $summary = $service->getMetricsSummary($shop);

        // Laptop Gamer Pro debe ser el #1 en clics de WhatsApp
        $this->assertEquals('Laptop Gamer Pro', $summary['top_by_clicks']->first()->name);
        $this->assertEquals(15, $summary['top_by_clicks']->first()->clicks_count);

        // Mouse Inalambrico debe ser el #1 en vistas
        $this->assertEquals('Mouse Inalambrico', $summary['top_by_views']->first()->name);
        $this->assertEquals(100, $summary['top_by_views']->first()->views_count);
    }

    public function test_a_seller_can_download_the_shop_qr_code_as_svg(): void
    {
        $seller = User::factory()->create(['email_verified_at' => now()]);
        $shop = Shop::factory()->create([
            'user_id' => $seller->id,
            'slug' => 'tienda-qr',
            'status' => 'active',
        ]);

        $response = $this->actingAs($seller)->get(route('seller.shops.qr.download', $shop));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('filename="qr-tienda-qr.svg"', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('<svg', $response->getContent());
    }

    public function test_a_seller_can_access_the_printable_counter_qr_poster(): void
    {
        $seller = User::factory()->create(['email_verified_at' => now()]);
        $shop = Shop::factory()->create([
            'user_id' => $seller->id,
            'name' => 'Boutique Central',
            'slug' => 'boutique-central',
            'status' => 'active',
        ]);

        $response = $this->actingAs($seller)->get(route('seller.shops.qr.print', $shop));

        $response->assertOk();
        $response->assertSee('Boutique Central');
        $response->assertSee('¡Escanea para ver todos nuestros productos!');
        $response->assertSee('<svg', false);
    }
}
