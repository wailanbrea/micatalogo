<?php

use App\Enums\UserPlan;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;
use App\Services\PlanLimitsService;
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

test('creating an appliance shop applies its categories and profile', function () {
    $seller = User::factory()->create(['plan' => UserPlan::Pro]);

    $this->actingAs($seller)
        ->post(route('seller.shops.store'), shopPayload([
            'name' => 'Electro Hogar Brea',
            'business_type' => 'appliance_store',
        ]))
        ->assertRedirect();

    $shop = $seller->shops()->where('name', 'Electro Hogar Brea')->firstOrFail();

    expect($shop->business_type)->toBe('appliance_store')
        ->and($shop->categories()->orderBy('sort_order')->pluck('name')->all())
        ->toBe(['Neveras y refrigeradores', 'Estufas y hornos', 'Lavadoras y secadoras', 'Aires acondicionados', 'Microondas', 'Pequeños electrodomésticos', 'Televisores y entretenimiento', 'Otros']);
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

test('a premium seller uses the premium shop quota', function () {
    config()->set('catalog.plans.premium.max_active_shops', 2);
    $seller = User::factory()->create(['plan' => UserPlan::Premium]);
    Shop::factory()->for($seller)->create();

    $this->actingAs($seller)
        ->post(route('seller.shops.store'), shopPayload(['name' => 'Otra tienda']))
        ->assertRedirect();

    expect(Shop::ownedBy($seller)->count())->toBe(2);
});

test('a pro seller can manage three shops from one panel and cannot create a fourth', function () {
    $seller = User::factory()->create(['plan' => UserPlan::Pro]);

    foreach (['Casa Norte', 'Casa Centro', 'Casa Sur'] as $name) {
        $this->actingAs($seller)
            ->post(route('seller.shops.store'), shopPayload(['name' => $name]))
            ->assertRedirect();
    }

    $fourthResponse = $this->actingAs($seller)
        ->post(route('seller.shops.store'), shopPayload(['name' => 'Casa Este']));

    $fourthResponse->assertStatus(422);
    expect(Shop::ownedBy($seller)->where('status', 'active')->count())->toBe(3);

    $shops = Shop::ownedBy($seller)->orderBy('name')->get();
    $dashboard = $this->actingAs($seller)->get(route('seller.dashboard'));

    $dashboard->assertOk()
        ->assertSee('Tus tiendas')
        ->assertSee('3 tiendas');

    foreach ($shops as $shop) {
        $dashboard->assertSee($shop->name)
            ->assertSee(route('shops.show', $shop), false)
            ->assertSee(route('seller.shops.products.index', $shop), false)
            ->assertSee(route('seller.shops.inventory.index', $shop), false)
            ->assertSee(route('seller.shops.edit', $shop), false);
    }
});

test('a seller cannot edit another sellers shop', function () {
    $seller = User::factory()->create();
    $shop = Shop::factory()->create();

    $this->actingAs($seller)
        ->get(route('seller.shops.edit', $shop))
        ->assertForbidden();
});

test('mi tienda stays inside the panel and explains how to complete the storefront', function () {
    $seller = User::factory()->create();
    $shop = Shop::factory()->for($seller)->create([
        'description' => null,
        'address' => null,
        'instagram' => null,
    ]);

    $response = $this->actingAs($seller)
        ->get(route('seller.shops.storefront', $shop));

    $response->assertOk()
        ->assertSee('Mi tienda')
        ->assertSee('Tu tienda se prepara aquí')
        ->assertSee(route('seller.shops.edit', $shop).'#basic-information', false)
        ->assertSee(route('seller.shops.products.index', $shop), false)
        ->assertSee(route('seller.shops.storefront', $shop).'#vitrinas', false)
        ->assertSee('Configurar tienda')
        ->assertSee('Gestionar productos')
        ->assertSee('Ver enlace y QR')
        ->assertSee('Para que tu tienda se vea bien')
        ->assertSee(route('seller.shops.storefront', [$shop, 'tab' => 'apariencia']), false)
        ->assertSee(route('seller.shops.storefront', [$shop, 'tab' => 'contacto']), false)
        ->assertSee(route('seller.shops.feature', [$shop, 'feature' => 'photos']), false)
        ->assertSee('Ver mi tienda')
        ->assertSee(route('shops.show', $shop), false)
        ->assertSee(route('seller.shops.storefront', $shop), false)
        ->assertSee('Navegación del panel');
});

test('mi tienda tabs link to executable appearance contact and metrics screens', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();

    $response = $this->actingAs($user)->get(route('seller.shops.storefront', $shop));

    $response->assertOk()
        ->assertSee(route('seller.shops.edit', $shop).'#appearance', false)
        ->assertSee(route('seller.shops.edit', $shop).'#contact', false)
        ->assertSee(route('seller.shops.edit', $shop).'#hours', false)
        ->assertSee(route('seller.shops.edit', $shop).'#google', false)
        ->assertSee(route('seller.shops.metrics.index', $shop), false)
        ->assertSee('id="apariencia"', false)
        ->assertSee('id="contacto"', false)
        ->assertSee('id="google"', false);
});

test('mi tienda renders every Puntto-style tab in the same panel', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();

    foreach ([
        'apariencia' => 'Guardar apariencia',
        'contacto' => 'Guardar contacto y horario',
        'catalogo' => 'Guardar catálogo',
        'vitrinas' => 'Guardar vitrinas',
        'anuncios' => 'Guardar anuncios',
        'google' => 'Guardar Google',
    ] as $tab => $saveLabel) {
        $this->actingAs($user)
            ->get(route('seller.shops.storefront', [$shop, 'tab' => $tab]))
            ->assertOk()
            ->assertSee('aria-current="page"', false)
            ->assertSee($saveLabel);
    }
});

