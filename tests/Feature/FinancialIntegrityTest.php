<?php

use App\Enums\UserPlan;
use App\Models\CashMovement;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;
use App\Services\BusinessDashboardService;
use App\Services\CashRegisterService;
use App\Services\CustomerAccountService;
use App\Services\ExpenseService;
use App\Services\InventoryService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function setupFinancialShop(): array
{
    $owner = User::factory()->create(['plan' => UserPlan::Pro]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $seller = User::factory()->create(['plan' => UserPlan::Free]);
    ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $seller->id,
        'commission_type' => 'percentage',
        'commission_value' => 0,
        'is_active' => true,
    ]);

    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'price' => 1000.00,
        'sale_unit' => 'unit',
    ]);

    ProductInventory::create([
        'product_id' => $product->id,
        'track_inventory' => true,
        'stock_quantity' => 100,
        'cost_price' => 400.00,
        'sold_quantity' => 0,
        'low_stock_threshold' => 5,
    ]);

    return [$owner, $shop, $product, $seller];
}

// 57. Impuestos separados: venta con ITBIS no infla ventas netas ni ganancia
test('57. tax collected is excluded from net sales and gross profit in P&L', function () {
    [$owner, $shop, $product] = setupFinancialShop();
    $dashboardService = app(BusinessDashboardService::class);

    // Sale: Price 1000, Tax 180 (ITBIS 18%), Cost 400
    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'invoice_number' => 'INV-TAX-01',
        'subtotal' => 1000.00,
        'discount' => 0.00,
        'tax' => 0.00,
        'total' => 1180.00,
        'status' => 'paid',
        'issued_at' => now(),
    ]);

    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => 1000.00,
        'discount' => 0.00,
        'tax' => 180.00,
        'line_total' => 1180.00,
        'unit_cost_cents' => 40000,
        'total_cost_cents' => 40000,
        'is_cost_estimated' => false,
    ]);

    InvoicePayment::create([
        'public_id' => (string) Str::ulid(),
        'shop_id' => $shop->id,
        'invoice_id' => $invoice->id,
        'user_id' => $owner->id,
        'payment_method' => 'cash',
        'amount' => 1180.00,
        'amount_cents' => 118000,
        'received_at' => now(),
    ]);

    $summary = $dashboardService->getSummary($shop, now()->startOfMonth()->toDateString(), now()->toDateString());

    // Gross sales = 1000 (subtotal without tax), Tax = 180
    // Net sales = 1000 (must NOT be 1180)
    // FIFO COGS = 400
    // Gross profit = 1000 - 400 = 600 (must NOT be 780)
    expect($summary['income_statement']['gross_sales'])->toBe(1000.0)
        ->and($summary['income_statement']['tax_collected'])->toBe(180.0)
        ->and($summary['income_statement']['net_sales'])->toBe(1000.0)
        ->and($summary['income_statement']['fifo_cogs'])->toBe(400.0)
        ->and($summary['income_statement']['gross_profit'])->toBe(600.0);
});

// 58. Descuento general + descuento de línea: ambos se reflejan en ventas netas
test('58. line discount and general discount both reduce net sales correctly', function () {
    [$owner, $shop, $product] = setupFinancialShop();
    $dashboardService = app(BusinessDashboardService::class);

    // Unit price: 1000, Line discount: 100 => Subtotal after line discount: 900
    // General invoice discount: 50 => Net sales should be: 850
    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'invoice_number' => 'INV-DISC-01',
        'subtotal' => 900.00,
        'discount' => 50.00,
        'tax' => 0.00,
        'total' => 850.00,
        'status' => 'paid',
        'issued_at' => now(),
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => 1000.00,
        'discount' => 100.00,
        'general_discount_cents' => 5000,
        'tax' => 0.00,
        'line_total' => 850.00,
        'unit_cost_cents' => 40000,
        'total_cost_cents' => 40000,
        'is_cost_estimated' => false,
    ]);

    $summary = $dashboardService->getSummary($shop, now()->startOfMonth()->toDateString(), now()->toDateString());

    expect($summary['income_statement']['gross_sales'])->toBe(1000.0)
        ->and($summary['income_statement']['discounts'])->toBe(150.0)
        ->and($summary['income_statement']['net_sales'])->toBe(850.0)
        ->and($summary['income_statement']['gross_profit'])->toBe(450.0);
});

