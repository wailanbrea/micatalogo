<?php

use App\Enums\UserPlan;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Support\InventoryWorkbookFixture;

/** Local-only HTTP fixture server. NEVER bootstrap against an existing database. */
require __DIR__.'/../../vendor/autoload.php';

if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite') {
    throw new RuntimeException('Only isolated SQLite testing is allowed.');
}
$database = getenv('DB_DATABASE');
$parent = realpath(dirname($database));
$temp = realpath(sys_get_temp_dir());
if (! $parent || strcasecmp($parent, $temp) !== 0 || ! preg_match('/^inventory-import-e2e-[a-z0-9-]+\.sqlite$/D', basename($database))) {
    throw new RuntimeException('Invalid isolated database path.');
}
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database) {
    throw new RuntimeException('Runtime configuration is not isolated.');
}

if (PHP_SAPI === 'cli') {
    if (file_exists($database)) {
        throw new RuntimeException('Refusing to modify an existing database.');
    }
    touch($database);
    Artisan::call('migrate', ['--force' => true]);
    $user = User::factory()->create(['name' => 'Prueba aislada', 'email' => 'inventory-e2e@example.invalid', 'plan' => UserPlan::Pro]);
    $shop = Shop::factory()->for($user)->create(['name' => 'Inventario E2E']);
    $user->tokens()->create(['name' => 'local-fixture-only', 'token' => hash('sha256', 'isolated-import-e2e'), 'abilities' => ['catalog:read']]);
    InventoryWorkbookFixture::write(sys_get_temp_dir().'/inventory-import-e2e-anonymous.xlsx');
    echo json_encode(['shop_id' => $shop->public_id, 'fixture' => sys_get_temp_dir().'/inventory-import-e2e-anonymous.xlsx', 'database' => $database]);
    exit;
}

// The server is bound to 127.0.0.1; Android uses adb reverse, not public networking.
if (! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit;
}
// Optional catalog media must not call external services during this local fixture test.
Http::fake(['*' => Http::response([], 404)]);
$app->handleRequest(Request::capture());
