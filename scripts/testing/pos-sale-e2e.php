<?php

use App\Enums\UserPlan;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/** Local-only HTTP fixture server. NEVER bootstrap against an existing database. */
require __DIR__.'/../../vendor/autoload.php';

if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite') {
    throw new RuntimeException('Only isolated SQLite testing is allowed.');
}

$database = (string) getenv('DB_DATABASE');
$parent = realpath(dirname($database));
$temp = realpath(sys_get_temp_dir());
if (! $parent || strcasecmp($parent, $temp) !== 0 || ! preg_match('/^pos-sale-e2e-[a-z0-9-]+\.sqlite$/D', basename($database))) {
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

    $user = User::factory()->create([
        'name' => 'POS E2E QA',
        'email' => 'pos-sale-e2e@example.invalid',
        'plan' => UserPlan::Pro,
    ]);
    $shop = Shop::factory()->for($user)->create(['name' => 'POS E2E QA']);
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'name' => 'Producto POS E2E QA',
        'price' => 2500.50,
        'sale_unit' => 'unit',
    ]);
    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 3,
        'cost_price' => 1500.00,
        'sold_quantity' => 0,
        'low_stock_threshold' => 1,
    ]);

    $source = Product::factory()->create([
        'shop_id' => $shop->id,
        'name' => 'Botella fuente POS E2E QA',
        'price' => 1200.00,
        'sale_unit' => 'bottle',
        'volume_ml' => 100,
    ]);
    ProductInventory::create([
        'product_id' => $source->id,
        'track_inventory' => true,
        'stock_quantity' => 1,
        'available_ml' => 100,
        'opened_bottles' => 1,
        'cost_price' => 300.00,
        'sold_quantity' => 0,
        'low_stock_threshold' => 1,
    ]);

    $decant = Product::factory()->create([
        'shop_id' => $shop->id,
        'name' => 'Decant POS E2E QA 10 ml',
        'price' => 50.00,
        'sale_unit' => 'decant',
        'volume_ml' => 10,
        'inventory_source_product_id' => $source->id,
    ]);
    ProductInventory::create([
        'product_id' => $decant->id,
        'track_inventory' => true,
        'stock_quantity' => 10,
        'cost_price' => 30.00,
        'sold_quantity' => 0,
        'low_stock_threshold' => 1,
    ]);

    $plainToken = 'isolated-pos-sale-e2e-token';
    $user->tokens()->create([
        'name' => 'local-pos-fixture-only',
        'token' => hash('sha256', $plainToken),
        'abilities' => ['pos:write', 'customers:write'],
    ]);

    echo json_encode([
        'shop_id' => $shop->public_id,
        'product_id' => $product->public_id,
        'source_product_id' => $source->public_id,
        'decant_product_id' => $decant->public_id,
        'token' => $plainToken,
        'database' => $database,
    ], JSON_THROW_ON_ERROR);
    exit;
}

if (! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit;
}

$app->handleRequest(Request::capture());
