<?php

use App\Enums\UserPlan;
use App\Models\MobileOperation;
use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function administrationCommand(string $type, array $request = [], ?string $resource = null): array
{
    $id = (string) Str::uuid();

    return [
        'type' => $type,
        'client_operation_uuid' => $id,
        'request' => $request,
    ] + ($resource ? ['resource_id' => $resource] : []);
}

beforeEach(function () {
    $this->owner = User::factory()->create(['plan' => UserPlan::Premium]);
    $this->shop = Shop::factory()->create(['user_id' => $this->owner->id]);
    $this->url = '/api/v1/shops/'.$this->shop->public_id.'/mobile-operations';
    Sanctum::actingAs($this->owner, ['pos:write']);
});

test('shop administration mutations replay once and preserve menu controls', function () {
    $settings = administrationCommand('shop_settings_update', [
        'name' => 'Tienda administrativa QA',
        'business_type' => 'general_retail',
        'description' => 'Configuración duradera.',
        'address' => 'Av. QA 1',
        'maps_url' => null,
        'instagram' => '',
        'whatsapp_country_code' => '1',
        'whatsapp_number' => '8095550100',
        'offers_shipping' => false,
        'primary_color' => '#1d4ed8',
        'secondary_color' => '#0f172a',
        'business_hours' => [],
    ]);
    $this->postJson($this->url, $settings)->assertCreated()->assertJsonPath('name', 'Tienda administrativa QA');
    $this->postJson($this->url, $settings)->assertCreated()->assertJsonPath('name', 'Tienda administrativa QA');

    $visibility = administrationCommand('shop_menu_visibility_update', ['enabled_menu_keys' => ['sales', 'products']]);
    $this->postJson($this->url, $visibility)->assertCreated()
        ->assertJsonPath('enabled_menu_keys.0', 'sales');
    $this->postJson($this->url, $visibility)->assertCreated();

    expect(MobileOperation::where('shop_id', $this->shop->id)->count())->toBe(2)
        ->and($this->shop->fresh()->name)->toBe('Tienda administrativa QA')
        ->and($this->shop->fresh()->enabled_menu_keys)->toContain('settings', 'shop_settings', 'sellers');
});

test('seller creation and permission changes are durable and tenant scoped', function () {
    $create = administrationCommand('seller_create', [
        'email' => 'seller-qa@example.com',
        'commission_type' => 'percentage',
        'commission_value' => '10.00',
        'menu_permissions' => ['sales', 'products'],
    ]);
    $response = $this->postJson($this->url, $create)->assertCreated();
    $sellerId = $response->json('seller.id');
    $this->postJson($this->url, $create)->assertCreated()->assertJsonPath('seller.id', $sellerId);
    expect(ShopSeller::where('shop_id', $this->shop->id)->count())->toBe(1);

    $permissions = administrationCommand('seller_menu_permissions_update', [
        'menu_permissions' => ['sales', 'customers'],
    ], (string) $sellerId);
    $this->postJson($this->url, $permissions)->assertCreated()
        ->assertJsonPath('menu_permissions.0', 'sales');
    $this->postJson($this->url, $permissions)->assertCreated();

    expect(ShopSeller::find($sellerId)->menu_permissions)->toBe(['sales', 'customers']);
    expect(MobileOperation::where('shop_id', $this->shop->id)->count())->toBe(2);
});

test('another owner cannot use a durable administration operation on this shop', function () {
    Sanctum::actingAs(User::factory()->create(['plan' => UserPlan::Premium]), ['pos:write']);
    $command = administrationCommand('shop_menu_visibility_update', ['enabled_menu_keys' => ['sales']]);

    $this->postJson($this->url, $command)->assertNotFound();
    expect(MobileOperation::count())->toBe(0);
});
