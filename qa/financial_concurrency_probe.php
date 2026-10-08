<?php

declare(strict_types=1);

use App\Enums\UserPlan;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\DailyClosure;
use App\Models\Invoice;
use App\Models\InventoryMovement;
use App\Models\InventoryImportSession;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\PurchaseDocument;
use App\Models\Shop;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\PaymentService;
use App\Services\PurchaseDocumentService;
use App\Services\CatalogMediaService;
use App\Services\InventoryImportService;
use App\Services\PlanLimitsService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.mysql.database');
if (! str_starts_with($database, 'micatalogo_qa_')) {
    throw new RuntimeException('Financial concurrency probe requires a database prefixed micatalogo_qa_.');
}

$scenario = (string) ($argv[1] ?? '');

if ($scenario === 'worker') {
    $kind = (string) ($argv[2] ?? '');
    $shopId = (int) ($argv[3] ?? 0);
    $userId = (int) ($argv[4] ?? 0);
    $recordId = (int) ($argv[5] ?? 0);
    $uuid = (string) ($argv[6] ?? '');
    $hash = (string) ($argv[7] ?? '');
    $barrier = (string) ($argv[8] ?? '');
    while (! is_file($barrier)) {
        usleep(10_000);
    }

    try {
        $shop = Shop::query()->findOrFail($shopId);
        $user = User::query()->findOrFail($userId);

        $result = match ($kind) {
            'debt_payment' => app(PaymentService::class)->recordCustomerDebtPayment(
                $shop,
                Customer::query()->findOrFail($recordId),
                $user,
                '50.00',
                'card',
                $uuid,
                $hash,
                'QA concurrent debt payment',
                'QA-CONCURRENT'
            ),
            'cash_movement' => app(CashRegisterService::class)->recordManualMovement(
                CashRegisterSession::query()->findOrFail($recordId),
                $user,
                'cash_in',
                '50.00',
                'QA concurrent cash movement',
                $uuid,
                $hash
            ),
            'purchase_receive' => app(PurchaseDocumentService::class)->receive(
                $shop,
                PurchaseDocument::query()->findOrFail($recordId),
                $user
            ),
            'daily_close' => app(\App\Services\DailyCloseService::class)->close(
                $shop,
                $user,
                now()->toDateString(),
                '0.00',
                'QA concurrent daily close'
            ),
            'inventory_import' => app(InventoryImportService::class)->confirmSession(
                $shop,
                InventoryImportSession::query()->findOrFail($recordId),
                [],
                app(PlanLimitsService::class),
                app(CatalogMediaService::class),
                $user
            ),
            default => throw new InvalidArgumentException("Unknown scenario: {$kind}"),
        };

        echo json_encode(['status' => 'success', 'result_id' => $result->id ?? null], JSON_THROW_ON_ERROR).PHP_EOL;
    } catch (Throwable $exception) {
        echo json_encode([
            'status' => 'error',
            'exception' => $exception::class,
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
        ], JSON_THROW_ON_ERROR).PHP_EOL;
        exit(1);
    }

    exit(0);
}

if (! in_array($scenario, ['debt_payment', 'cash_movement', 'purchase_receive', 'daily_close', 'inventory_import'], true)) {
    throw new InvalidArgumentException('Usage: php qa/financial_concurrency_probe.php debt_payment|cash_movement|purchase_receive|daily_close|inventory_import');
}

$user = User::factory()->create([
    'email' => 'qa-financial-'.Str::lower(Str::random(12)).'@micatalogo.test',
    'plan' => UserPlan::Pro,
]);
$shop = Shop::factory()->create([
    'user_id' => $user->id,
    'slug' => 'qa-financial-'.Str::lower(Str::random(12)),
]);
$record = null;

