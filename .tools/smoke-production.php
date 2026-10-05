<?php

if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('production')) { throw new RuntimeException('Production only.'); }
try {
Illuminate\Support\Facades\View::share('errors', new Illuminate\Support\ViewErrorBag);
$shop = App\Models\Shop::where('slug', 'bsolutions-dev')->firstOrFail();
Illuminate\Support\Facades\Auth::guard()->setUser($shop->user);
$request = Illuminate\Http\Request::create('/panel/tiendas/'.$shop->public_id.'/negocio', 'GET');
$request->setUserResolver(fn() => $shop->user);
$route = new Illuminate\Routing\Route('GET', 'panel/tiendas/{shop}/negocio', fn() => null);
$route->bind($request);
$route->setParameter('shop', $shop);
$request->setRouteResolver(fn() => $route);
$app->instance('request', $request);
$html = app(App\Http\Controllers\SellerBusinessController::class)->index($request, $shop)->render();
$apkUrlPath = parse_url((string) config('bspos.android_update.apk_url'), PHP_URL_PATH);
$apkPath = is_string($apkUrlPath)
    ? public_path(str_replace('/', DIRECTORY_SEPARATOR, ltrim($apkUrlPath, '/')))
    : null;
$expectedApkHash = strtolower((string) config('bspos.android_update.apk_sha256'));
$actualApkHash = $apkPath && is_file($apkPath) ? strtolower((string) hash_file('sha256', $apkPath)) : '';
$checks = ['business_render' => str_contains($html, 'Ventas y ganancia por producto'),
    'fifo_table' => Illuminate\Support\Facades\Schema::hasTable('inventory_lots'),
    'invoice_cost_column' => Illuminate\Support\Facades\Schema::hasColumn('invoice_items', 'total_cost_cents'),
    'apk_hash' => $expectedApkHash !== '' && $actualApkHash !== '' && hash_equals($expectedApkHash, $actualApkHash)];
echo json_encode($checks, JSON_PRETTY_PRINT);
exit(in_array(false, $checks, true) ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, 'Smoke validation failed: '.$error->getMessage().PHP_EOL);
    exit(1);
}
