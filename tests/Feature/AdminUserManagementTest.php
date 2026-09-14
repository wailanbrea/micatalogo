<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_access_user_management_index(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        $seller = User::factory()->create([
            'name' => 'Carlos Vendedor',
            'email' => 'carlos@tienda.com',
            'role' => UserRole::Seller,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('Usuarios del Sistema');
        $response->assertSee('Carlos Vendedor');
        $response->assertSee('carlos@tienda.com');
    }

    public function test_a_non_admin_seller_cannot_access_user_management(): void
    {
        $seller = User::factory()->create([
            'role' => UserRole::Seller,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($seller)->get(route('admin.users.index'));

        $response->assertForbidden();
    }

    public function test_an_admin_can_filter_users_by_search_status_and_role(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        $targetUser = User::factory()->create([
            'name' => 'Manuel Exclusivo',
            'email' => 'manuel@target.com',
            'status' => UserStatus::Active,
            'role' => UserRole::Seller,
        ]);

        $otherUser = User::factory()->create([
            'name' => 'Pedro Descartado',
            'email' => 'pedro@other.com',
            'status' => UserStatus::Suspended,
            'role' => UserRole::Seller,
        ]);

        // Filtro por texto
        $resSearch = $this->actingAs($admin)->get(route('admin.users.index', ['q' => 'Manuel']));
        $resSearch->assertOk();
        $resSearch->assertSee('Manuel Exclusivo');
        $resSearch->assertDontSee('Pedro Descartado');

        // Filtro por estado suspendido
        $resStatus = $this->actingAs($admin)->get(route('admin.users.index', ['status' => 'suspended']));
        $resStatus->assertOk();
        $resStatus->assertSee('Pedro Descartado');
        $resStatus->assertDontSee('Manuel Exclusivo');
    }

    public function test_an_admin_can_suspend_and_reactivate_a_user(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);

        // Suspender
        $response = $this->actingAs($admin)->post(route('admin.users.toggle-status', $user));
        $response->assertRedirect();
        $this->assertEquals(UserStatus::Suspended, $user->fresh()->status);

        // Reactivar
        $response2 = $this->actingAs($admin)->post(route('admin.users.toggle-status', $user));
        $response2->assertRedirect();
        $this->assertEquals(UserStatus::Active, $user->fresh()->status);
    }

    public function test_an_admin_cannot_suspend_their_own_account(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.toggle-status', $admin));
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertEquals(UserStatus::Active, $admin->fresh()->status);
    }

    public function test_an_admin_can_toggle_a_users_role(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => UserRole::Seller,
            'email_verified_at' => now(),
        ]);

        // Promover a Admin
        $response = $this->actingAs($admin)->post(route('admin.users.toggle-role', $user));
        $response->assertRedirect();
        $this->assertEquals(UserRole::Admin, $user->fresh()->role);

        // Degradar a Seller
        $response2 = $this->actingAs($admin)->post(route('admin.users.toggle-role', $user));
        $response2->assertRedirect();
        $this->assertEquals(UserRole::Seller, $user->fresh()->role);
    }

    public function test_admin_dashboard_displays_consolidated_global_metrics_and_users(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        $seller = User::factory()->create(['email_verified_at' => now()]);
        $shop = Shop::factory()->create(['user_id' => $seller->id]);

        DB::table('shop_daily_metrics')->insert([
            ['shop_id' => $shop->id, 'date' => now()->toDateString(), 'page_views' => 120, 'whatsapp_clicks' => 18],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Tráfico Global (30d)');
        $response->assertSee('120');
        $response->assertSee('18');
        $response->assertSee('15% conv.');
        $response->assertSee('Usuarios');
    }
}
