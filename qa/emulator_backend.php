<?php

// Deliberately isolated QA fixture. Never uses the configured production connection.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$action = $argv[1] ?? 'identity';
$freshLive = ($argv[2] ?? '') === 'fresh-live';
$snapshot = $freshLive ? 'C:/Users/waila/AppData/Local/Temp/opencode/micatalogo-emulator-complete-20261011.sqlite'
    : 'C:/Users/waila/AppData/Local/Temp/opencode/micatalogo-emulator-20261010.sqlite';
$db = Illuminate\Support\Facades\DB::connection();
$expected = $action === 'fixture' ? ':memory:' : $snapshot;
if (! $app->environment('testing') || $db->getDriverName() !== 'sqlite'
    || str_replace('\\', '/', $db->getDatabaseName()) !== $expected) {
    throw new RuntimeException('QA requires testing + the explicitly isolated SQLite database.');
}
echo json_encode(['environment' => $app->environment(), 'connection' => $db->getName(),
    'driver' => $db->getDriverName(), 'host' => 'none (local SQLite)', 'database' => $db->getDatabaseName()]).PHP_EOL;

if ($action === 'fixture') {
    if (is_file($snapshot)) {
        throw new RuntimeException('The QA snapshot already exists; it will not be overwritten.');
    }
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    $owner = App\Models\User::factory()->create(['name' => 'Emulador QA', 'email' => 'emulador-qa@example.test',
        'password' => Illuminate\Support\Facades\Hash::make('Emulador-QA-2026!'), 'plan' => 'pro']);
    foreach (['A', 'B'] as $suffix) {
        $shop = App\Models\Shop::factory()->for($owner)->create(['name' => "QA Tienda $suffix", 'slug' => "qa-emulador-$suffix",
            'business_type' => $suffix === 'A' ? 'perfume_store' : 'clothing', 'whatsapp_country_code' => '1', 'whatsapp_number' => '8095550100']);
        $category = $shop->categories()->create(['name' => "QA Categoría $suffix", 'slug' => "qa-$suffix"]);
        $product = App\Models\Product::factory()->for($shop)->create(['name' => "QA Producto $suffix", 'slug' => "qa-producto-$suffix",
            'shop_category_id' => $category->id, 'product_code' => "QA-$suffix", 'price' => '300.00', 'sale_unit' => 'unit',
            'moderation_status' => App\Enums\ProductModerationStatus::Active]);
        App\Models\ProductInventory::create(['product_id' => $product->id, 'track_inventory' => true,
            'stock_quantity' => 20, 'sold_quantity' => 0, 'cost_price' => '120.00']);
        foreach ([true, false] as $active) {
            App\Models\Customer::create(['shop_id' => $shop->id, 'name' => "QA Cliente $suffix ".($active ? 'Activo' : 'Inactivo'),
                'first_name' => 'QA', 'last_name' => "Cliente $suffix ".($active ? 'Activo' : 'Inactivo'),
                'phone' => '8095550101', 'address' => "Calle QA $suffix", 'document_type' => 'cedula',
                'document_number' => "$suffix-".($active ? '001' : '002'), 'credit_limit' => '10000.00', 'is_active' => $active]);
        }
        $orderSuffix = $freshLive && $suffix === 'A' ? 'A2' : $suffix;
        $order = App\Models\Order::create(['shop_id' => $shop->id, 'order_number' => "QA-PEDIDO-$orderSuffix",
            'customer_name' => "QA Comprador $orderSuffix", 'customer_phone' => '8095550102', 'delivery_type' => 'pickup',
            'currency' => 'DOP', 'subtotal' => '600.00', 'total' => '600.00', 'status' => 'sent_to_whatsapp', 'notes' => 'Sólo datos QA locales']);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name,
            'product_code' => $product->product_code, 'quantity' => 2, 'unit_price' => '300.00', 'line_total' => '600.00']);
    }
    $db->getPdo()->exec("VACUUM INTO '".$snapshot."'");
    echo "Created isolated SQLite snapshot: $snapshot".PHP_EOL;
} elseif ($action === 'append-order') {
    $shop = App\Models\Shop::where('slug', 'qa-emulador-A')->sole();
    if ($shop->user->email !== 'emulador-qa@example.test') throw new RuntimeException('Not a QA owner');
    $product = $shop->products()->where('product_code', 'QA-A')->sole();
    $order = $shop->orders()->firstOrCreate(['order_number' => 'QA-PEDIDO-A2'], ['customer_name' => 'QA Comprador A2',
        'customer_phone' => '8095550102', 'delivery_type' => 'pickup', 'currency' => 'DOP',
        'subtotal' => '600.00', 'total' => '600.00', 'status' => 'sent_to_whatsapp']);
    if ($order->wasRecentlyCreated) $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name,
        'product_code' => $product->product_code, 'quantity' => 2, 'unit_price' => '300.00', 'line_total' => '600.00']);
    echo 'Appended QA order; all previous records preserved.'.PHP_EOL;
} elseif ($action === 'inspect') {
    foreach (App\Models\Shop::where('slug', 'like', 'qa-emulador-%')->get() as $shop) {
        echo json_encode(['shop' => $shop->name, 'public_id' => $shop->public_id,
            'customers' => $shop->customers()->get(['public_id', 'name', 'is_active', 'balance'])->toArray(),
            'products' => $shop->products()->with('inventory')->get()->map(fn ($p) => ['name' => $p->name,
                'stock' => $p->inventory?->stock_quantity, 'sold' => $p->inventory?->sold_quantity])->toArray(),
            'invoices' => App\Models\Invoice::where('shop_id', $shop->id)->get(['invoice_number', 'status', 'total'])->toArray(),
            'payments' => App\Models\InvoicePayment::where('shop_id', $shop->id)->get([
                'id', 'amount', 'payment_method', 'client_operation_uuid'])->toArray(),
            'cash_movements' => App\Models\CashMovement::where('shop_id', $shop->id)
                ->get(['type', 'amount', 'notes'])->toArray(),
            'cash_sessions' => App\Models\CashRegisterSession::where('shop_id', $shop->id)->get([
                'status', 'opening_amount_cents', 'expected_closing_amount_cents', 'counted_closing_amount_cents', 'difference_cents'])->toArray(),
            'orders' => $shop->orders()->get(['order_number', 'status'])->toArray()], JSON_UNESCAPED_UNICODE).PHP_EOL;
    }
}
