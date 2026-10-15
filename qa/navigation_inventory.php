<?php

// Read-only F0 export; never boot the production connection for navigation QA.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if (! $app->environment('testing') || $db->getDriverName() !== 'sqlite' || $db->getDatabaseName() !== ':memory:') {
    throw new RuntimeException('F0 export requires testing and SQLite :memory:.');
}
$routes = [];
foreach (Illuminate\Support\Facades\Route::getRoutes() as $route) {
    $action = $route->getActionName(); $source = null; $line = null; $guards = [];
    if (str_contains($action, '@')) {
        [$class, $method] = explode('@', $action, 2);
        if (class_exists($class) && method_exists($class, $method)) {
            $reflection = new ReflectionMethod($class, $method);
            $source = str_replace(base_path().DIRECTORY_SEPARATOR, '', $reflection->getFileName());
            $line = $reflection->getStartLine();
            $lines = file($reflection->getFileName());
            $body = implode('', array_slice($lines, $line - 1, $reflection->getEndLine() - $line + 1));
            preg_match_all('/[^\r\n]*(?:authorize|abort_unless|abort_if|Gate::|canSellAtShop|canManage|ownsShop|assertFeature|assertCan|shop_id)[^\r\n]*/', $body, $matches);
            $guards = array_map('trim', $matches[0]);
        }
    }
    $routes[] = ['methods' => $route->methods(), 'uri' => $route->uri(), 'name' => $route->getName(), 'action' => $action,
        'middleware' => $route->gatherMiddleware(), 'source' => $source, 'line' => $line, 'guard_evidence' => $guards,
        'writes' => count(array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS'])) > 0];
}
$result = ['environment' => 'testing', 'driver' => 'sqlite', 'database' => ':memory:',
    'menu_keys' => config('bspos.seller_menu_options'), 'owner_only_keys' => config('bspos.owner_only_menu_options'), 'routes' => $routes];
$destination = $argv[1] ?? null;
if ($destination) {
    if (! is_dir(dirname($destination))) throw new RuntimeException('Inventory destination parent must exist.');
    file_put_contents($destination, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    echo 'F0 read-only inventory: '.count($routes).' routes, '.count($result['menu_keys']).' menu keys; testing/sqlite/:memory:'.PHP_EOL;
} else echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
