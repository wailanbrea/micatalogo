<?php

// Standalone CLI: only initializes a NEW QA SQLite snapshot, never refreshes an existing database.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$action = $argv[1] ?? 'identity';
$snapshot = 'C:/Users/waila/AppData/Local/Temp/opencode/micatalogo-sync-20261012.sqlite';
$db = Illuminate\Support\Facades\DB::connection();
$expected = $action === 'init' ? ':memory:' : $snapshot;
if (! $app->environment('testing') || $db->getDriverName() !== 'sqlite' || str_replace('\\', '/', $db->getDatabaseName()) !== $expected) {
    throw new RuntimeException('Sync QA requires testing and its explicitly isolated SQLite connection.');
}
echo json_encode(['environment' => $app->environment(), 'connection' => $db->getName(), 'driver' => $db->getDriverName(),
    'host' => 'none (local SQLite)', 'database' => $db->getDatabaseName()]).PHP_EOL;
if ($action === 'init') {
    if (file_exists($snapshot)) throw new RuntimeException('The QA snapshot already exists; it will not be overwritten.');
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    $db->getPdo()->exec("VACUUM INTO '$snapshot'");
    echo 'Created a new schema-only QA snapshot; each scenario appends uniquely owned fixtures.'.PHP_EOL;
} elseif ($action === 'inspect') {
    foreach (App\Models\Shop::whereHas('user', fn ($query) => $query->where('email', 'like', 'sync-qa-%@example.test'))->with('user')->get() as $shop) {
        echo json_encode(['shop_id' => $shop->public_id, 'name' => $shop->name, 'owner' => $shop->user->email,
            'products' => $shop->products()->count(), 'customers' => $shop->customers()->count(), 'invoices' => $shop->invoices()->count(),
            'operations' => App\Models\MobileOperation::where('shop_id', $shop->id)->count(),
            'records' => App\Models\ShopSyncRecord::where('shop_id', $shop->id)->count()], JSON_UNESCAPED_UNICODE).PHP_EOL;
    }
}
