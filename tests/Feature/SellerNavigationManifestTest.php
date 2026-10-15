<?php

use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\ShopSeller;
use App\Models\User;
use App\Services\SellerMenuService;
use App\Services\SellerNavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

test('repeated shell navigation rendering performs no additional database queries and remains request scoped', function () {
    $shop = Shop::factory()->create();
    $owner = $shop->user;
    $navigation = app(SellerNavigationService::class);
    $first = $navigation->forUser($shop, $owner, 'web');
    Illuminate\Support\Facades\DB::enableQueryLog();
    Illuminate\Support\Facades\DB::flushQueryLog();
    expect($navigation->forUser($shop, $owner, 'web'))->toBe($first)
        ->and(Illuminate\Support\Facades\DB::getQueryLog())->toBe([]);
    Illuminate\Support\Facades\DB::disableQueryLog();

    $shop->update(['enabled_menu_keys' => []]);
    $this->app->instance('request', Illuminate\Http\Request::create('/next-page'));
    $next = $navigation->forUser($shop->fresh(), $owner, 'web');
    expect(navigationItems($next)->pluck('id')->all())->not->toContain('pos', 'decants');
});

function navigationItems(array $manifest): Collection
{
    return collect($manifest['sections'])->concat($manifest['contextual_sections'])->pluck('items')->flatten(1)
        ->concat($manifest['secondary']);
}

test('navigation registry accounts for every legacy key without turning areas into permissions', function () {
    $definitions = collect(config('seller-navigation.sections'))->concat(config('seller-navigation.contextual'))
        ->pluck('items')->flatten(1)->concat(config('seller-navigation.secondary'));
    $keys = $definitions->pluck('key')->filter()->unique()->concat(config('seller-navigation.container_keys'))->sort()->values()->all();

    expect($keys)->toBe(collect(app(SellerMenuService::class)->keys())->sort()->values()->all())
        ->and(array_keys(config('seller-navigation.sections')))->toBe([
            'home', 'sales', 'orders', 'catalog', 'inventory', 'purchases', 'customers', 'finance', 'reports', 'storefront', 'settings',
        ])->and($definitions->pluck('id')->unique()->count())->toBe($definitions->count());
});

test('a restricted seller receives only independent children and never other siblings', function (string $key, string $area) {
    $owner = User::factory()->create(['plan' => 'pro']);
    $seller = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create(['business_type' => 'perfume_store']);
    ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $seller->id, 'is_active' => true,
        'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions' => [$key]]);
    $token = $seller->createToken('navigation')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/shops')->assertOk();
    $manifest = $response->json('0.navigation');
    expect($manifest['schema_version'])->toBe(1)
        ->and(collect($manifest['sections'])->pluck('id')->all())->toBe(['home', $area])
        ->and(navigationItems($manifest)->pluck('key')->filter(fn ($key) => $key !== '' && in_array($key, ['account', 'help'], true) === false)->unique()->values()->all())->toBe([$key])
        ->and($response->json('0.menu_permissions'))->toBe([$key]);
})->with(['cash only' => ['cash', 'sales'], 'expenses only' => ['expenses', 'finance'],
    'services only' => ['services', 'catalog'], 'products only' => ['products', 'catalog'],
    'reports only' => ['reports', 'reports'], 'collections only' => ['collections', 'customers'],
    'credit only' => ['credit', 'customers'], 'orders only' => ['orders', 'orders']]);

test('owner retains control access with every operational menu disabled and manager does not inherit it', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $manager = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create(['enabled_menu_keys' => []]);
    ShopMember::create(['shop_id' => $shop->id, 'user_id' => $manager->id, 'role' => 'manager', 'is_active' => true]);
    $service = app(SellerNavigationService::class);
    $ownerNavigation = $service->forUser($shop, $owner);
    $managerNavigation = $service->forUser($shop, $manager);

    expect(collect($ownerNavigation['sections'])->pluck('id')->all())->toBe(['home', 'settings'])
        ->and(navigationItems($ownerNavigation)->pluck('id')->all())->toContain('shop_menus', 'sellers')
        ->and(collect($managerNavigation['sections'])->pluck('id')->all())->toBe(['home'])
        ->and(navigationItems($managerNavigation)->pluck('key')->filter()->values()->all())->toBe(['account', 'help'])
        ->and($shop->fresh()->enabled_menu_keys)->toBe([]);
});

