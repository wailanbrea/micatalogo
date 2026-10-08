<?php

declare(strict_types=1);

use App\Enums\UserPlan;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.mysql.database');
if (! str_starts_with($database, 'micatalogo_qa_')) {
    throw new RuntimeException('Concurrency probe requires a database prefixed micatalogo_qa_.');
}

if (($argv[1] ?? null) === 'worker') {
    $product = Product::query()->where('public_id', $argv[2] ?? '')->firstOrFail();
    $barrier = (string) ($argv[3] ?? '');
    while (! is_file($barrier)) {
        usleep(10_000);
    }

    try {
        $movement = app(InventoryService::class)->recordSale($product, 1, 'QA concurrent sale', null, true);
        echo json_encode(['status' => 'success', 'movement_id' => $movement->id], JSON_THROW_ON_ERROR).PHP_EOL;
    } catch (\InvalidArgumentException $exception) {
        echo json_encode(['status' => 'rejected', 'code' => $exception->getCode(), 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR).PHP_EOL;
    }

    exit(0);
}

$user = User::factory()->create([
    'email' => 'qa-concurrency-'.Str::lower(Str::random(12)).'@micatalogo.test',
    'plan' => UserPlan::Pro,
]);
$shop = Shop::factory()->create(['user_id' => $user->id, 'slug' => 'qa-concurrency-'.Str::lower(Str::random(12))]);
$product = Product::factory()->create([
    'shop_id' => $shop->id,
    'name' => 'QA Concurrent Product',
    'price' => '250.00',
    'sale_unit' => 'unit',
]);
ProductInventory::create([
    'product_id' => $product->id,
    'track_inventory' => true,
    'stock_quantity' => 1,
    'cost_price' => '100.00',
    'sold_quantity' => 0,
    'low_stock_threshold' => 0,
]);

$barrier = tempnam(sys_get_temp_dir(), 'micatalogo-qa-concurrency-');
if ($barrier === false) {
    throw new RuntimeException('Could not create the QA concurrency barrier.');
}
unlink($barrier);

$command = fn (): string => escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' worker '.escapeshellarg($product->public_id).' '.escapeshellarg($barrier);
$workers = [];
foreach ([1, 2] as $_) {
    $pipes = [];
    $process = proc_open($command(), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
    if (! is_resource($process)) {
        throw new RuntimeException('Could not start a concurrent QA worker.');
    }
    $workers[] = [$process, $pipes];
}
touch($barrier);

$results = [];
foreach ($workers as [$process, $pipes]) {
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $results[] = [
        'exit_code' => $exitCode,
        'stdout' => trim($stdout),
        'stderr' => trim($stderr),
    ];
}
@unlink($barrier);

$inventory = $product->fresh()->inventory;
$invoiceCount = Invoice::query()->where('shop_id', $shop->id)->count();
$movementCount = $product->inventoryMovements()->where('type', 'sale')->count();
$successes = collect($results)->filter(fn (array $result): bool => str_contains($result['stdout'], '"status":"success"'))->count();
$rejections = collect($results)->filter(fn (array $result): bool => str_contains($result['stdout'], '"status":"rejected"'))->count();

$summary = [
    'database' => $database,
    'workers' => $results,
    'successes' => $successes,
    'rejections' => $rejections,
    'remaining_stock' => (int) $inventory->stock_quantity,
    'sale_movements' => $movementCount,
    'invoices' => $invoiceCount,
    'fixture_shop_id' => $shop->id,
    'fixture_product_id' => $product->id,
];
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;

if ($successes !== 1 || $rejections !== 1 || (int) $inventory->stock_quantity !== 0 || $movementCount !== 1 || $invoiceCount !== 1) {
    exit(1);
}
