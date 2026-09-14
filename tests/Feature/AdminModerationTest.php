<?php

namespace Tests\Feature;

use App\Enums\ProductModerationStatus;
use App\Models\Product;
use App\Models\Report;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_dashboard_or_reports(): void
    {
        $seller = User::factory()->create();

        $this->actingAs($seller)->get('/admin')->assertForbidden();
        $this->actingAs($seller)->get('/admin/reportes')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_admin_can_access_dashboard_and_see_platform_statistics(): void
    {
        $admin = User::factory()->admin()->create();

        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'status' => 'active']);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'moderation_status' => ProductModerationStatus::Active]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertSee('Dashboard del Sistema');
        $response->assertSee('Almacenamiento R2');
        $response->assertSee('Moderación');
        $response->assertSee('Tiendas');
        $response->assertSee('Productos');
    }

    public function test_admin_can_view_moderation_queue_and_filter_by_status(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);

        $openReport = Report::factory()->create([
            'reportable_type' => Shop::class,
            'reportable_id' => $shop->id,
            'reason' => 'fraud',
            'status' => 'open',
        ]);

        $resolvedReport = Report::factory()->create([
            'reportable_type' => Shop::class,
            'reportable_id' => $shop->id,
            'reason' => 'spam',
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $admin->id,
        ]);

        // Open reports by default
        $response = $this->actingAs($admin)->get('/admin/reportes');
        $response->assertOk();
        $response->assertSee('fraud');
        $response->assertDontSee('spam');

        // Filter resolved reports
        $response = $this->actingAs($admin)->get('/admin/reportes?status=resolved');
        $response->assertOk();
        $response->assertSee('spam');
        $response->assertDontSee('fraud');
    }

    public function test_admin_can_resolve_report_and_suspend_product(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'status' => 'active']);
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Falso Reloj Rolex',
            'moderation_status' => ProductModerationStatus::Active,
        ]);

        $report = Report::factory()->create([
            'reportable_type' => Product::class,
            'reportable_id' => $product->id,
            'reason' => 'counterfeit',
            'status' => 'open',
        ]);

        $response = $this->actingAs($admin)->post("/admin/reportes/{$report->public_id}/resolver", [
            'action' => 'suspend_target',
            'notes' => 'Confirmada infracción de marca.',
        ]);

        $response->assertRedirect('/admin/reportes');
        $response->assertSessionHas('status', 'Reporte resuelto y entidad reportada suspendida.');

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'resolved',
            'resolved_by' => $admin->id,
        ]);

        $this->assertEquals(ProductModerationStatus::Suspended, $product->fresh()->moderation_status);

        // Verify product is now hidden from public view
        $this->get("/tienda/{$shop->slug}/producto/{$product->slug}")->assertNotFound();
    }

    public function test_admin_can_resolve_report_and_suspend_shop(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = User::factory()->create();
        $shop = Shop::factory()->create([
            'user_id' => $seller->id,
            'slug' => 'tienda-fraudulenta',
            'status' => 'active',
        ]);

        $report = Report::factory()->create([
            'reportable_type' => Shop::class,
            'reportable_id' => $shop->id,
            'reason' => 'fraud',
            'status' => 'open',
        ]);

        $response = $this->actingAs($admin)->post("/admin/reportes/{$report->public_id}/resolver", [
            'action' => 'suspend_target',
            'notes' => 'Múltiples denuncias de estafa comprobadas.',
        ]);

        $response->assertRedirect('/admin/reportes');
        $this->assertEquals('suspended', $shop->fresh()->status);

        // Verify shop is now hidden from public
        $this->get('/tienda/tienda-fraudulenta')->assertNotFound();
    }

    public function test_admin_can_dismiss_report_without_penalizing_target(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'status' => 'active']);

        $report = Report::factory()->create([
            'reportable_type' => Shop::class,
            'reportable_id' => $shop->id,
            'reason' => 'other',
            'status' => 'open',
        ]);

        $response = $this->actingAs($admin)->post("/admin/reportes/{$report->public_id}/resolver", [
            'action' => 'dismiss',
            'notes' => 'Denuncia infundada.',
        ]);

        $response->assertRedirect('/admin/reportes');
        $this->assertEquals('active', $shop->fresh()->status);
        $this->assertEquals('resolved', $report->fresh()->status);
    }

    public function test_admin_can_directly_toggle_shop_and_product_status(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'status' => 'active']);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'moderation_status' => ProductModerationStatus::Active]);

        // Toggle Shop
        $this->actingAs($admin)->post("/admin/tiendas/{$shop->public_id}/toggle-status");
        $this->assertEquals('suspended', $shop->fresh()->status);

        $this->actingAs($admin)->post("/admin/tiendas/{$shop->public_id}/toggle-status");
        $this->assertEquals('active', $shop->fresh()->status);

        // Toggle Product
        $this->actingAs($admin)->post("/admin/productos/{$product->public_id}/toggle-status");
        $this->assertEquals(ProductModerationStatus::Suspended, $product->fresh()->moderation_status);

        $this->actingAs($admin)->post("/admin/productos/{$product->public_id}/toggle-status");
        $this->assertEquals(ProductModerationStatus::Active, $product->fresh()->moderation_status);
    }
}
