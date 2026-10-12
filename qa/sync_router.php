<?php

// Used only by `php -S 127.0.0.1:18767 -t public qa/sync_router.php`.
// QA routes are deliberately outside routes/api.php and cannot be deployed as public application routes.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (! str_starts_with($path, '/__qa/')) {
    $file = __DIR__.'/../public'.$path;
    if ($path !== '/' && is_file($file)) return false;
    require __DIR__.'/../public/index.php';
    return;
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
$expected = 'C:/Users/waila/AppData/Local/Temp/opencode/micatalogo-sync-20261012.sqlite';
if (! $app->environment('testing') || $db->getDriverName() !== 'sqlite' || str_replace('\\', '/', $db->getDatabaseName()) !== $expected
    || ! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403); exit('QA is restricted to the verified local SQLite server.');
}
header('Content-Type: application/json');
if ($path === '/__qa/identity') {
    echo json_encode(['environment' => $app->environment(), 'connection' => $db->getName(), 'driver' => $db->getDriverName(),
        'host' => 'none (local SQLite)', 'database' => $expected]);
    return;
}
if ($path !== '/__qa/scenario' || $_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(404); return; }
$input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
$scenario = $input['scenario'] ?? '';
$allowed = ['loginSharedCustomerCreditOrderCollectionAndShopSwitch', 'ownerSummaryReadsServerForEachShop',
    'ordersEntryAndReturnDoNotShowTheEmptyLandingForExistingOrders', 'compactCustomersOpenMovementsAndExplainTheirDebt',
    'offlineQueuedSalesSurviveDatabaseRestartAndSyncExactlyOnce', 'offlineQueuedDecantConsumesSourceMillilitersAndSyncsExactlyOnce',
    'anonymousRowEightWorkbookTravelsFromAndroidToRealLaravelAndConfirmsBySession', 'storeOperationsReadBackReplayAndTenantIsolation'];
if (! in_array($scenario, $allowed, true)) { http_response_code(422); echo json_encode(['error' => 'Unknown isolated scenario']); return; }

