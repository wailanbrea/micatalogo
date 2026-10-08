<?php

declare(strict_types=1);

use App\Enums\UserPlan;
use App\Models\CashMovement;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\OrderConfirmationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.mysql.database');
if (! str_starts_with($database, 'micatalogo_qa_')) {
    throw new RuntimeException('Order confirmation probe requires a database prefixed micatalogo_qa_.');
}

if (($argv[1] ?? null) === 'worker') {
    $shopId = (int) ($argv[2] ?? 0);
    $orderId = (int) ($argv[3] ?? 0);
    $userId = (int) ($argv[4] ?? 0);
    $barrier = (string) ($argv[5] ?? '');

    while (! is_file($barrier)) {
        usleep(10_000);
    }

    try {
        $invoiceNumber = app(OrderConfirmationService::class)->confirm(
            Shop::query()->findOrFail($shopId),
            Order::query()->findOrFail($orderId),
            User::query()->findOrFail($userId),
            ['payment_kind' => 'paid', 'payment_method' => 'cash']
        );

        echo json_encode(['status' => 'success', 'invoice_number' => $invoiceNumber], JSON_THROW_ON_ERROR).PHP_EOL;
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

$user = User::factory()->create([
    'email' => 'qa-order-concurrency-'.Str::lower(Str::random(12)).'@micatalogo.test',
    'plan' => UserPlan::Pro,
]);
$shop = Shop::factory()->create([
    'user_id' => $user->id,
    'slug' => 'qa-order-concurrency-'.Str::lower(Str::random(12)),
]);
$product = Product::factory()->create([
    'shop_id' => $shop->id,
    'name' => 'QA concurrent WhatsApp product',
    'price' => '300.00',
    'sale_unit' => 'unit',
]);
ProductInventory::query()->create([
    'product_id' => $product->id,
    'track_inventory' => true,
    'stock_quantity' => 1,
    'cost_price' => '100.00',
    'sold_quantity' => 0,
    'low_stock_threshold' => 0,
]);

$session = app(CashRegisterService::class)->openSession($shop, $user, '0.00', 'QA concurrent order confirmation');
$order = $shop->orders()->create([
    'order_number' => 'QA-ORDER-'.Str::upper(Str::random(10)),
    'currency' => 'DOP',
    'subtotal' => '300.00',
    'total' => '300.00',
    'status' => 'sent_to_whatsapp',
]);
$order->items()->create([
    'product_id' => $product->id,
    'product_name' => $product->name,
    'quantity' => 1,
    'unit_price' => '300.00',
    'line_total' => '300.00',
]);

$barrier = tempnam(sys_get_temp_dir(), 'micatalogo-qa-order-');
if ($barrier === false) {
    throw new RuntimeException('Could not create the QA order barrier.');
}
unlink($barrier);

$command = fn (): string => escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__)
    .' worker '.escapeshellarg((string) $shop->id)
    .' '.escapeshellarg((string) $order->id)
    .' '.escapeshellarg((string) $user->id)
    .' '.escapeshellarg($barrier);

$workers = [];
foreach ([1, 2] as $_) {
    $pipes = [];
    $process = proc_open($command(), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
    if (! is_resource($process)) {
        throw new RuntimeException('Could not start a concurrent order worker.');
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

$freshOrder = $order->fresh();
$inventory = $product->fresh()->inventory;
$invoiceCount = Invoice::query()->where('shop_id', $shop->id)->count();
$invoiceNumber = Invoice::query()->where('shop_id', $shop->id)->value('invoice_number');
$saleMovements = $product->inventoryMovements()->where('type', 'sale')->count();
$cashMovements = CashMovement::query()->where('cash_register_session_id', $session->id)->where('type', 'sale')->count();
$successes = collect($results)->filter(fn (array $result): bool => str_contains($result['stdout'], '"status":"success"'))->count();
$errors = collect($results)->filter(fn (array $result): bool => str_contains($result['stdout'], '"status":"error"'))->count();

$summary = [
    'database' => $database,
    'workers' => $results,
    'successes' => $successes,
    'errors' => $errors,
    'order_status' => $freshOrder?->status,
    'order_invoice_id' => $freshOrder?->invoice_id,
    'invoice_number' => $invoiceNumber,
    'remaining_stock' => (int) ($inventory?->stock_quantity ?? -1),
    'sale_movements' => $saleMovements,
    'cash_sale_movements' => $cashMovements,
    'expected_cash_cents' => $session->fresh()->calculateExpectedBalance(),
    'fixture_shop_id' => $shop->id,
    'fixture_order_id' => $order->id,
];
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;

if (
    $successes !== 2
    || $errors !== 0
    || $freshOrder?->status !== 'confirmed'
    || ! $freshOrder?->invoice_id
    || $invoiceCount !== 1
    || (int) ($inventory?->stock_quantity ?? -1) !== 0
    || $saleMovements !== 1
    || $cashMovements !== 1
    || (int) $session->fresh()->calculateExpectedBalance() !== 30000
    || collect($results)->pluck('stdout')->map(fn (string $output): ?string => json_decode($output, true)['invoice_number'] ?? null)->filter()->unique()->count() !== 1
) {
    exit(1);
}