// 59. Devolución de venta: descuenta venta neta sin duplicar impuestos; si restock=1, devuelve costo FIFO
test('59. return deducts net sales and restocks fifo cost', function () {
    [$owner, $shop, $product] = setupFinancialShop();
    $dashboardService = app(BusinessDashboardService::class);

    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'invoice_number' => 'INV-RET-01',
        'subtotal' => 2000.00,
        'discount' => 0.00,
        'tax' => 0.00,
        'total' => 2000.00,
        'status' => 'paid',
        'issued_at' => now(),
    ]);

    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 2,
        'unit_price' => 1000.00,
        'discount' => 0.00,
        'tax' => 0.00,
        'line_total' => 2000.00,
        'unit_cost_cents' => 40000,
        'total_cost_cents' => 80000,
        'is_cost_estimated' => false,
    ]);

    $opId = DB::table('mobile_operations')->insertGetId([
        'shop_id' => $shop->id,
        'client_operation_uuid' => (string) Str::uuid(),
        'type' => 'return',
        'payload_sha256' => hash('sha256', 'return'),
        'result' => json_encode(['ok' => true]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $returnId = DB::table('invoice_returns')->insertGetId([
        'invoice_id' => $invoice->id,
        'mobile_operation_id' => $opId,
        'total' => 1000.00,
        'notes' => 'Customer return',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('invoice_return_items')->insert([
        'invoice_return_id' => $returnId,
        'invoice_item_id' => $item->id,
        'quantity' => 1,
        'refund' => 1000.00,
        'tax_refund' => 0.00,
        'restock' => true,
        'total_cost_cents' => 40000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $summary = $dashboardService->getSummary($shop, now()->startOfMonth()->toDateString(), now()->toDateString());

    // Gross: 2000, Returns: 1000 => Net sales: 1000
    // COGS: 800 - 400 = 400
    // Gross profit: 1000 - 400 = 600
    expect($summary['income_statement']['net_sales'])->toBe(1000.0)
        ->and($summary['income_statement']['returns'])->toBe(1000.0)
        ->and($summary['income_statement']['fifo_cogs'])->toBe(400.0)
        ->and($summary['income_statement']['gross_profit'])->toBe(600.0);
});

test('return totals stay exact when later credit collection allocates the remaining cents', function () {
    [$owner, $shop, $product] = setupFinancialShop();
    $customer = Customer::create([
        'shop_id' => $shop->id,
        'name' => 'Cliente retorno centavos',
        'credit_limit' => '100.00',
        'balance' => '10.00',
    ]);
    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'user_id' => $owner->id,
        'invoice_number' => 'INV-RETURN-CENTS',
        'subtotal' => '10.01',
        'discount' => '0.00',
        'tax' => '0.00',
        'total' => '10.01',
        'status' => 'pending',
        'issued_at' => now(),
    ]);
    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => '10.01',
        'discount' => '0.00',
        'tax' => '0.00',
        'line_total' => '10.01',
        'unit_cost_cents' => 40000,
        'total_cost_cents' => 40000,
        'is_cost_estimated' => false,
    ]);
    $operationId = DB::table('mobile_operations')->insertGetId([
        'shop_id' => $shop->id,
        'client_operation_uuid' => (string) Str::uuid(),
        'type' => 'return',
        'payload_sha256' => hash('sha256', 'fractional-return'),
        'result' => json_encode(['ok' => true]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $returnId = DB::table('invoice_returns')->insertGetId([
        'invoice_id' => $invoice->id,
        'mobile_operation_id' => $operationId,
        'total' => '0.01',
        'notes' => 'QA centavos',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('invoice_return_items')->insert([
        'invoice_return_id' => $returnId,
        'invoice_item_id' => $item->id,
        'quantity' => 1,
        'refund' => '0.01',
        'tax_refund' => '0.00',
        'total_cost_cents' => 40000,
        'restock' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $result = app(PaymentService::class)->recordCustomerDebtPayment(
        $shop,
        $customer,
        $owner,
        '10.00',
        'bank_transfer',
        (string) Str::uuid(),
        hash('sha256', 'fractional-collection'),
    );

    expect($result['allocations'][0]['allocated_cents'])->toBe(1000)
        ->and($result['allocations'][0]['remaining_invoice_balance'])->toBe('0.00')
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and((string) $customer->fresh()->balance)->toBe('0.00');
});

// 60. Venta a crédito: genera ganancia y CxC, pero NO genera flujo de efectivo hasta el cobro
test('60. credit sale increases receivable and profit without increasing cash flow', function () {
    [$owner, $shop, $product] = setupFinancialShop();
    $dashboardService = app(BusinessDashboardService::class);
    $customerAccountService = app(CustomerAccountService::class);

    $customer = Customer::create([
        'shop_id' => $shop->id,
        'name' => 'Cliente Crédito',
        'credit_limit' => 5000,
        'balance' => 0,
    ]);

    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-CREDIT-01',
        'subtotal' => 1000.00,
        'discount' => 0.00,
        'tax' => 0.00,
        'total' => 1000.00,
        'status' => 'pending',
        'due_date' => now()->addDays(15),
        'issued_at' => now(),
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => 1000.00,
        'discount' => 0.00,
        'tax' => 0.00,
        'line_total' => 1000.00,
        'unit_cost_cents' => 40000,
        'total_cost_cents' => 40000,
        'is_cost_estimated' => false,
    ]);

    $customerAccountService->recordInvoiceCharge($customer, $invoice, '1000.00', $owner->id);

    $summary = $dashboardService->getSummary($shop, now()->startOfMonth()->toDateString(), now()->toDateString());

    // Profit is recognized (1000 - 400 = 600)
    expect($summary['income_statement']['gross_profit'])->toBe(600.0)
        // CxC is recognized
        ->and($summary['current_state']['receivable_total'])->toBe(1000.0)
        // Cash flow is ZERO
        ->and($summary['cash_flow']['net_cash_flow'])->toBe(0.0);
});

// 61. Venta mixta: suma exacta en centavos; solo porción cash afecta caja
test('61. split payment sums exact cents and only cash affects cash register', function () {
    [$owner, $shop, $product] = setupFinancialShop();
    $cashService = app(CashRegisterService::class);
    $paymentService = app(PaymentService::class);

    $session = $cashService->openSession($shop, $owner, 500, 'Apertura');

    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'invoice_number' => 'INV-SPLIT-01',
        'subtotal' => 1000.00,
        'total' => 1000.00,
        'status' => 'paid',
        'issued_at' => now(),
    ]);

    // 400 cash + 600 card
    $paymentService->processInvoicePayments($shop, $invoice, $owner, [
        ['method' => 'cash', 'amount' => '400.00'],
        ['method' => 'card', 'amount' => '600.00'],
    ], 0, null);

    $session->refresh();
    // Only 400 cash should be added to cash register session
    expect((int) $session->movements()->where('type', 'sale')->sum('amount_cents'))->toBe(40000)
        ->and($session->calculateExpectedBalance())->toBe(90000); // 500 opening + 400 cash

    // Invoice has 2 payment rows summing 1000.00
    expect((int) InvoicePayment::where('invoice_id', $invoice->id)->sum('amount_cents'))->toBe(100000);
});

// 62. Prevención de crédito duplicado
test('62. duplicate credit is normalized and does not double count', function () {
    [$owner, $shop, $product] = setupFinancialShop();
    $paymentService = app(PaymentService::class);

    $customer = Customer::create([
        'shop_id' => $shop->id,
        'name' => 'Cliente Normalizado',
        'credit_limit' => 5000,
        'balance' => 0,
    ]);

    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-DUP-CREDIT',
        'subtotal' => 1000.00,
        'total' => 1000.00,
        'status' => 'pending',
        'issued_at' => now(),
    ]);

    // If client sends 300 cash + 700 in payments array with method credit, plus credit_amount 700
    // PaymentService must normalize it and only charge 700 to customer balance
    $paymentService->processInvoicePayments($shop, $invoice, $owner, [
        ['method' => 'cash', 'amount' => '300.00'],
        ['method' => 'credit', 'amount' => '700.00'],
    ], 700.00, $customer);

    expect((float) $customer->fresh()->balance)->toBe(700.00)
        ->and($invoice->fresh()->payment_status)->toBe('partial');
});

// 63. Factura pendiente sin cliente: rechazada con 422
test('63. orphan pending invoice without customer or debt is rejected with 422 in POS sale', function () {
    [$owner, $shop, $product] = setupFinancialShop();

    $token = $owner->createToken('test', ['pos:write'])->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/v1/shops/{$shop->public_id}/pos-sales", [
        'client_sale_uuid' => (string) Str::uuid(),
        'payment_status' => 'pending',
        'payment_method' => 'cash',
        'paid_amount' => '0.00',
        'credit_amount' => '0.00',
        'customer_id' => null, // No customer!
        'items' => [
            ['product_id' => $product->public_id, 'quantity' => 1, 'unit_price' => '1000.00'],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['customer_id']);
});

// 64. Gasto con pago parcial: P&L reconoce 100% incurrido, flujo solo la parte pagada
test('64. partial expense recognized fully in P&L and partially in cash flow', function () {
    [$owner, $shop, $product] = setupFinancialShop();
    $expenseService = app(ExpenseService::class);
    $dashboardService = app(BusinessDashboardService::class);

    // Expense of 1,000 with 400 paid now
    $expense = $expenseService->recordExpense($shop, $owner, [
        'category_name' => 'Mantenimiento',
        'description' => 'Reparación de aire',
        'amount' => '1000.00',
        'paid_amount' => '400.00',
        'payment_method' => 'cash',
        'occurred_at' => now(),
    ]);

    expect($expense->payment_status)->toBe('partial')
        ->and($expense->amount_paid_cents)->toBe(40000)
        ->and($expense->unpaidAmountCents())->toBe(60000);

    $summary = $dashboardService->getSummary($shop, now()->startOfMonth()->toDateString(), now()->toDateString());

    // P&L operating expenses = 1000
    expect($summary['income_statement']['operating_expenses_total'])->toBe(1000.0)
        // Cash flow expenses paid = 400
        ->and($summary['cash_flow']['outflows']['expenses_paid'])->toBe(400.0);
});

// 65. Gastos por categoría en P&L: la suma coincide exactamente con operating_expenses del período
test('65. sum of expenses by category matches operating expenses total strictly filtered by date', function () {
    [$owner, $shop, $product] = setupFinancialShop();
    $expenseService = app(ExpenseService::class);
    $dashboardService = app(BusinessDashboardService::class);

    // Expense in current month: 300
    $expenseService->recordExpense($shop, $owner, [
        'category_name' => 'Servicios',
        'description' => 'Agua',
        'amount' => '300.00',
        'payment_method' => 'cash',
        'occurred_at' => now(),
    ]);

    // Expense in current month: 700
    $expenseService->recordExpense($shop, $owner, [
        'category_name' => 'Publicidad',
        'description' => 'Redes sociales',
        'amount' => '700.00',
        'payment_method' => 'card',
        'occurred_at' => now(),
    ]);

    // Expense in previous month: 500 (must NOT be counted)
    $expenseService->recordExpense($shop, $owner, [
        'category_name' => 'Servicios',
        'description' => 'Agua mes pasado',
        'amount' => '500.00',
        'payment_method' => 'cash',
        'occurred_at' => now()->subMonths(2),
    ]);

    $summary = $dashboardService->getSummary($shop, now()->startOfMonth()->toDateString(), now()->toDateString());

    $sumCategories = collect($summary['income_statement']['expenses_by_category'])->sum('total');

    expect($summary['income_statement']['operating_expenses_total'])->toBe(1000.0)
        ->and($sumCategories)->toBe(1000.0);
});

// 66. Movimiento en caja ajena: rechazado con 403
test('66. cash movement on another sellers cash register session is forbidden with 403', function () {
    [$owner, $shop, $product, $seller] = setupFinancialShop();
    $otherSeller = User::factory()->create(['plan' => UserPlan::Free]);
    ShopSeller::create([
        'shop_id' => $shop->id,
        'user_id' => $otherSeller->id,
        'commission_type' => 'percentage',
        'commission_value' => 0,
        'is_active' => true,
    ]);

    $cashService = app(CashRegisterService::class);
    $session = $cashService->openSession($shop, $seller, 100, 'Turno Vendedor 1');

    // Other seller tries to add movement to seller's session
    $response = $this->actingAs($otherSeller, 'sanctum')->postJson("/api/v1/shops/{$shop->public_id}/cash-sessions/{$session->public_id}/movements", [
        'type' => 'cash_in',
        'amount' => '50.00',
        'reason' => 'Intento ilegítimo',
    ]);

    $response->assertStatus(403);
});

// 67. Apertura concurrente de caja: bloqueada
test('67. concurrent cash register opening by same user is blocked', function () {
    [$owner, $shop, $product] = setupFinancialShop();
    $cashService = app(CashRegisterService::class);

    $cashService->openSession($shop, $owner, 200, 'Primera apertura');

    // Trying to open another session without closing throws exception
    expect(fn () => $cashService->openSession($shop, $owner, 300, 'Segunda apertura'))
        ->toThrow(InvalidArgumentException::class);
});

// 68. Idempotencia de gastos con UUID: reintento devuelve mismo gasto sin duplicar
test('68. expense creation with client_operation_uuid is idempotent', function () {
    [$owner, $shop, $product] = setupFinancialShop();
    $uuid = (string) Str::uuid();

    $payload = [
        'category_name' => 'Limpieza',
        'description' => 'Materiales de aseo',
        'amount' => '450.00',
        'payment_method' => 'cash',
        'client_operation_uuid' => $uuid,
    ];

    $res1 = $this->actingAs($owner, 'sanctum')->postJson("/api/v1/shops/{$shop->public_id}/expenses", $payload);
    $res1->assertCreated();

    $res2 = $this->actingAs($owner, 'sanctum')->postJson("/api/v1/shops/{$shop->public_id}/expenses", $payload);
    $res2->assertCreated();

    expect(Expense::where('shop_id', $shop->id)->where('client_operation_uuid', $uuid)->count())->toBe(1);
});

// 69. Idempotencia con diferente payload: 409 Conflict
test('69. expense idempotency conflict with different payload returns 409', function () {
    [$owner, $shop, $product] = setupFinancialShop();
    $uuid = (string) Str::uuid();

    $payload1 = [
        'category_name' => 'Limpieza',
        'description' => 'Materiales de aseo',
        'amount' => '450.00',
        'payment_method' => 'cash',
        'client_operation_uuid' => $uuid,
    ];

    $this->actingAs($owner, 'sanctum')->postJson("/api/v1/shops/{$shop->public_id}/expenses", $payload1)->assertCreated();

    // Reusing UUID with different amount
    $payload2 = [
        'category_name' => 'Limpieza',
        'description' => 'Materiales de aseo',
        'amount' => '999.00', // Changed!
        'payment_method' => 'cash',
        'client_operation_uuid' => $uuid,
    ];

    $this->actingAs($owner, 'sanctum')->postJson("/api/v1/shops/{$shop->public_id}/expenses", $payload2)->assertStatus(409);
});

// 70. Aging por factura y due_date: clasificada en el tramo correcto
test('70. invoice aging classifies debt by due_date accurately', function () {
    [$owner, $shop, $product] = setupFinancialShop();
    $dashboardService = app(BusinessDashboardService::class);

    $customer = Customer::create([
        'shop_id' => $shop->id,
        'name' => 'Cliente Aging',
        'credit_limit' => 10000,
        'balance' => 3000,
    ]);

    // Invoice 1: 45 days overdue (tramo 31_60)
    Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-AGING-45',
        'subtotal' => 1000.00,
        'total' => 1000.00,
        'status' => 'pending',
        'due_date' => now()->subDays(45),
        'issued_at' => now()->subDays(60),
    ]);

    // Invoice 2: 10 days overdue (tramo 0_30)
    Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-AGING-10',
        'subtotal' => 2000.00,
        'total' => 2000.00,
        'status' => 'pending',
        'due_date' => now()->subDays(10),
        'issued_at' => now()->subDays(25),
    ]);

    $summary = $dashboardService->getSummary($shop, now()->startOfMonth()->toDateString(), now()->toDateString());

    expect($summary['current_state']['aging']['days_0_30'])->toBe(2000.0)
        ->and($summary['current_state']['aging']['days_31_60'])->toBe(1000.0)
        ->and($summary['current_state']['aging']['overdue_count'])->toBe(1);
});