$result = Illuminate\Support\Facades\DB::transaction(function () use ($app, $scenario) {
    $nonce = strtolower((string) Illuminate\Support\Str::ulid());
    $password = 'Sync-QA-2026!';
    $owner = App\Models\User::factory()->create(['name' => 'Sync QA', 'email' => "sync-qa-$nonce@example.test",
        'password' => Illuminate\Support\Facades\Hash::make($password), 'plan' => App\Enums\UserPlan::Premium]);
    $token = $owner->createToken('isolated-sync-qa', ['*'])->plainTextToken;
    $shops = []; $products = []; $customers = []; $orders = [];
    foreach (['A', 'B'] as $suffix) {
        $shop = App\Models\Shop::factory()->for($owner)->create(['name' => "QA Tienda $suffix", 'slug' => "sync-qa-$nonce-".strtolower($suffix),
            'business_type' => $suffix === 'A' ? 'perfume_store' : 'clothing', 'whatsapp_country_code' => '1', 'whatsapp_number' => '8095550100']);
        $category = $shop->categories()->create(['name' => "QA Categoría $suffix", 'slug' => 'qa-'.strtolower($suffix)]);
        $product = App\Models\Product::factory()->for($shop)->create(['name' => "QA Producto $suffix", 'slug' => 'qa-producto-'.strtolower($suffix),
            'shop_category_id' => $category->id, 'product_code' => "QA-$suffix", 'price' => '300.00', 'sale_unit' => 'unit',
            'moderation_status' => App\Enums\ProductModerationStatus::Active]);
        App\Models\ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true, 'stock_quantity' => 20, 'sold_quantity' => 0, 'cost_price' => '120.00']);
        foreach ([true, false] as $active) {
            $customer = App\Models\Customer::create(['shop_id' => $shop->id, 'name' => "QA Cliente $suffix ".($active ? 'Activo' : 'Inactivo'),
                'first_name' => 'QA', 'last_name' => "Cliente $suffix ".($active ? 'Activo' : 'Inactivo'), 'phone' => '8095550101', 'address' => "Calle QA $suffix",
                'document_type' => 'cedula', 'document_number' => "$suffix-".($active ? '001' : '002'), 'credit_limit' => '10000.00', 'is_active' => $active]);
            if ($active) $customers[$suffix] = $customer;
        }
        $label = $suffix === 'A' ? 'A2' : 'B';
        $order = App\Models\Order::create(['shop_id' => $shop->id, 'order_number' => "QA-PEDIDO-$label-$nonce", 'customer_name' => "QA Comprador $label",
            'customer_phone' => '8095550102', 'delivery_type' => 'pickup', 'currency' => 'DOP', 'subtotal' => '600.00', 'total' => '600.00', 'status' => 'sent_to_whatsapp']);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'product_code' => $product->product_code,
            'quantity' => 2, 'unit_price' => '300.00', 'line_total' => '600.00']);
        $shops[$suffix] = $shop; $products[$suffix] = $product; $orders[$suffix] = $order;
    }
    $result = ['email' => $owner->email, 'password' => $password, 'token' => $token, 'shop_a' => $shops['A']->public_id,
        'shop_b' => $shops['B']->public_id, 'product' => $products['A']->public_id, 'scenario' => $scenario,
        'order_b_number' => $orders['B']->order_number];
    $call = function (string $controller, string $method, array $body, array $parameters) use ($app, $owner) {
        $request = Illuminate\Http\Request::create('/__qa/fixture-operation', 'POST', $body);
        $request->headers->set('Accept', 'application/json'); $request->setUserResolver(fn () => $owner);
        $response = $app->call([$app->make($controller), $method], ['request' => $request] + $parameters);
        if ($response->getStatusCode() >= 400) throw new RuntimeException('QA fixture rejected: '.$response->getContent());
        return $response->getData(true);
    };
    if (in_array($scenario, ['ownerSummaryReadsServerForEachShop', 'ordersEntryAndReturnDoNotShowTheEmptyLandingForExistingOrders'], true)) {
        $call(App\Http\Controllers\Api\V1\OrderController::class, 'confirm', ['payment_kind' => 'cash', 'payment_method' => 'cash'], ['shop' => $shops['A'], 'order' => $orders['A']]);
    }
    if ($scenario === 'compactCustomersOpenMovementsAndExplainTheirDebt') {
        $confirmed = $call(App\Http\Controllers\Api\V1\OrderController::class, 'confirm', ['payment_kind' => 'credit', 'payment_method' => 'cash',
            'customer_id' => $customers['B']->public_id, 'credit_amount' => '600.00'], ['shop' => $shops['B'], 'order' => $orders['B']]);
        $result['invoice_number'] = $confirmed['invoice_number'];
        foreach ([1, 2] as $ignored) $call(App\Http\Controllers\Api\V1\CustomerController::class, 'payment',
            ['client_transaction_uuid' => (string) Illuminate\Support\Str::uuid(), 'amount' => '200.00', 'payment_method' => 'bank_transfer'], ['shop' => $shops['B'], 'customer' => $customers['B']]);
    }
    if ($scenario === 'offlineQueuedSalesSurviveDatabaseRestartAndSyncExactlyOnce') {
        $products['A']->update(['price' => '2500.50']); $products['A']->inventory->update(['stock_quantity' => 3, 'cost_price' => '1500.00']);
    }
    if ($scenario === 'offlineQueuedDecantConsumesSourceMillilitersAndSyncsExactlyOnce') {
        $source = $products['A']; $source->update(['sale_unit' => 'bottle', 'volume_ml' => 100, 'price' => '1200.00']);
        $source->inventory->update(['stock_quantity' => 1, 'available_ml' => 100, 'opened_bottles' => 1, 'cost_price' => '300.00']);
        $decant = App\Models\Product::factory()->for($shops['A'])->create(['name' => 'QA Decant 10 ml', 'price' => '50.00', 'sale_unit' => 'decant',
            'volume_ml' => 10, 'inventory_source_product_id' => $source->id, 'moderation_status' => App\Enums\ProductModerationStatus::Active]);
        App\Models\ProductInventory::create(['product_id' => $decant->id, 'track_inventory' => true, 'stock_quantity' => 10, 'cost_price' => '30.00']);
        $result['source_product'] = $source->public_id; $result['decant_product'] = $decant->public_id;
    }
    $result['before'] = ['invoices' => App\Models\Invoice::whereIn('shop_id', array_map(fn ($s) => $s->id, $shops))->count(),
        'operations' => App\Models\MobileOperation::whereIn('shop_id', array_map(fn ($s) => $s->id, $shops))->count()];
    return $result;
});
// Synthetic fixtures are intentionally retained; no cleanup/reset endpoint exists.
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
