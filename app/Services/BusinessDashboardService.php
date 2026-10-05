<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\InventoryLot;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BusinessDashboardService
{
    /**
     * Compute full business summary for a shop and date range.
     */
    public function getSummary(Shop $shop, ?string $from = null, ?string $to = null, string $sort = 'profit', string $direction = 'desc'): array
    {
        $from = $from ?: now()->startOfMonth()->toDateString();
        $to = $to ?: now()->toDateString();

        $carbonFrom = Carbon::parse($from)->startOfDay();
        $carbonTo = Carbon::parse($to)->endOfDay();
        $daysDiff = max(1, $carbonFrom->diffInDays($carbonTo) + 1);

        // Previous period of equal duration
        $prevTo = $carbonFrom->copy()->subDay()->endOfDay();
        $prevFrom = $prevTo->copy()->subDays($daysDiff - 1)->startOfDay();

        $currentPeriod = $this->computePeriodMetrics($shop, $carbonFrom->toDateTimeString(), $carbonTo->toDateTimeString());
        $previousPeriod = $this->computePeriodMetrics($shop, $prevFrom->toDateTimeString(), $prevTo->toDateTimeString());

        $comparison = $this->computeComparison($currentPeriod, $previousPeriod);
        $currentState = $this->computeCurrentState($shop);
        $profitability = $this->computeProductProfitability($shop, $carbonFrom->toDateTimeString(), $carbonTo->toDateTimeString(), $sort, $direction);
        $incomeStatement = $this->computeIncomeStatement($shop, $currentPeriod);
        $cashFlow = $this->computeCashFlow($shop, $carbonFrom->toDateTimeString(), $carbonTo->toDateTimeString(), $currentPeriod);

        return [
            'from' => $from,
            'to' => $to,
            'period' => $currentPeriod,
            'previous_period' => $previousPeriod,
            'comparison' => $comparison,
            'current_state' => $currentState,
            'profitability' => $profitability,
            'income_statement' => $incomeStatement,
            'cash_flow' => $cashFlow,
            'requires_attention' => $this->computeRequiresAttention($shop, $currentState),
        ];
    }

    /**
     * Calculate sales, costs, profits and collections in a given time frame.
     */
    protected function computePeriodMetrics(Shop $shop, string $fromDatetime, string $toDatetime): array
    {
        // 1. Invoices base in period
        $invoiceStats = DB::table('invoices')
            ->where('shop_id', $shop->id)
            ->whereBetween('issued_at', [$fromDatetime, $toDatetime])
            ->where('status', '!=', 'void')
            ->selectRaw('
                COUNT(id) as sales_count,
                COALESCE(SUM(subtotal), 0) as subtotal,
                COALESCE(SUM(discount), 0) as discounts,
                COALESCE(SUM(tax), 0) as tax,
                COALESCE(SUM(total), 0) as total,
                COALESCE(SUM(commission_amount), 0) as commissions_generated
            ')
            ->first();

        // 2. Returns in period
        $returnsStats = DB::table('invoice_returns')
            ->join('invoices', 'invoices.id', '=', 'invoice_returns.invoice_id')
            ->where('invoices.shop_id', $shop->id)
            ->whereBetween('invoice_returns.created_at', [$fromDatetime, $toDatetime])
            ->selectRaw('
                COALESCE(SUM(invoice_returns.total), 0) as total_refund
            ')
            ->first();

        $grossSales = (float) $invoiceStats->total;
        $discounts = (float) $invoiceStats->discounts;
        $totalReturns = (float) ($returnsStats->total_refund ?? 0);
        $netSales = max(0.0, $grossSales - $totalReturns);

        // 3. FIFO Cost of items sold and returned
        $soldItemsStats = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.shop_id', $shop->id)
            ->whereBetween('invoices.issued_at', [$fromDatetime, $toDatetime])
            ->where('invoices.status', '!=', 'void')
            ->selectRaw('
                COALESCE(SUM(invoice_items.quantity), 0) as units_sold,
                COALESCE(SUM(invoice_items.total_cost_cents), 0) as cost_cents,
                COUNT(invoice_items.id) as total_lines,
                SUM(CASE WHEN invoice_items.total_cost_cents IS NOT NULL THEN 1 ELSE 0 END) as known_cost_lines,
                SUM(CASE WHEN invoice_items.total_cost_cents IS NOT NULL THEN invoice_items.line_total ELSE 0 END) as known_revenue
            ')
            ->first();

        $returnedItemsStats = DB::table('invoice_return_items')
            ->join('invoice_returns', 'invoice_returns.id', '=', 'invoice_return_items.invoice_return_id')
            ->join('invoices', 'invoices.id', '=', 'invoice_returns.invoice_id')
            ->where('invoices.shop_id', $shop->id)
            ->whereBetween('invoice_returns.created_at', [$fromDatetime, $toDatetime])
            ->selectRaw('
                COALESCE(SUM(invoice_return_items.quantity), 0) as units_returned,
                COALESCE(SUM(CASE WHEN invoice_return_items.restock = 1 THEN invoice_return_items.total_cost_cents ELSE 0 END), 0) as restocked_cost_cents
            ')
            ->first();

        $unitsSold = max(0, (int) $soldItemsStats->units_sold - (int) ($returnedItemsStats->units_returned ?? 0));
        $netCostCents = max(0, (int) $soldItemsStats->cost_cents - (int) ($returnedItemsStats->restocked_cost_cents ?? 0));
        $fifoCogs = $netCostCents / 100.0;

        // Cost coverage
        $totalLines = (int) $soldItemsStats->total_lines;
        $knownCostLines = (int) $soldItemsStats->known_cost_lines;
        $coveragePercent = $totalLines > 0 ? round(($knownCostLines / $totalLines) * 100, 1) : 100.0;
        $isCoveragePartial = $coveragePercent < 99.9;

        // Profit & Margin
        $grossProfit = $netSales - $fifoCogs;
        $grossMarginPercent = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 1) : 0.0;

        // 4. Operating Expenses in period
        $expensesStats = DB::table('expenses')
            ->where('shop_id', $shop->id)
            ->whereBetween('occurred_at', [$fromDatetime, $toDatetime])
            ->where('payment_status', 'paid')
            ->selectRaw('COALESCE(SUM(amount_cents), 0) as expenses_cents')
            ->first();

        $operatingExpenses = ((int) ($expensesStats->expenses_cents ?? 0)) / 100.0;
        $commissionsGenerated = (float) $invoiceStats->commissions_generated;

        $operatingProfit = $grossProfit - $operatingExpenses - $commissionsGenerated;
        $operatingMarginPercent = $netSales > 0 ? round(($operatingProfit / $netSales) * 100, 1) : 0.0;

        // 5. Collections and Credit in period
        // Cash collected from invoice payments in this period
        $collectedFromInvoicesCents = (int) DB::table('invoice_payments')
            ->where('shop_id', $shop->id)
            ->whereBetween('received_at', [$fromDatetime, $toDatetime])
            ->sum('amount_cents');

        // Credit charges generated in this period
        $creditGenerated = (float) DB::table('customer_account_entries')
            ->where('shop_id', $shop->id)
            ->whereBetween('created_at', [$fromDatetime, $toDatetime])
            ->where('type', 'charge')
            ->whereNotNull('invoice_id')
            ->sum('amount');

        // Customer payments collected towards past credit
        $collectedFromCredit = (float) abs(DB::table('customer_account_entries')
            ->where('shop_id', $shop->id)
            ->whereBetween('created_at', [$fromDatetime, $toDatetime])
            ->where('type', 'payment')
            ->sum('amount'));

        $totalCollectedInPeriod = ($collectedFromInvoicesCents / 100.0) + $collectedFromCredit;

        $salesCount = (int) $invoiceStats->sales_count;
        $averageTicket = $salesCount > 0 ? round($netSales / $salesCount, 2) : 0.0;

        return [
            'gross_sales' => $grossSales,
            'discounts' => $discounts,
            'returns' => $totalReturns,
            'net_sales' => $netSales,
            'fifo_cogs' => $fifoCogs,
            'units_sold' => $unitsSold,
            'cost_coverage_percent' => $coveragePercent,
            'is_cost_coverage_partial' => $isCoveragePartial,
            'gross_profit' => $grossProfit,
            'gross_margin_percent' => $grossMarginPercent,
            'operating_expenses' => $operatingExpenses,
            'commissions_generated' => $commissionsGenerated,
            'operating_profit' => $operatingProfit,
            'operating_margin_percent' => $operatingMarginPercent,
            'sales_count' => $salesCount,
            'average_ticket' => $averageTicket,
            'collected_in_period' => $totalCollectedInPeriod,
            'credit_generated' => $creditGenerated,
        ];
    }

    /**
     * Compute comparative percentage variations between current and previous period.
     */
    protected function computeComparison(array $current, array $previous): array
    {
        return [
            'net_sales_delta' => $this->percentageDelta($current['net_sales'], $previous['net_sales']),
            'gross_profit_delta' => $this->percentageDelta($current['gross_profit'], $previous['gross_profit']),
            'average_ticket_delta' => $this->percentageDelta($current['average_ticket'], $previous['average_ticket']),
            'sales_count_delta' => $this->percentageDelta($current['sales_count'], $previous['sales_count']),
            'margin_delta' => round($current['gross_margin_percent'] - $previous['gross_margin_percent'], 1),
        ];
    }

    protected function percentageDelta(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Calculate current live state: inventory value at cost, aging, receivables, alerts.
     */
    protected function computeCurrentState(Shop $shop): array
    {
        // 1. Receivables & Aging
        $customers = $shop->customers()->where('balance', '>', 0)->get();
        $totalReceivable = (float) $customers->sum('balance');

        $aging = [
            'days_0_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'days_over_90' => 0.0,
            'overdue_count' => 0,
        ];

        $now = now();
        foreach ($customers as $customer) {
            $oldestCharge = DB::table('customer_account_entries')
                ->where('customer_id', $customer->id)
                ->where('type', 'charge')
                ->orderBy('created_at', 'asc')
                ->first();

            $days = $oldestCharge ? $now->diffInDays(Carbon::parse($oldestCharge->created_at)) : 0;
            $bal = (float) $customer->balance;

            if ($days <= 30) {
                $aging['days_0_30'] += $bal;
            } elseif ($days <= 60) {
                $aging['days_31_60'] += $bal;
                $aging['overdue_count']++;
            } elseif ($days <= 90) {
                $aging['days_61_90'] += $bal;
                $aging['overdue_count']++;
            } else {
                $aging['days_over_90'] += $bal;
                $aging['overdue_count']++;
            }
        }

        // 2. Inventory Value at COST (FIFO remaining cost)
        $inventoryCostCents = (int) DB::table('inventory_lots')
            ->join('products', 'products.id', '=', 'inventory_lots.product_id')
            ->where('products.shop_id', $shop->id)
            ->where('inventory_lots.remaining_quantity', '>', 0)
            ->whereNotNull('inventory_lots.remaining_cost_cents')
            ->sum('inventory_lots.remaining_cost_cents');

        // Fallback for products without lots but with stock & cost_price
        $directInventoryCost = (float) DB::table('product_inventories')
            ->join('products', 'products.id', '=', 'product_inventories.product_id')
            ->leftJoin('inventory_lots', 'inventory_lots.product_id', '=', 'products.id')
            ->where('products.shop_id', $shop->id)
            ->whereNull('inventory_lots.id')
            ->where('product_inventories.track_inventory', 1)
            ->where('product_inventories.stock_quantity', '>', 0)
            ->whereNotNull('product_inventories.cost_price')
            ->selectRaw('SUM(product_inventories.stock_quantity * product_inventories.cost_price) as cost_sum')
            ->value('cost_sum');

        $totalInventoryCostValue = ($inventoryCostCents / 100.0) + ($directInventoryCost ?: 0.0);

        // 3. Stock counts
        $stockStats = DB::table('product_inventories')
            ->join('products', 'products.id', '=', 'product_inventories.product_id')
            ->where('products.shop_id', $shop->id)
            ->whereNull('products.deleted_at')
            ->where('product_inventories.track_inventory', 1)
            ->selectRaw('
                COUNT(products.id) as total_controlled,
                SUM(CASE WHEN product_inventories.stock_quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN product_inventories.stock_quantity > 0 AND product_inventories.stock_quantity <= product_inventories.low_stock_threshold THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN product_inventories.cost_price IS NULL THEN 1 ELSE 0 END) as missing_cost
            ')
            ->first();

        $activeProductsCount = $shop->products()->count();
        $pendingOrdersCount = $shop->orders()->whereNull('invoice_id')->count();
        $pendingPriceRulesCount = DB::table('product_price_rules')
            ->whereIn('product_id', $shop->products()->select('id'))
            ->whereNotNull('pending_price')
            ->count();

        // Active cash session
        $openCashSession = $shop->currentCashSession();

        return [
            'receivable_total' => $totalReceivable,
            'aging' => $aging,
            'inventory_cost_value' => $totalInventoryCostValue,
            'active_products_count' => $activeProductsCount,
            'low_stock_count' => (int) ($stockStats->low_stock ?? 0),
            'out_of_stock_count' => (int) ($stockStats->out_of_stock ?? 0),
            'missing_cost_count' => (int) ($stockStats->missing_cost ?? 0),
            'pending_orders_count' => $pendingOrdersCount,
            'pending_price_rules_count' => $pendingPriceRulesCount,
            'has_open_cash_session' => $openCashSession !== null,
            'open_cash_session' => $openCashSession,
        ];
    }

    /**
     * Compute actionable items requiring attention.
     */
    protected function computeRequiresAttention(Shop $shop, array $currentState): array
    {
        $items = [];

        if ($currentState['pending_orders_count'] > 0) {
            $items[] = [
                'type' => 'orders',
                'count' => $currentState['pending_orders_count'],
                'label' => "{$currentState['pending_orders_count']} pedidos pendientes de confirmar",
                'action_label' => 'Revisar',
                'url' => route('seller.shops.orders.confirm.list', $shop, false) ?: '#orders',
                'severity' => 'warning',
            ];
        }

        if ($currentState['low_stock_count'] > 0) {
            $items[] = [
                'type' => 'low_stock',
                'count' => $currentState['low_stock_count'],
                'label' => "{$currentState['low_stock_count']} productos con stock bajo",
                'action_label' => 'Revisar',
                'url' => route('seller.shops.inventory.index', [$shop, 'stock' => 'low']),
                'severity' => 'warning',
            ];
        }

        if ($currentState['out_of_stock_count'] > 0) {
            $items[] = [
                'type' => 'out_of_stock',
                'count' => $currentState['out_of_stock_count'],
                'label' => "{$currentState['out_of_stock_count']} productos agotados",
                'action_label' => 'Reponer',
                'url' => route('seller.shops.inventory.index', [$shop, 'stock' => 'out']),
                'severity' => 'danger',
            ];
        }

        if ($currentState['missing_cost_count'] > 0) {
            $items[] = [
                'type' => 'missing_cost',
                'count' => $currentState['missing_cost_count'],
                'label' => "{$currentState['missing_cost_count']} productos sin costo asignado",
                'action_label' => 'Corregir',
                'url' => route('seller.shops.inventory.index', [$shop, 'cost_max' => '0']),
                'severity' => 'info',
            ];
        }

        if ($currentState['pending_price_rules_count'] > 0) {
            $items[] = [
                'type' => 'pricing',
                'count' => $currentState['pending_price_rules_count'],
                'label' => "{$currentState['pending_price_rules_count']} precios requieren aprobación",
                'action_label' => 'Aprobar',
                'url' => route('seller.shops.pricing.index', $shop),
                'severity' => 'info',
            ];
        }

        if ($currentState['aging']['overdue_count'] > 0) {
            $items[] = [
                'type' => 'overdue_credit',
                'count' => $currentState['aging']['overdue_count'],
                'label' => "{$currentState['aging']['overdue_count']} clientes con créditos vencidos (>30 días)",
                'action_label' => 'Cobrar',
                'url' => route('seller.shops.customers.index', $shop),
                'severity' => 'danger',
            ];
        }

        return $items;
    }

    /**
     * Compute product profitability table sorted by gross profit by default.
     */
    protected function computeProductProfitability(Shop $shop, string $fromDatetime, string $toDatetime, string $sort = 'profit', string $direction = 'desc'): array
    {
        $products = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.shop_id', $shop->id)
            ->whereBetween('invoices.issued_at', [$fromDatetime, $toDatetime])
            ->where('invoices.status', '!=', 'void')
            ->selectRaw('
                invoice_items.product_id,
                invoice_items.product_name,
                SUM(invoice_items.quantity) as units_sold,
                SUM(invoice_items.line_total - invoice_items.tax - invoice_items.general_discount_cents / 100.0) as revenue,
                SUM(invoice_items.total_cost_cents) / 100.0 as known_cost,
                SUM(CASE WHEN invoice_items.total_cost_cents IS NULL THEN 1 ELSE 0 END) as unknown_lines
            ')
            ->groupBy('invoice_items.product_id', 'invoice_items.product_name')
            ->get();

        // Adjust for returns
        $returns = DB::table('invoice_return_items')
            ->join('invoice_returns', 'invoice_returns.id', '=', 'invoice_return_items.invoice_return_id')
            ->join('invoice_items', 'invoice_items.id', '=', 'invoice_return_items.invoice_item_id')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.shop_id', $shop->id)
            ->whereBetween('invoice_returns.created_at', [$fromDatetime, $toDatetime])
            ->selectRaw('
                invoice_items.product_id,
                invoice_items.product_name,
                SUM(invoice_return_items.quantity) as units_returned,
                SUM(invoice_return_items.refund - invoice_return_items.tax_refund) as refund_revenue,
                SUM(CASE WHEN invoice_return_items.restock = 1 THEN COALESCE(invoice_return_items.total_cost_cents, 0) ELSE 0 END) / 100.0 as restocked_cost
            ')
            ->groupBy('invoice_items.product_id', 'invoice_items.product_name')
            ->get()
            ->keyBy(fn ($item) => "{$item->product_id}:{$item->product_name}");

        $result = $products->map(function ($row) use ($returns) {
            $key = "{$row->product_id}:{$row->product_name}";
            $ret = $returns->get($key);

            $units = (int) $row->units_sold - ($ret ? (int) $ret->units_returned : 0);
            $revenue = (float) $row->revenue - ($ret ? (float) $ret->refund_revenue : 0.0);
            $cost = (float) $row->known_cost - ($ret ? (float) $ret->restocked_cost : 0.0);
            $cost = max(0.0, $cost);
            $profit = $revenue - $cost;
            $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0.0;

            return [
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'units' => $units,
                'revenue' => round($revenue, 2),
                'cost' => round($cost, 2),
                'gross_profit' => round($profit, 2),
                'margin_percent' => $margin,
                'has_unknown_cost' => (int) $row->unknown_lines > 0,
            ];
        });

        // Sort by requested metric (default: gross_profit DESC)
        $isDesc = strtolower($direction) !== 'asc';

        return match ($sort) {
            'units' => $result->sortBy('units', SORT_REGULAR, $isDesc)->values()->all(),
            'revenue' => $result->sortBy('revenue', SORT_REGULAR, $isDesc)->values()->all(),
            'margin' => $result->sortBy('margin_percent', SORT_REGULAR, $isDesc)->values()->all(),
            'cost_incomplete' => $result->sortByDesc('has_unknown_cost')->values()->all(),
            default => $result->sortBy('gross_profit', SORT_REGULAR, $isDesc)->values()->all(),
        };
    }

    /**
     * Compute clean Income Statement (Estado de Resultados).
     */
    protected function computeIncomeStatement(Shop $shop, array $period): array
    {
        // Operating expenses by category
        $expensesByCategory = DB::table('expenses')
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->where('expenses.shop_id', $shop->id)
            ->where('expenses.payment_status', 'paid')
            ->selectRaw('expense_categories.name as category_name, SUM(expenses.amount_cents) / 100.0 as category_total')
            ->groupBy('expense_categories.name')
            ->orderByDesc('category_total')
            ->get()
            ->map(fn ($r) => ['name' => $r->category_name, 'total' => (float) $r->category_total])
            ->all();

        return [
            'gross_sales' => $period['gross_sales'],
            'discounts' => $period['discounts'],
            'returns' => $period['returns'],
            'net_sales' => $period['net_sales'],
            'fifo_cogs' => $period['fifo_cogs'],
            'gross_profit' => $period['gross_profit'],
            'gross_margin_percent' => $period['gross_margin_percent'],
            'operating_expenses_total' => $period['operating_expenses'],
            'expenses_by_category' => $expensesByCategory,
            'commissions' => $period['commissions_generated'],
            'operating_profit' => $period['operating_profit'],
            'operating_margin_percent' => $period['operating_margin_percent'],
        ];
    }

    /**
     * Compute Cash Flow (Flujo de Efectivo).
     * Rule: Cash Flow != Profit.
     */
    protected function computeCashFlow(Shop $shop, string $fromDatetime, string $toDatetime, array $period): array
    {
        // 1. INFLOWS:
        // Cash collected from invoice payments (cash, card, transfer)
        $invoiceCollections = DB::table('invoice_payments')
            ->where('shop_id', $shop->id)
            ->whereBetween('received_at', [$fromDatetime, $toDatetime])
            ->selectRaw('payment_method, SUM(amount_cents) / 100.0 as total')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $salesCash = (float) ($invoiceCollections->get('cash')?->total ?? 0);
        $salesCard = (float) ($invoiceCollections->get('card')?->total ?? 0);
        $salesTransfer = (float) ($invoiceCollections->get('bank_transfer')?->total ?? 0);
        $salesOther = (float) ($invoiceCollections->get('other')?->total ?? 0);

        // Debt payments collected from customers
        $debtCollections = (float) abs(DB::table('customer_account_entries')
            ->where('shop_id', $shop->id)
            ->whereBetween('created_at', [$fromDatetime, $toDatetime])
            ->where('type', 'payment')
            ->sum('amount'));

        // Cash register ins and owner contributions
        $cashInMovements = (float) DB::table('cash_movements')
            ->where('shop_id', $shop->id)
            ->whereBetween('occurred_at', [$fromDatetime, $toDatetime])
            ->whereIn('type', ['cash_in', 'owner_contribution'])
            ->selectRaw('SUM(amount_cents) / 100.0 as total')
            ->value('total') ?: 0.0;

        $totalInflows = $salesCash + $salesCard + $salesTransfer + $salesOther + $debtCollections + $cashInMovements;

        // 2. OUTFLOWS:
        // Paid expenses
        $expensesPaid = (float) DB::table('expenses')
            ->where('shop_id', $shop->id)
            ->whereBetween('occurred_at', [$fromDatetime, $toDatetime])
            ->where('payment_status', 'paid')
            ->selectRaw('SUM(amount_cents) / 100.0 as total')
            ->value('total') ?: 0.0;

        // Cash register outs and owner withdrawals
        $cashOutMovements = (float) abs(DB::table('cash_movements')
            ->where('shop_id', $shop->id)
            ->whereBetween('occurred_at', [$fromDatetime, $toDatetime])
            ->whereIn('type', ['cash_out', 'owner_withdrawal'])
            ->selectRaw('SUM(amount_cents) / 100.0 as total')
            ->value('total') ?: 0.0);

        $totalOutflows = $expensesPaid + $cashOutMovements;
        $netCashFlow = $totalInflows - $totalOutflows;

        return [
            'inflows' => [
                'sales_cash' => $salesCash,
                'sales_card' => $salesCard,
                'sales_transfer' => $salesTransfer,
                'sales_other' => $salesOther,
                'debt_collections' => $debtCollections,
                'other_inflows' => $cashInMovements,
                'total' => $totalInflows,
            ],
            'outflows' => [
                'expenses_paid' => $expensesPaid,
                'cash_out' => $cashOutMovements,
                'total' => $totalOutflows,
            ],
            'net_cash_flow' => $netCashFlow,
        ];
    }
}
