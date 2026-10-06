<?php

use App\Enums\UserPlan;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\BusinessProfileService;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.turnstile.enabled', false);
});

test('registration creates an isolated free shop and applies its business preset', function () {
    $user = app(RegistrationService::class)->create([
        'name' => 'Ana Vendedora',
        'email' => 'ana@example.com',
        'business_name' => 'Ana Perfumes',
        'business_type' => 'perfume_store',
        'whatsapp_country_code' => '1809',
        'whatsapp_number' => '8095550101',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms_accepted' => '1',
    ]);

    $shop = $user->shops()->firstOrFail();

    expect($user->plan)->toBe(UserPlan::Free)
        ->and($shop->name)->toBe('Ana Perfumes')
        ->and($shop->business_type)->toBe('perfume_store')
        ->and($shop->slug)->toBe('ana-perfumes')
        ->and($shop->categories()->orderBy('sort_order')->pluck('name')->all())->toBe(['Perfumes', 'Sets', 'Body Splash', 'Decants', 'Accesorios']);
});

test('reserved and colliding slugs are made unique without replacing an existing shop', function () {
    Shop::factory()->create(['slug' => 'mi-negocio']);

    $user = app(RegistrationService::class)->create([
        'name' => 'Otra Persona',
        'email' => 'otra@example.com',
        'business_name' => 'Mi Negocio',
        'business_type' => 'general_retail',
        'slug' => 'mi-negocio',
        'whatsapp_country_code' => '1809',
        'whatsapp_number' => '8095550102',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms_accepted' => '1',
    ]);

    expect($user->shops()->value('slug'))->toBe('mi-negocio-2');
    expect(Shop::query()->where('slug', 'mi-negocio')->count())->toBe(1);
});

test('unsupported business types and paid capabilities are enforced centrally', function () {
    $shop = Shop::factory()->create(['business_type' => 'barbershop']);

    expect(app(BusinessProfileService::class)->allows($shop, 'decants'))->toBeFalse()
        ->and(app(BusinessProfileService::class)->capabilities($shop)['services'])->toBe('unsupported');

    $perfumeShop = Shop::factory()->create([
        'business_type' => 'perfume_store',
        'user_id' => User::factory()->create(['plan' => UserPlan::Pro])->id,
    ]);
    expect(app(BusinessProfileService::class)->allows($perfumeShop, 'decants'))->toBeTrue();
});

test('legacy general shops with decants keep their existing capability', function () {
    $shop = Shop::factory()->create(['business_type' => 'general_retail']);
    Product::factory()->for($shop)->create(['sale_unit' => 'decant']);

    expect(app(BusinessProfileService::class)->allows($shop, 'decants'))->toBeTrue();
});
