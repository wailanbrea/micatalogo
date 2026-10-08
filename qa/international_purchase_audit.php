<?php

declare(strict_types=1);

use App\Enums\UserPlan;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\User;
use App\Services\PurchaseDocumentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.mysql.database');
if (! str_starts_with($database, 'micatalogo_qa_')) {
    throw new RuntimeException('International purchase audit requires a database prefixed micatalogo_qa_.');
}

$user = User::factory()->create([
    'email' => 'qa-international-'.Str::lower(Str::random(12)).'@micatalogo.test',
    'plan' => UserPlan::Pro,
]);
$shop = Shop::factory()->create([
    'user_id' => $user->id,
    'slug' => 'qa-international-'.Str::lower(Str::random(12)),
]);
$product = Product::factory()->create([
    'shop_id' => $shop->id,
    'name' => 'QA international product',
    'price' => '1500.00',
]);
ProductInventory::query()->create([
    'product_id' => $product->id,
    'track_inventory' => true,
    'stock_quantity' => 0,
    'cost_price' => '0.00',
    'sold_quantity' => 0,
    'low_stock_threshold' => 0,
]);

$purchases = app(PurchaseDocumentService::class);
$load = $purchases->create($shop, $user, [
    'type' => 'load',
    'document_number' => 'QA-LOAD-'.Str::upper(Str::random(8)),
    'currency' => 'USD',
    'exchange_rate' => '60.00',
    'freight_amount' => '120.00',
    'customs_amount' => '80.00',
    'mode' => 'draft',
]);
$container = $purchases->create($shop, $user, [
    'type' => 'container',
    'document_number' => 'QA-CONTAINER-'.Str::upper(Str::random(8)),
    'currency' => 'USD',
    'exchange_rate' => '60.00',
    'freight_amount' => '120.00',
    'customs_amount' => '80.00',
    'parent_document_id' => $load->public_id,
    'mode' => 'received',
    'items' => [[
        'product_id' => $product->public_id,
        'quantity' => 10,
        'unit_cost' => '10.00',
    ]],
]);

$lot = InventoryLot::query()->where('product_id', $product->id)->latest('id')->firstOrFail();
$output = [
    'database' => $database,
    'currency' => $container->currency,
    'exchange_rate' => (string) $container->exchange_rate,
    'freight_amount' => (string) $container->freight_amount,
    'customs_amount' => (string) $container->customs_amount,
    'document_subtotal' => (string) $container->subtotal,
    'document_total' => (string) $container->total,
    'entered_unit_cost' => (string) $container->items->sole()->unit_cost,
    'stored_lot_unit_cost_cents' => $lot->received_unit_cost_cents,
    'stored_stock' => (int) $product->fresh()->inventory->stock_quantity,
    'finding' => 'Logística y moneda se conservan como campos informativos; no se convierten ni se prorratean al lote.',
    'fixture_shop_id' => $shop->id,
    'fixture_document_id' => $container->id,
];

echo json_encode($output, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