// 71. Abono parcial a factura reduce el saldo envejecido solo por la diferencia impaga
test('71. partial payment to invoice reduces aged balance only by remaining debt', function () {
    [$owner, $shop, $product] = setupFinancialShop();
    $dashboardService = app(BusinessDashboardService::class);

    $customer = Customer::create([
        'shop_id' => $shop->id,
        'name' => 'Cliente Abono',
        'credit_limit' => 5000,
        'balance' => 600,
    ]);

    $invoice = Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-PARTIAL-ABONO',
        'subtotal' => 1000.00,
        'total' => 1000.00,
        'status' => 'partial',
        'due_date' => now()->subDays(35),
        'issued_at' => now()->subDays(50),
    ]);

    // Paid 400
    InvoicePayment::create([
        'public_id' => (string) Str::ulid(),
        'shop_id' => $shop->id,
        'invoice_id' => $invoice->id,
        'user_id' => $owner->id,
        'payment_method' => 'cash',
        'amount' => 400.00,
        'amount_cents' => 40000,
        'received_at' => now(),
    ]);

    $summary = $dashboardService->getSummary($shop, now()->startOfMonth()->toDateString(), now()->toDateString());

    // Remaining in 31_60 should be 600
    expect($summary['current_state']['aging']['days_31_60'])->toBe(600.0);
});

