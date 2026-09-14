<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('homepage renders a single CTA to create catalog and displays software category', function () {
    $this->seed(DatabaseSeeder::class);

    $response = $this->get('/');
    $response->assertOk();

    // Verify there is only one "Crear mi catalogo gratis" CTA
    $content = $response->getContent();
    $ctaCount = substr_count($content, 'Crear mi catalogo gratis');
    expect($ctaCount)->toBe(1);

    // Verify Software category is visible in categories bar
    $response->assertSee('Software');

    // Verify BSolutions.dev shop is visible in featured shops
    $response->assertSee('BSolutions.dev');

    // Verify BSolutions products are present
    $response->assertSee('CRM WhatsApp Multiagente');
    $response->assertSee('TicketPro');

    // Verify WebP image URLs are rendered
    $response->assertSee('fm=webp');
});

test('product detail view renders product image when available', function () {
    $this->seed(DatabaseSeeder::class);

    $shop = Shop::where('slug', 'bsolutions-dev')->firstOrFail();
    $product = Product::where('slug', 'bsolutionscrmwas')->firstOrFail();

    $response = $this->get(route('products.show', [$shop, $product]));
    $response->assertOk();

    $response->assertSee($product->name);
    $response->assertSee('RD$ 9,500');
    $response->assertSee('crm-whatsapp.svg');
    $response->assertSee('https://wa.me/18095550100');
});

test('homepage renders user account dropdown when authenticated', function () {
    $user = User::factory()->create([
        'name' => 'Wailan Brea',
        'email' => 'wailan@example.com',
    ]);

    $response = $this->actingAs($user)->get('/');
    $response->assertOk();

    $response->assertSee('Hola, Wailan');
    $response->assertSee('Mi Cuenta y Catálogos');
    $response->assertSee('wailan@example.com');
    $response->assertSee('Panel de tiendas');
    $response->assertDontSee('Iniciar sesión');
});

test('homepage renders admin menu options when user is admin', function () {
    $admin = User::factory()->admin()->create([
        'name' => 'Owner Admin',
        'email' => 'owner@example.com',
    ]);

    $response = $this->actingAs($admin)->get('/');
    $response->assertOk();

    $response->assertSee('Administrador / Owner');
    $response->assertSee('Dashboard del Sistema');
    $response->assertSee('Categorías globales');
});
