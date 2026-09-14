<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContentModerationReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.turnstile.enabled', false);
    }

    public function test_visitor_can_submit_report_for_a_product(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        $payload = [
            'type' => 'product',
            'id' => $product->public_id,
            'reason' => 'counterfeit',
            'description' => 'Este producto parece una imitación no autorizada.',
        ];

        $response = $this->from("/tienda/{$shop->slug}/producto/{$product->slug}")
            ->post('/reportar', $payload);

        $response->assertRedirect("/tienda/{$shop->slug}/producto/{$product->slug}");
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('reports', [
            'reportable_type' => Product::class,
            'reportable_id' => $product->id,
            'reason' => 'counterfeit',
            'description' => 'Este producto parece una imitación no autorizada.',
            'status' => 'open',
        ]);
    }

    public function test_visitor_can_submit_report_for_a_shop(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);

        $payload = [
            'type' => 'shop',
            'id' => $shop->public_id,
            'reason' => 'fraud',
            'description' => 'Tienda sospechosa de estafa con comprobantes falsos.',
        ];

        $response = $this->from("/tienda/{$shop->slug}")
            ->post('/reportar', $payload);

        $response->assertRedirect("/tienda/{$shop->slug}");
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('reports', [
            'reportable_type' => Shop::class,
            'reportable_id' => $shop->id,
            'reason' => 'fraud',
            'status' => 'open',
        ]);
    }

    public function test_report_submission_requires_valid_reason(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);

        $response = $this->post('/reportar', [
            'type' => 'shop',
            'id' => $shop->public_id,
            'reason' => 'invalid_reason_here',
        ]);

        $response->assertSessionHasErrors('reason');
    }

    public function test_report_submission_is_rate_limited(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/reportar', [
                'type' => 'shop',
                'id' => $shop->public_id,
                'reason' => 'spam',
            ])->assertSessionHasNoErrors();
        }

        $this->post('/reportar', [
            'type' => 'shop',
            'id' => $shop->public_id,
            'reason' => 'spam',
        ])->assertStatus(429);
    }

    public function test_report_submission_validates_turnstile_when_enabled(): void
    {
        config()->set('services.turnstile.enabled', true);
        config()->set('services.turnstile.secret_key', 'test-secret');

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
                'action' => 'report',
            ]),
        ]);

        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);

        $response = $this->post('/reportar', [
            'type' => 'shop',
            'id' => $shop->public_id,
            'reason' => 'spam',
            'cf-turnstile-response' => 'valid-turnstile-token',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reports', [
            'reportable_id' => $shop->id,
            'reason' => 'spam',
        ]);
    }
}