if ($scenario === 'debt_payment') {
    $customer = Customer::query()->create([
        'shop_id' => $shop->id,
        'name' => 'QA concurrent customer',
        'credit_limit' => '500.00',
        'balance' => '100.00',
        'is_active' => true,
    ]);
    $record = Invoice::query()->create([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'invoice_number' => 'QA-CONCURRENT-'.Str::upper(Str::random(10)),
        'status' => 'pending',
        'channel' => 'pos',
        'currency' => 'DOP',
        'subtotal' => '100.00',
        'total' => '100.00',
        'issued_at' => now(),
    ]);
    $expected = ['entries' => 1, 'payments' => 1, 'balance' => '50.00'];
    $workerRecordId = $customer->id;
} elseif ($scenario === 'cash_movement') {
    $record = app(CashRegisterService::class)->openSession($shop, $user, '0.00', 'QA concurrent cash session');
    $expected = ['movements' => 1, 'amount_cents' => 5000];
    $workerRecordId = $record->id;
} elseif ($scenario === 'purchase_receive') {
    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'name' => 'QA concurrent purchase product',
        'price' => '250.00',
        'sale_unit' => 'unit',
    ]);
    ProductInventory::query()->create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 0,
        'cost_price' => '0.00',
        'sold_quantity' => 0,
        'low_stock_threshold' => 0,
    ]);
    $record = app(PurchaseDocumentService::class)->create($shop, $user, [
        'type' => 'purchase',
        'document_number' => 'QA-CONCURRENT-'.Str::upper(Str::random(10)),
        'mode' => 'draft',
        'items' => [[
            'product_id' => $product->public_id,
            'quantity' => 2,
            'unit_cost' => '100.00',
        ]],
    ]);
    $expected = ['movement_count' => 1, 'stock' => 2, 'status' => 'received'];
    $workerRecordId = $record->id;
} elseif ($scenario === 'daily_close') {
    $expected = ['closures' => 1];
    $workerRecordId = 0;
} else {
    $record = InventoryImportSession::query()->create([
        'shop_id' => $shop->id,
        'user_id' => $user->id,
        'original_filename' => 'qa-concurrent-import.csv',
        'status' => 'previewed',
        'total_rows' => 1,
        'valid_rows' => 1,
        'invalid_rows' => 0,
        'rows_payload' => [[
            'line' => 2,
            'status' => 'new',
            'valid' => true,
            'name' => 'QA concurrent imported product',
            'price' => '250.00',
            'cost_price' => '100.00',
            'stock' => 2,
            'product_code' => 'QA-CONCURRENT-IMPORT-001',
            'barcode' => null,
            'category' => null,
            'brand' => null,
            'description' => null,
            'notes' => null,
            'attributes' => [],
        ]],
        'missing_categories' => [],
        'mapping' => [],
        'expires_at' => now()->addHour(),
    ]);
    $expected = ['products' => 1, 'status' => 'confirmed', 'created' => 1];
    $workerRecordId = $record->id;
}

$uuid = (string) Str::uuid();
$hash = hash('sha256', "qa-financial-concurrency:{$scenario}");
$barrier = tempnam(sys_get_temp_dir(), 'micatalogo-qa-financial-');
if ($barrier === false) {
    throw new RuntimeException('Could not create the QA concurrency barrier.');
}
unlink($barrier);

$command = fn (): string => implode(' ', array_map('escapeshellarg', [
    PHP_BINARY,
    __FILE__,
    'worker',
    $scenario,
    (string) $shop->id,
    (string) $user->id,
    (string) $workerRecordId,
    $uuid,
    $hash,
    $barrier,
]));

$workerCount = $scenario === 'purchase_receive' ? 6 : 8;
$workers = [];
foreach (range(1, $workerCount) as $_) {
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
    $results[] = [
        'exit_code' => proc_close($process),
        'stdout' => trim($stdout),
        'stderr' => trim($stderr),
    ];
}
@unlink($barrier);

$successes = collect($results)->filter(fn (array $result): bool => str_contains($result['stdout'], '"status":"success"'))->count();
$errors = collect($results)->filter(fn (array $result): bool => str_contains($result['stdout'], '"status":"error"'))->count();
$summary = [
    'database' => $database,
    'scenario' => $scenario,
    'workers' => $results,
    'worker_count' => $workerCount,
    'successes' => $successes,
    'errors' => $errors,
    'expected' => $expected,
    'fixture_shop_id' => $shop->id,
];

if ($scenario === 'debt_payment') {
    $summary['entries'] = CustomerAccountEntry::query()->where('shop_id', $shop->id)->count();
    $summary['payments'] = $record->payments()->count();
    $summary['balance'] = (string) $record->customer->fresh()->balance;
} elseif ($scenario === 'cash_movement') {
    $summary['movements'] = CashMovement::query()->where('shop_id', $shop->id)->count();
    $summary['amount_cents'] = (int) CashMovement::query()->where('shop_id', $shop->id)->sum('amount_cents');
} elseif ($scenario === 'purchase_receive') {
    $item = $record->items()->firstOrFail();
    $summary['movement_count'] = InventoryMovement::query()->where('product_id', $item->product_id)->where('type', 'restock')->count();
    $summary['stock'] = (int) $item->product->fresh()->inventory->stock_quantity;
    $summary['status'] = $record->fresh()->status;
} elseif ($scenario === 'daily_close') {
    $summary['closures'] = DailyClosure::query()->where('shop_id', $shop->id)->count();
} else {
    $summary['products'] = Product::query()->where('shop_id', $shop->id)->count();
    $summary['status'] = $record->fresh()->status;
    $summary['created'] = (int) (($record->fresh()->summary['created'] ?? 0));
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;

$passed = $scenario === 'purchase_receive'
    ? $successes === 1 && $errors === ($workerCount - 1)
    : $successes === $workerCount && $errors === 0;
if ($scenario === 'debt_payment') {
    $passed = $passed && $summary['entries'] === 1 && $summary['payments'] === 1 && $summary['balance'] === '50.00';
} elseif ($scenario === 'cash_movement') {
    $passed = $passed && $summary['movements'] === 1 && $summary['amount_cents'] === 5000;
} elseif ($scenario === 'purchase_receive') {
    $passed = $passed && $summary['movement_count'] === 1 && $summary['stock'] === 2 && $summary['status'] === 'received';
} elseif ($scenario === 'daily_close') {
    $passed = $passed && $summary['closures'] === 1;
} else {
    $passed = $passed && $summary['products'] === 1 && $summary['status'] === 'confirmed' && $summary['created'] === 1;
}

if (! $passed) {
    exit(1);
}
