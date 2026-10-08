<?php

declare(strict_types=1);

use App\Enums\UserPlan;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\MobileOperationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.mysql.database');
if (! str_starts_with($database, 'micatalogo_qa_')) {
    throw new RuntimeException('Product identity concurrency probe requires a database prefixed micatalogo_qa_.');
}

$scenario = (string) ($argv[1] ?? '');

if ($scenario === 'worker') {
    $shopId = (int) ($argv[2] ?? 0);
    $userId = (int) ($argv[3] ?? 0);
    $productId = (string) ($argv[4] ?? '');
    $workerNumber = (int) ($argv[5] ?? 0);
    $barrier = (string) ($argv[6] ?? '');

    while (! is_file($barrier)) {
        usleep(10_000);
    }

    try {
        $result = app(MobileOperationService::class)->apply(
            Shop::query()->findOrFail($shopId),
            User::query()->findOrFail($userId),
            [
                'client_operation_uuid' => (string) Str::uuid(),
                'type' => 'product_upsert',
                'product_id' => $productId,
                'name' => "QA creación manual concurrente {$workerNumber}",
                'price' => '250.00',
                'cost_price' => '100.00',
                'internal_code' => 'QA-MANUAL-DUP-001',
                'barcode' => '7501234567890',
                'published' => true,
            ]
        );

        echo json_encode([
            'status' => 'success',
            'worker' => $workerNumber,
            'product_id' => $result['product_id'] ?? null,
        ], JSON_THROW_ON_ERROR).PHP_EOL;
        exit(0);
    } catch (Throwable $exception) {
        echo json_encode([
            'status' => 'error',
            'worker' => $workerNumber,
            'exception' => $exception::class,
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
        ], JSON_THROW_ON_ERROR).PHP_EOL;
        exit(1);
    }
}

if ($scenario !== 'run') {
    throw new InvalidArgumentException('Usage: php qa/product_identity_concurrency_probe.php run');
}

$user = User::factory()->create([
    'email' => 'qa-product-identity-'.Str::lower(Str::random(12)).'@micatalogo.test',
    'plan' => UserPlan::Pro,
]);
$shop = Shop::factory()->create([
    'user_id' => $user->id,
    'slug' => 'qa-product-identity-'.Str::lower(Str::random(12)),
]);

$barrier = tempnam(sys_get_temp_dir(), 'micatalogo-qa-product-identity-');
if ($barrier === false) {
    throw new RuntimeException('Could not create the QA concurrency barrier.');
}
unlink($barrier);

$workerCount = 8;
$command = fn (int $worker): string => implode(' ', array_map('escapeshellarg', [
    PHP_BINARY,
    __FILE__,
    'worker',
    (string) $shop->id,
    (string) $user->id,
    (string) Str::ulid(),
    (string) $worker,
    $barrier,
]));

$workers = [];
foreach (range(1, $workerCount) as $worker) {
    $pipes = [];
    $process = proc_open($command($worker), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
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
    $results[] = [
        'exit_code' => proc_close($process),
        'stdout' => trim($stdout),
        'stderr' => trim($stderr),
    ];
}
@unlink($barrier);

$successes = collect($results)->filter(fn (array $result): bool => str_contains($result['stdout'], '"status":"success"'))->count();
$errors = collect($results)->filter(fn (array $result): bool => str_contains($result['stdout'], '"status":"error"'))->count();
$products = Product::query()->where('shop_id', $shop->id)->where('product_code', 'QA-MANUAL-DUP-001')->get();
$barcodeMatches = $products->filter(fn (Product $product): bool => preg_replace('/[\s-]+/', '', (string) $product->barcode) === '7501234567890');

$summary = [
    'database' => $database,
    'worker_count' => $workerCount,
    'successes' => $successes,
    'errors' => $errors,
    'products_with_shared_sku' => $products->count(),
    'products_with_shared_barcode' => $barcodeMatches->count(),
    'shop_id' => $shop->id,
    'workers' => $results,
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;

$passed = $successes === 1
    && $errors === $workerCount - 1
    && $products->count() === 1
    && $barcodeMatches->count() === 1;

if (! $passed) {
    exit(1);
}
