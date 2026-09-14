<?php

namespace Tests\Feature;

use App\Enums\ProductModerationStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MetricRecordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_public_shop_increments_daily_pageviews(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'slug' => 'tienda-metricas', 'status' => 'active']);

        $this->get('/tienda/tienda-metricas')->assertOk();
        $this->get('/tienda/tienda-metricas')->assertOk();

        $this->assertDatabaseHas('shop_daily_metrics', [
            'shop_id' => $shop->id,
            'date' => now()->toDateString(),
            'page_views' => 2,
            'whatsapp_clicks' => 0,
        ]);

        $this->assertEquals(1, DB::table('shop_daily_metrics')->where('shop_id', $shop->id)->count());
    }

    public function test_visiting_public_product_increments_both_product_and_shop_pageviews(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'slug' => 'tienda-tech', 'status' => 'active']);
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'slug' => 'mouse-gamer',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $this->get('/tienda/tienda-tech/producto/mouse-gamer')->assertOk();

        $this->assertDatabaseHas('product_daily_metrics', [
            'product_id' => $product->id,
            'date' => now()->toDateString(),
            'page_views' => 1,
            'whatsapp_clicks' => 0,
        ]);

        $this->assertDatabaseHas('shop_daily_metrics', [
            'shop_id' => $shop->id,
            'date' => now()->toDateString(),
            'page_views' => 1,
            'whatsapp_clicks' => 0,
        ]);
    }

    public function test_crawler_user_agents_do_not_increment_pageviews(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'slug' => 'tienda-crawler', 'status' => 'active']);

        $this->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])
            ->get('/tienda/tienda-crawler')
            ->assertOk();

        $this->assertDatabaseMissing('shop_daily_metrics', [
            'shop_id' => $shop->id,
        ]);
    }

    public function test_whatsapp_shop_redirect_tracks_clicks(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create([
            'user_id' => $seller->id,
            'slug' => 'tienda-wa',
            'whatsapp_country_code' => '1809',
            'whatsapp_number' => '1234567',
            'status' => 'active',
        ]);

        $response = $this->get('/r/wa/tienda/tienda-wa');

        $response->assertRedirect();
        $this->assertStringStartsWith('https://wa.me/18091234567', $response->headers->get('Location'));

        $this->assertDatabaseHas('shop_daily_metrics', [
            'shop_id' => $shop->id,
            'date' => now()->toDateString(),
            'page_views' => 0,
            'whatsapp_clicks' => 1,
        ]);
    }

    public function test_whatsapp_product_redirect_tracks_clicks(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create([
            'user_id' => $seller->id,
            'slug' => 'tienda-wa-prod',
            'whatsapp_country_code' => '1849',
            'whatsapp_number' => '9876543',
            'status' => 'active',
        ]);
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'slug' => 'teclado-mecanico',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $response = $this->get('/r/wa/tienda/tienda-wa-prod/producto/teclado-mecanico');

        $response->assertRedirect();
        $this->assertStringStartsWith('https://wa.me/18499876543', $response->headers->get('Location'));

        $this->assertDatabaseHas('product_daily_metrics', [
            'product_id' => $product->id,
            'date' => now()->toDateString(),
            'whatsapp_clicks' => 1,
        ]);

        $this->assertDatabaseHas('shop_daily_metrics', [
            'shop_id' => $shop->id,
            'date' => now()->toDateString(),
            'whatsapp_clicks' => 1,
        ]);
    }
}
