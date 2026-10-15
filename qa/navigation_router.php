<?php

// Local QA HTTP router, never registered in the application's production routes.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
$snapshot='C:/Users/waila/AppData/Local/Temp/opencode/micatalogo-navigation-20261014.sqlite';
if (! $app->environment('testing') || $db->getDriverName()!=='sqlite' || str_replace('\\','/',$db->getDatabaseName())!==$snapshot || !in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)) {
    http_response_code(403); exit('QA connection is not verified.');
}
$path=parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path==='/__qa/navigation/identity') {
    header('Content-Type: application/json'); echo json_encode(['environment'=>'testing','driver'=>'sqlite','host'=>'none (local SQLite)','database'=>$snapshot]); return;
}
Illuminate\Support\Facades\Route::middleware('web')->get('/__qa/navigation/login/{role}', function(string $role) {
    abort_unless(in_array($role,['owner','manager','accountant','sales','cash','expenses','services','reports','barber','grocery'],true),404);
    $actor=App\Models\User::where('email',"qa-nav-$role@example.test")->firstOrFail();
    Illuminate\Support\Facades\Auth::login($actor);
    request()->session()->regenerate();
    $shop=in_array($role,['barber','grocery'],true)?$actor->shops()->firstOrFail():App\Models\Shop::where('slug','qa-nav-perfumes')->firstOrFail();
    return match ($role) {
        'cash' => redirect()->route('seller.shops.cash.index', $shop),
        'expenses' => redirect()->route('seller.shops.expenses.index', $shop),
        'reports' => redirect()->route('seller.shops.feature', ['shop'=>$shop, 'feature'=>'reports']),
        'services' => redirect()->route('seller.shops.feature', ['shop'=>$shop, 'feature'=>'services']),
        'accountant' => redirect()->route('seller.shops.business', $shop),
        default => redirect()->route('seller.shops.summary',$shop),
    };
});
if ($path!=='/' && is_file(__DIR__.'/../public'.$path)) return false;
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
$request=Illuminate\Http\Request::capture();
$response=$kernel->handle($request);$response->send();$kernel->terminate($request,$response);