test('legacy null keeps shop defaults and seller null keeps limited delegation', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $seller = User::factory()->create();
    $shop = Shop::factory()->for($owner)->create(['enabled_menu_keys' => null]);
    $assignment = ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $seller->id, 'is_active' => true,
        'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions' => null]);
    $manifest = app(SellerNavigationService::class)->forUser($shop, $seller);

    expect(navigationItems($manifest)->pluck('key')->filter()->unique()->sort()->values()->all())->toBe(['account', 'help', 'printers', 'products', 'sales'])
        ->and($shop->fresh()->enabled_menu_keys)->toBeNull()
        ->and($assignment->fresh()->menu_permissions)->toBeNull();
});

test('accountant gets read only financial destinations without team or owner controls', function () {
    $shop = Shop::factory()->create();
    $accountant = User::factory()->create();
    ShopMember::create(['shop_id' => $shop->id, 'user_id' => $accountant->id, 'role' => 'accountant', 'is_active' => true]);
    $manifest = app(SellerNavigationService::class)->forUser($shop, $accountant, 'web');

    expect(collect($manifest['sections'])->pluck('id')->all())->toBe(['home', 'finance', 'reports'])
        ->and(navigationItems($manifest)->pluck('key')->filter()->unique()->values()->all())->toBe(['finance', 'reports', 'account', 'help']);
});

test('canonical navigation reuses effective capabilities across business types and plans', function () {
    foreach (['free', 'premium', 'pro'] as $plan) {
        $owner = User::factory()->create(['plan' => $plan]);
        foreach (['perfume_store', 'barbershop', 'grocery', 'professional_services', 'food_restaurant'] as $type) {
            $shop = Shop::factory()->for($owner)->create(['business_type' => $type]);
            $manifest = app(SellerNavigationService::class)->forUser($shop, $owner);
            $visible = app(SellerMenuService::class)->visibleForUser($shop, $owner);
            foreach (navigationItems($manifest) as $item) {
                if ($item['key'] !== '' && in_array($item['key'], ['account', 'help'], true) === false) {
                    expect($visible)->toContain($item['key']);
                }
            }
            if (in_array($type, ['barbershop', 'grocery', 'professional_services', 'food_restaurant'], true)) {
                expect(navigationItems($manifest)->pluck('id')->all())->not->toContain('decants');
            }
            if (in_array($type, ['professional_services', 'food_restaurant'], true)) {
                expect(navigationItems($manifest)->pluck('id')->all())->not->toContain('inventory', 'fifo');
            }
        }
    }
});

test('web excludes native only destinations and retains protected feature fallbacks', function () {
    $shop = Shop::factory()->create();
    $seller = User::factory()->create();
    ShopSeller::create(['shop_id' => $shop->id, 'user_id' => $seller->id, 'is_active' => true,
        'commission_type' => 'percentage', 'commission_value' => 5, 'menu_permissions' => ['pricing', 'import', 'printers', 'routes']]);
    $web = app(SellerNavigationService::class)->forUser($shop, $seller, 'web');
    $items = navigationItems($web);
    expect($items->pluck('id')->all())->not->toContain('printers', 'routes', 'route_loads')
        ->and($items->firstWhere('id', 'pricing')['feature'])->toBe('pricing')
        ->and($items->firstWhere('id', 'import')['feature'])->toBe('import');
    foreach ($items as $item) {
        expect($item['url'])->toBeString()->not->toBeEmpty();
    }
});

test('multi shop navigation is tenant scoped and platform admin receives no implicit shop controls', function () {
    $owner = User::factory()->create(['plan' => 'pro']);
    $admin = User::factory()->create(['role' => 'admin']);
    $shop = Shop::factory()->for($owner)->create(['business_type' => 'perfume_store']);
    $other = Shop::factory()->for($owner)->create(['business_type' => 'barbershop', 'enabled_menu_keys' => ['services']]);
    $outsider = Shop::factory()->create();
    $token = $owner->createToken('navigation')->plainTextToken;
    $shops = collect($this->withToken($token)->getJson('/api/v1/shops')->assertOk()->json())->keyBy('id');

    expect($shops->keys()->all())->toContain($shop->public_id, $other->public_id)->not->toContain($outsider->public_id)
        ->and(navigationItems($shops[$other->public_id]['navigation'])->pluck('id')->all())->toContain('services')->not->toContain('decants', 'pos');
    app('auth')->forgetGuards();
    $this->withToken($admin->createToken('admin')->plainTextToken)->getJson('/api/v1/shops')->assertOk()->assertExactJson([]);
    try {
        app(SellerNavigationService::class)->forUser($shop, $admin);
        $this->fail('A global administrator must not bypass the shop context boundary');
    } catch (HttpException $error) {
        expect($error->getStatusCode())->toBe(404);
    }
});
