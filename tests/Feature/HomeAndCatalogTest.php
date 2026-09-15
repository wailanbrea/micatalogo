<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('homepage renders seller landing page and never displays cross-store products', function () {
    $this->seed(DatabaseSeeder::class);

    $response = $this->get('/');
    $response->assertOk();

    // Verify value propositions are present
    $response->assertSee('Crea tu catálogo gratis');
    $response->assertSee('Sube tus productos una vez');
    $response->assertSee('Comparte un solo enlace con tus clientes');
    $response->assertSee('Vitrina 100% Aislada');
    $response->assertSee('Pedidos por WhatsApp');

    // Verify home does NOT leak or promote specific products or stores
    $response->assertDontSee('CRM WhatsApp Multiagente');
    $response->assertDontSee('TicketPro');
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
