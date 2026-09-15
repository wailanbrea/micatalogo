<?php

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function shopPayload(array $overrides = []): array
{
    return [...[
        'name' => 'Brea Fashion',
        'slug' => '',
        'description' => 'Moda para todos los dias.',
        'whatsapp_country_code' => '+1',
        'whatsapp_number' => '(809) 555-1234',
        'instagram' => '@brea.fashion',
    ], ...$overrides];
}

test('a verified seller can create a shop with normalized contact data', function () {
    $seller = User::factory()->create();

    $this->actingAs($seller)
        ->post(route('seller.shops.store'), shopPayload())
        ->assertRedirect();

    $this->assertDatabaseHas('shops', [
        'user_id' => $seller->id,
        'name' => 'Brea Fashion',
        'slug' => 'brea-fashion',
        'whatsapp_country_code' => '1',
        'whatsapp_number' => '8095551234',
        'instagram' => 'brea.fashion',
        'status' => 'active',
    ]);
});

test('shop slugs resolve collisions deterministically', function () {
    Shop::factory()->create(['slug' => 'brea-fashion']);
    $seller = User::factory()->create();

    $this->actingAs($seller)->post(route('seller.shops.store'), shopPayload());

    $this->assertDatabaseHas('shops', ['user_id' => $seller->id, 'slug' => 'brea-fashion-2']);
});

test('a seller cannot create a second active free shop', function () {
    $seller = User::factory()->create();
    Shop::factory()->for($seller)->create();

    $this->actingAs($seller)
        ->post(route('seller.shops.store'), shopPayload(['name' => 'Otra tienda']))
        ->assertStatus(422);

    expect(Shop::ownedBy($seller)->count())->toBe(1);
});

test('a seller cannot edit another sellers shop', function () {
    $seller = User::factory()->create();
    $shop = Shop::factory()->create();

    $this->actingAs($seller)
        ->get(route('seller.shops.edit', $shop))
        ->assertForbidden();
});

test('a seller can update and delete their shop', function () {
    $seller = User::factory()->create();
    $shop = Shop::factory()->for($seller)->create();

    $this->actingAs($seller)
        ->put(route('seller.shops.update', $shop), shopPayload(['name' => 'Brea Estilo', 'slug' => 'brea-estilo']))
        ->assertRedirect(route('seller.shops.edit', $shop));

    $this->assertDatabaseHas('shops', ['id' => $shop->id, 'name' => 'Brea Estilo', 'slug' => 'brea-estilo']);

    $this->delete(route('seller.shops.destroy', $shop))
        ->assertRedirect(route('seller.dashboard'));

    $this->assertSoftDeleted('shops', ['id' => $shop->id]);
});

test('an unverified seller cannot access shop onboarding', function () {
    $seller = User::factory()->unverified()->create();

    $this->actingAs($seller)
        ->get(route('seller.shops.create'))
        ->assertRedirect('/email/verify');
});

test('seller dashboard filters shops by search term', function () {
    $seller = User::factory()->create();
    $shop1 = Shop::factory()->for($seller)->create(['name' => 'Ferretería El Tornillo', 'slug' => 'ferreteria-el-tornillo']);
    $shop2 = Shop::factory()->for($seller)->create(['name' => 'Boutique Elegancia', 'slug' => 'boutique-elegancia']);

    $response = $this->actingAs($seller)
        ->get(route('seller.dashboard', ['q' => 'Tornillo']));

    $response->assertOk()
        ->assertSee('Ferretería El Tornillo')
        ->assertDontSee('Boutique Elegancia')
        ->assertSee('Filtros aplicados:')
        ->assertSee('Búsqueda:');
});

test('admin can filter all shops by status and shipping', function () {
    $admin = User::factory()->admin()->create();
    $activeShop = Shop::factory()->create(['name' => 'Tienda Activa Con Envio', 'status' => 'active', 'offers_shipping' => true]);
    $suspendedShop = Shop::factory()->create(['name' => 'Tienda Suspendida', 'status' => 'suspended', 'offers_shipping' => false]);

    // Filter by status suspended in all shops view
    $response = $this->actingAs($admin)
        ->get(route('seller.dashboard', ['view' => 'all', 'status' => 'suspended']));

    $response->assertOk()
        ->assertSee('Todas las tiendas del sistema')
        ->assertSee('Tienda Suspendida')
        ->assertDontSee('Tienda Activa Con Envio');

    // Filter by shipping in all shops view
    $responseShipping = $this->actingAs($admin)
        ->get(route('seller.dashboard', ['view' => 'all', 'shipping' => 'yes']));

    $responseShipping->assertOk()
        ->assertSee('Tienda Activa Con Envio')
        ->assertDontSee('Tienda Suspendida');
});

test('seller dashboard renders unified persistent navigation and account dropdown', function () {
    $seller = User::factory()->create(['name' => 'Juan Perez', 'email' => 'juan@example.com']);
    $shop = Shop::factory()->for($seller)->create(['name' => 'Tienda Juan']);

    $response = $this->actingAs($seller)->get(route('seller.dashboard'));

    $response->assertOk()
        ->assertSee('Mis tiendas')
        ->assertSee('Hola, Juan')
        ->assertSee('Mi Cuenta')
        ->assertSee('juan@example.com')
        ->assertSee('Vendedor')
        ->assertSee(route('seller.shops.inventory.index', $shop));
});

test('a seller can upload a shop logo which is converted to WebP and stored', function () {
    Storage::fake('public');
    $seller = User::factory()->create();
    $shop = Shop::factory()->for($seller)->create(['logo_object_key' => null]);

    $logoFile = UploadedFile::fake()->image('logo.png', 500, 500);

    $this->actingAs($seller)
        ->put(route('seller.shops.update', $shop), [
            ...shopPayload(['name' => $shop->name, 'slug' => $shop->slug]),
            'logo' => $logoFile,
        ])
        ->assertRedirect(route('seller.shops.edit', $shop));

    $shop->refresh();
    expect($shop->logo_object_key)->not->toBeNull()
        ->and($shop->logo_object_key)->toEndWith('.webp')
        ->and($shop->logo_url)->not->toBeNull();

    expect(Storage::disk('public')->exists($shop->logo_object_key))->toBeTrue();
});

test('a seller can remove their shop logo', function () {
    Storage::fake('public');
    $seller = User::factory()->create();
    $shop = Shop::factory()->for($seller)->create(['logo_object_key' => 'shops/test/logo-sample.webp']);
    Storage::disk('public')->put($shop->logo_object_key, 'sample content');

    $this->actingAs($seller)
        ->put(route('seller.shops.update', $shop), [
            ...shopPayload(['name' => $shop->name, 'slug' => $shop->slug]),
            'remove_logo' => 1,
        ])
        ->assertRedirect(route('seller.shops.edit', $shop));

    $shop->refresh();
    expect($shop->logo_object_key)->toBeNull();
    expect(Storage::disk('public')->exists('shops/test/logo-sample.webp'))->toBeFalse();
});