test('aging never exceeds the authoritative customer balance when open invoices disagree', function () {
    [$owner, $shop] = setupFinancialShop();
    $customer = Customer::create([
        'shop_id' => $shop->id,
        'name' => 'Cliente con saldo conciliado',
        'credit_limit' => 20000,
        'balance' => 6500,
    ]);

    Invoice::create([
        'shop_id' => $shop->id,
        'user_id' => $owner->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-AGING-RECONCILE',
        'subtotal' => 9500.00,
        'total' => 9500.00,
        'status' => 'pending',
        'due_date' => now()->subDays(10),
        'issued_at' => now()->subDays(10),
    ]);

    $summary = app(BusinessDashboardService::class)->getSummary(
        $shop,
        now()->startOfMonth()->toDateString(),
        now()->toDateString()
    );
    $aging = $summary['current_state']['aging'];

    expect($aging['total_receivable'])->toBe(6500.0)
        ->and($aging['days_0_30'] + $aging['days_31_60'] + $aging['days_61_90'] + $aging['days_over_90'])->toBe(6500.0)
        ->and($aging['invoices_total'])->toBe(6500.0)
        ->and($aging['reconciliation_difference'])->toBe(0.0);
});

// 72. Venta web individual / carrito crea InvoicePayment y movimiento de caja si hay sesión abierta
test('72. web sale creates InvoicePayment and cash movement when cash register is open', function () {
    [$owner, $shop, $product] = setupFinancialShop();
    $cashService = app(CashRegisterService::class);
    $inventoryService = app(InventoryService::class);

    $session = $cashService->openSession($shop, $owner, 300, 'Apertura Web');

    // Web single product sale
    $movement = $inventoryService->recordSale($product, 2, 'Venta mostrador web', $owner->id, true, 1000.00, 'cash');

    $invoice = Invoice::where('shop_id', $shop->id)->latest()->first();
    expect($invoice)->not->toBeNull();

    // Verify InvoicePayment was created
    $payment = InvoicePayment::where('invoice_id', $invoice->id)->first();
    expect($payment)->not->toBeNull()
        ->and($payment->amount_cents)->toBe(200000)
        ->and($payment->payment_method)->toBe('cash');

    // Verify CashMovement was created in active session
    $cashMov = CashMovement::where('cash_register_session_id', $session->id)->where('type', 'sale')->first();
    expect($cashMov)->not->toBeNull()
        ->and($cashMov->amount_cents)->toBe(200000);
});