test('mi tienda can save catalog preferences without leaving the storefront context', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $shop = Shop::factory()->for($user)->create();

    $response = $this->actingAs($user)->put(route('seller.shops.update', $shop), [
        'return_to' => 'storefront',
        'return_tab' => 'catalogo',
        'name' => $shop->name,
        'slug' => $shop->slug,
        'whatsapp_country_code' => $shop->whatsapp_country_code,
        'whatsapp_number' => $shop->whatsapp_number,
        'instagram' => $shop->instagram,
        'offers_shipping' => $shop->offers_shipping ? '1' : '0',
        'address' => $shop->address,
        'maps_url' => $shop->maps_url,
        'description' => $shop->description,
        'primary_color' => '#2563EB',
        'secondary_color' => '#0F172A',
        'operational_settings' => [
            'catalog' => [
                'sort' => 'price_desc',
                'offers_first' => '1',
                'hide_out_of_stock' => '1',
                'show_stock' => '0',
                'allow_backorder' => '0',
            ],
        ],
    ]);

    $response->assertRedirect(route('seller.shops.storefront', [$shop, 'tab' => 'catalogo']));
    expect($shop->fresh()->operational_settings['catalog']['sort'])->toBe('price_desc');
    expect($shop->fresh()->operational_settings['catalog']['hide_out_of_stock'])->toBeTrue();
});

