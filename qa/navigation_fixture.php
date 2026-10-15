<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
$action = $argv[1] ?? 'identity';
$snapshot = 'C:/Users/waila/AppData/Local/Temp/opencode/micatalogo-navigation-20261014.sqlite';
$expected = $action === 'init' ? ':memory:' : $snapshot;
if (! $app->environment('testing') || $db->getDriverName() !== 'sqlite' || str_replace('\\', '/', $db->getDatabaseName()) !== $expected) {
    throw new RuntimeException('Navigation QA requires its verified SQLite connection.');
}
echo json_encode(['environment'=>'testing','driver'=>'sqlite','host'=>'none (local SQLite)','database'=>$expected]).PHP_EOL;
if ($action === 'init') {
    if (file_exists($snapshot)) throw new RuntimeException('Existing QA snapshot is preserved; initialization refuses to overwrite it.');
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force'=>true]);
    $owner = App\Models\User::factory()->create(['name'=>'QA_NAV_Owner','email'=>'qa-nav-owner@example.test','password'=>Illuminate\Support\Facades\Hash::make('QA-NAV-2026!'),'plan'=>'pro']);
    $shop = App\Models\Shop::factory()->for($owner)->create(['name'=>'QA_NAV_Perfumes','slug'=>'qa-nav-perfumes','business_type'=>'perfume_store']);
    $category = $shop->categories()->create(['name'=>'QA_NAV_Categoría','slug'=>'qa-nav-category','status'=>'active']);
    $product = App\Models\Product::factory()->for($shop)->create(['name'=>'QA_NAV_Producto','price'=>'300.00','sale_unit'=>'unit','shop_category_id'=>$category->id,'moderation_status'=>App\Enums\ProductModerationStatus::Active]);
    App\Models\ProductInventory::create(['product_id'=>$product->id,'track_inventory'=>true,'stock_quantity'=>20,'sold_quantity'=>0,'cost_price'=>'120.00']);
    App\Models\Customer::create(['shop_id'=>$shop->id,'name'=>'QA_NAV_Cliente','credit_limit'=>10000,'balance'=>0]);
    foreach (['manager','accountant','sales','cash','expenses','services','reports'] as $role) {
        $actor=App\Models\User::factory()->create(['name'=>'QA_NAV_'.ucfirst($role),'email'=>"qa-nav-$role@example.test",'password'=>Illuminate\Support\Facades\Hash::make('QA-NAV-2026!'),'plan'=>'pro']);
        if (in_array($role,['manager','accountant'],true)) {
            App\Models\ShopMember::create(['shop_id'=>$shop->id,'user_id'=>$actor->id,'role'=>$role,'is_active'=>true]);
        } else {
            App\Models\ShopSeller::create(['shop_id'=>$shop->id,'user_id'=>$actor->id,'is_active'=>true,'commission_type'=>'percentage','commission_value'=>5,'menu_permissions'=>[$role,'account','help']]);
        }
    }
    foreach (['barber'=>'barbershop','grocery'=>'grocery'] as $role=>$type) {
        $actor=App\Models\User::factory()->create(['name'=>'QA_NAV_'.ucfirst($role),'email'=>"qa-nav-$role@example.test",'password'=>Illuminate\Support\Facades\Hash::make('QA-NAV-2026!'),'plan'=>'pro']);
        App\Models\Shop::factory()->for($actor)->create(['name'=>'QA_NAV_'.ucfirst($role),'slug'=>'qa-nav-'.$role,'business_type'=>$type]);
    }
    $db->getPdo()->exec("VACUUM INTO '$snapshot'");
    echo 'Created dedicated QA_NAV fixtures; no commercial data or previous snapshot was overwritten.'.PHP_EOL;
} elseif ($action === 'inspect') {
    $shops=App\Models\Shop::where('slug','like','qa-nav-%')->get();
    echo json_encode(['shops'=>$shops->map(fn($s)=>['id'=>$s->public_id,'name'=>$s->name,'business_type'=>$s->business_type,'enabled_menu_keys'=>$s->enabled_menu_keys,
        'products'=>$s->products()->with('inventory')->get()->map(fn($p)=>['id'=>$p->public_id,'stock'=>$p->inventory?->stock_quantity,'cost'=>$p->inventory?->cost_price,'sold'=>$p->inventory?->sold_quantity])->all(),
        'customers'=>$s->customers()->get(['public_id','balance'])->all(),'invoices'=>$s->invoices()->count(),
         'cash_sessions'=>$s->cashRegisterSessions()->count()])->all()],JSON_UNESCAPED_UNICODE).PHP_EOL;
} elseif ($action === 'permissions') {
    $menus = app(App\Services\SellerMenuService::class);
    $profiles = app(App\Services\BusinessProfileService::class);
    $roles = [];
    foreach (['owner','manager','accountant','sales','cash','expenses','services','reports','barber','grocery'] as $role) {
        $actor = App\Models\User::where('email', "qa-nav-$role@example.test")->firstOrFail();
        $shop = in_array($role, ['barber','grocery'], true) ? $actor->shops()->firstOrFail() : App\Models\Shop::where('slug','qa-nav-perfumes')->firstOrFail();
        $roles[$role] = ['shop'=>$shop->public_id, 'business_type'=>$shop->business_type,
            'visible_menu_keys'=>$menus->visibleForUser($shop, $actor),
            'capabilities'=>$profiles->capabilities($shop),
            'can_manage'=>$menus->canManage($shop, $actor),
            'can_manage_menu_visibility'=>$menus->canManageMenuVisibility($shop, $actor)];
    }
    echo json_encode($roles, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
}