// 73. Confirmación de pedido WhatsApp crea InvoicePayment y movimiento de caja si está pagado
test('73. whatsapp order confirmation creates InvoicePayment and cash movement', function () {
    [$owner, $shop, $product] = setupFinancialShop();
    $cashService = app(CashRegisterService::class);

    $session = $cashService->openSession($shop, $owner, 500, 'Apertura WhatsApp');

    $order = Order::create([
        'shop_id' => $shop->id,
        'order_number' => 'WA-ORDER-100',
        'currency' => 'DOP',
        'subtotal' => 1000.00,
        'total' => 1000.00,
        'status' => 'sent_to_whatsapp',
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => 1000.00,
        'line_total' => 1000.00,
    ]);

    $response = $this->actingAs($owner)->post(route('seller.shops.orders.confirm', [$shop, $order]));
    $response->assertRedirect();

    $invoice = Invoice::where('shop_id', $shop->id)->latest()->first();
    expect($invoice)->not->toBeNull();

    $payment = InvoicePayment::where('invoice_id', $invoice->id)->first();
    expect($payment)->not->toBeNull()
        ->and($payment->amount_cents)->toBe(100000);

    $cashMov = CashMovement::where('cash_register_session_id', $session->id)->where('type', 'sale')->first();
    expect($cashMov)->not->toBeNull()
        ->and($cashMov->amount_cents)->toBe(100000);
});