test('a shop owner can manage payment accounts without changing the shop record', function () {
    $seller = User::factory()->create(['email_verified_at' => now()]);
    $shop = Shop::factory()->for($seller)->create();

    $this->actingAs($seller)
        ->post(route('seller.shops.payment-accounts.store', $shop), [
            'name' => 'Cuenta Popular',
            'bank_name' => 'Banco Popular',
            'account_number' => '123456789',
            'account_holder' => 'BSolutions.dev',
            'instructions' => 'Enviar comprobante por WhatsApp.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('shop_payment_accounts', [
        'shop_id' => $shop->id,
        'name' => 'Cuenta Popular',
        'is_active' => 1,
    ]);
    $account = $shop->paymentAccounts()->firstOrFail();

    $this->actingAs($seller)
        ->put(route('seller.shops.payment-accounts.update', [$shop, $account]), [
            'name' => 'Cuenta Popular Principal',
            'bank_name' => 'Banco Popular',
            'account_number' => '123456789',
            'account_holder' => 'BSolutions.dev',
            'instructions' => 'Actualizada',
            'is_active' => '1',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('shop_payment_accounts', ['id' => $account->id, 'name' => 'Cuenta Popular Principal']);

    $this->actingAs($seller)
        ->delete(route('seller.shops.payment-accounts.destroy', [$shop, $account]))
        ->assertRedirect();

    $this->assertDatabaseHas('shop_payment_accounts', ['id' => $account->id, 'is_active' => 0]);
});

test('a shop owner can configure the full operational settings contract from the web', function () {
    $seller = User::factory()->create(['email_verified_at' => now()]);
    $shop = Shop::factory()->for($seller)->create();

    $this->actingAs($seller)
        ->get(route('seller.shops.edit', $shop))
        ->assertOk()
        ->assertSee('Comprobantes fiscales')
        ->assertSee('Decants: tamaños predeterminados (ml)')
        ->assertSee('Recetas / insumos');

    $this->actingAs($seller)
        ->put(route('seller.shops.update', $shop), shopPayload([
            'operational_settings' => [
                'timezone' => 'America/New_York',
                'credit' => [
                    'enabled' => '1',
                    'default_days' => '45',
                    'allow_partial_payments' => '1',
                ],
                'orders' => ['enabled' => '1'],
                'recipes' => ['enabled' => '1'],
                'wholesale' => ['enabled' => '1', 'minimum_quantity' => '12'],
                'shipping' => ['enabled' => '1', 'types' => ['pickup', 'delivery']],
                'decants' => ['enabled' => '1', 'default_ml' => ['5', '10', '30']],
                'images' => ['max_per_product' => '6', 'auto_optimize' => '1'],
                'payment_methods' => ['cash', 'bank_transfer', 'credit'],
            ],
        ]))
        ->assertRedirect(route('seller.shops.edit', $shop));

    $settings = $shop->fresh()->operational_settings;
    expect($settings['timezone'])->toBe('America/New_York')
        ->and($settings['credit']['default_days'])->toBe(45)
        ->and($settings['wholesale']['minimum_quantity'])->toBe(12)
        ->and($settings['images']['max_per_product'])->toBe(6)
        ->and($settings['decants']['default_ml'])->toBe([5, 10, 30])
        ->and($settings['shipping']['types'])->toBe(['pickup', 'delivery'])
        ->and($settings['recipes']['enabled'])->toBeTrue();
});

test('a shop owner can save weekly hours and the public storefront displays them', function () {
    $seller = User::factory()->create();
    $shop = Shop::factory()->for($seller)->create();
    $hours = [
        'monday' => ['open' => '08:30', 'close' => '19:00'],
        'tuesday' => ['open' => '08:30', 'close' => '19:00'],
        'wednesday' => ['open' => '08:30', 'close' => '19:00'],
        'thursday' => ['open' => '08:30', 'close' => '19:00'],
        'friday' => ['open' => '08:30', 'close' => '19:00'],
        'saturday' => ['open' => '09:00', 'close' => '15:00'],
        'sunday' => ['closed' => '1'],
    ];

    $this->actingAs($seller)
        ->put(route('seller.shops.update', $shop), shopPayload(['business_hours' => $hours]))
        ->assertRedirect(route('seller.shops.edit', $shop));

    $savedHours = $shop->fresh()->business_hours;
    expect($savedHours['monday']['open'])->toBe('08:30')
        ->and($savedHours['saturday']['close'])->toBe('15:00')
        ->and($savedHours['sunday']['closed'])->toBeTrue();

    $this->get(route('shops.show', $shop->fresh()))
        ->assertOk()
        ->assertSee('Ver horario semanal')
        ->assertSee('Lunes')
        ->assertSee('08:30 – 19:00');
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

test('new sellers see the animated store setup guide', function () {
    $seller = User::factory()->create();

    $this->actingAs($seller)
        ->get(route('seller.dashboard'))
        ->assertOk()
        ->assertSee('Guía para administradores de tienda')
        ->assertDontSee('Guía para vendedores')
        ->assertSee('Crear mi tienda');

    Shop::factory()->for($seller)->create();

    $this->actingAs($seller)
        ->get(route('seller.dashboard'))
        ->assertDontSee('Guía para administradores de tienda');
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

test('admin sees exact product image storage for every shop in all shops view', function () {
    $admin = User::factory()->admin()->create();
    $shop = Shop::factory()->create(['name' => 'Tienda con almacenamiento']);
    $product = Product::factory()->for($shop)->create();
    ProductImage::factory()->for($product)->create(['size_bytes' => 5 * 1024 * 1024]);

    $this->actingAs($admin)
        ->get(route('seller.dashboard', ['view' => 'all']))
        ->assertOk()
        ->assertSee('5.00 MB usados')
        ->assertSee('1 imágenes');
});

test('admin can edit owner-only shop fields', function () {
    $admin = User::factory()->admin()->create();
    $shop = Shop::factory()->create([
        'status' => 'active',
        'discovery_enabled' => false,
        'product_limit' => null,
    ]);

    $this->actingAs($admin)
        ->put(route('seller.shops.update', $shop), shopPayload([
            'name' => $shop->name,
            'slug' => $shop->slug,
            'status' => 'suspended',
            'discovery_enabled' => 1,
            'product_limit' => 500,
        ]))
        ->assertRedirect(route('seller.shops.edit', $shop));

    $this->assertDatabaseHas('shops', [
        'id' => $shop->id,
        'status' => 'suspended',
        'discovery_enabled' => 1,
        'product_limit' => 500,
    ]);

    expect(app(PlanLimitsService::class)->productLimit($shop->fresh()))->toBe(500);
});

test('seller dashboard renders unified persistent navigation and account dropdown', function () {
    $seller = User::factory()->create(['name' => 'Juan Perez', 'email' => 'juan@example.com']);
    $shop = Shop::factory()->for($seller)->create(['name' => 'Tienda Juan']);

    $response = $this->actingAs($seller)->get(route('seller.dashboard'));

    $response->assertOk()
        ->assertSee('Mis tiendas')
        ->assertSee('Hola, Juan')
        ->assertSee('Mi cuenta')
        ->assertSee('Salir')
        ->assertSee('Configuración')
        ->assertSee('Vendedores')
        ->assertDontSee('Usuarios del sistema')
        ->assertSee('Reportes')
        ->assertSee(route('seller.shops.inventory.index', $shop))
        ->assertSee(route('seller.shops.categories.index', $shop))
        ->assertSee(route('seller.shops.products.import.create', $shop))
        ->assertSee(route('seller.shops.products.bulk.create', $shop))
        ->assertSee(route('seller.shops.inventory.lots', $shop))
        ->assertSee(route('seller.shops.pricing.index', $shop))
        ->assertSee('data-navigation-progress')
        ->assertSee('wire:navigate.hover');
});

test('an assigned seller can see the shop catalog and selling entry without owner menus', function () {
    $owner = User::factory()->create();
    $seller = User::factory()->create(['name' => 'Vendedor asignado']);
    $shop = Shop::factory()->for($owner)->create(['name' => 'Tienda asignada']);
    ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'commission_type' => 'percentage',
        'commission_value' => 5,
        'is_active' => true,
    ]);

    $this->actingAs($seller)
        ->get(route('seller.dashboard'))
        ->assertOk()
        ->assertSee('Tienda asignada')
        ->assertSee('Guía para vendedores')
        ->assertDontSee('Guía para administradores de tienda')
        ->assertSee('Registrar venta')
        ->assertSee('Productos')
        ->assertDontSee('Mis tiendas')
        ->assertDontSee('Configuración')
        ->assertDontSee('Vendedores')
        ->assertDontSee('Editar tienda')
        ->assertDontSee('Crear tienda');

    $this->actingAs($seller)
        ->get(route('seller.shops.products.index', $shop))
        ->assertOk();

    $this->actingAs($seller)
        ->get(route('seller.shops.sellers.index', $shop))
        ->assertForbidden();

    $this->actingAs($seller)
        ->get(route('seller.shops.edit', $shop))
        ->assertForbidden();

    $this->actingAs($seller)
        ->get(route('seller.shops.products.create', $shop))
        ->assertForbidden();

    $this->actingAs($seller)
        ->get(route('seller.shops.create'))
        ->assertForbidden();
});

test('a shop administrator cannot access platform owner routes', function () {
    $shopAdministrator = User::factory()->create();
    Shop::factory()->for($shopAdministrator)->create();

    $this->actingAs($shopAdministrator)
        ->get(route('admin.dashboard'))
        ->assertForbidden();

    $this->actingAs($shopAdministrator)
        ->get(route('admin.reports.index'))
        ->assertForbidden();

    $this->actingAs($shopAdministrator)
        ->get(route('admin.support.index'))
        ->assertForbidden();

    $this->actingAs($shopAdministrator)
        ->get(route('admin.users.index'))
        ->assertForbidden();
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

test('a seller can upload a storefront cover and save branding fields', function () {
    Storage::fake('public');
    $seller = User::factory()->create();
    $shop = Shop::factory()->for($seller)->create(['cover_object_key' => null]);

    $this->actingAs($seller)
        ->put(route('seller.shops.update', $shop), [
            ...shopPayload(['name' => $shop->name, 'slug' => $shop->slug]),
            'address' => 'Santo Domingo, RD',
            'maps_url' => 'https://maps.google.com/?q=Santo+Domingo',
            'primary_color' => '#7c3aed',
            'secondary_color' => '#1e1b4b',
            'cover' => UploadedFile::fake()->image('cover.jpg', 1400, 520),
        ])
        ->assertRedirect(route('seller.shops.edit', $shop));

    $shop->refresh();
    expect($shop->cover_object_key)->not->toBeNull()
        ->and($shop->primary_color)->toBe('#7c3aed')
        ->and($shop->address)->toBe('Santo Domingo, RD');
    expect(Storage::disk('public')->exists($shop->cover_object_key))->toBeTrue();
});
