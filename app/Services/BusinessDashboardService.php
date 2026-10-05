<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Shop;
use App\Support\Money;
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
        $incomeStatement = $this->computeIncomeStatement($shop, $carbonFrom->toDateTimeString(), $carbonTo->toDateTimeString(), $currentPeriod);
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
     * Enforces mathematical integrity:
     * - Gross sales = sales before discounts and without taxes.
     * - Taxes are excluded from income and tracked separately.
     * - Net sales = Gross sales - Total discounts - Base returns.
     * - Gross profit = Net sales - Net FIFO cost.
     * - Operating profit = Gross profit - Incurred operating expenses - Commissions.
     */
    protected function computePeriodMetrics(Shop $shop, string $fromDatetime, string $toDatetime): array
    {
        // 1. Invoices stats in period
        $invoiceStats = DB::table('invoices')
            ->where('shop_id', $shop->id)
            ->whereBetween('issued_at', [$fromDatetime, $toDatetime])
            ->where('status', '!=', 'void')
            ->selectRaw('
                COUNT(id) as sales_count,
                COALESCE(SUM(discount), 0) as general_discounts,
                COALESCE(SUM(tax), 0) as invoice_tax,
                COALESCE(SUM(total), 0) as total_invoiced,
                COALESCE(SUM(commission_amount), 0) as commissions_generated
            ')
            ->first();

        // 2. Line items stats in period
        $soldItemsStats = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.shop_id', $shop->id)
            ->whereBetween('invoices.issued_at', [$fromDatetime, $toDatetime])
            ->where('invoices.status', '!=', 'void')
            ->selectRaw('
                COALESCE(SUM(invoice_items.quantity), 0) as units_sold,
                COALESCE(SUM(invoice_items.unit_price * invoice_items.quantity), 0) as gross_line_sales,
                COALESCE(SUM(invoice_items.discount), 0) as line_discounts,
                COALESCE(SUM(invoice_items.tax), 0) as line_tax,
                COALESCE(SUM(invoice_items.total_cost_cents), 0) as cost_cents,
                COUNT(invoice_items.id) as total_lines,
                SUM(CASE WHEN invoice_items.total_cost_cents IS NOT NULL THEN 1 ELSE 0 END) as known_cost_lines,
                SUM(CASE WHEN invoice_items.total_cost_cents IS NOT NULL THEN (invoice_items.unit_price * invoice_items.quantity - invoice_items.discount - COALESCE(invoice_items.general_discount_cents, 0) / 100.0) ELSE 0 END) as known_revenue,
                SUM(invoice_items.unit_price * invoice_items.quantity - invoice_items.discount - COALESCE(invoice_items.general_discount_cents, 0) / 100.0) as total_net_line_revenue
            ')
            ->first();

        // 3. Returns in period
        $returnsStats = DB::table('invoice_return_items')
            ->join('invoice_returns', 'invoice_returns.id', '=', 'invoice_return_items.invoice_return_id')
            ->join('invoices', 'invoices.id', '=', 'invoice_returns.invoice_id')
            ->where('invoices.shop_id', $shop->id)
            ->whereBetween('invoice_returns.created_at', [$fromDatetime, $toDatetime])
            ->selectRaw('
                COALESCE(SUM(invoice_return_items.quantity), 0) as units_returned,
                COALESCE(SUM(invoice_return_items.refund - invoice_return_items.tax_refund), 0) as base_refund,
                COALESCE(SUM(invoice_return_items.tax_refund), 0) as tax_refund,
                COALESCE(SUM(CASE WHEN invoice_return_items.restock = 1 THEN invoice_return_items.total_cost_cents ELSE 0 END), 0) as restocked_cost_cents
            ')
            ->first();

        $grossSales = (float) ($soldItemsStats->gross_line_sales ?? 0);
        $lineDiscounts = (float) ($soldItemsStats->line_discounts ?? 0);
        $generalDiscounts = (float) ($invoiceStats->general_discounts ?? 0);
        $totalDiscounts = $lineDiscounts + $generalDiscounts;

        $taxesCollected = (float) ($soldItemsStats->line_tax ?? 0) + (float) ($invoiceStats->invoice_tax ?? 0);
        $taxRefunded = (float) ($returnsStats->tax_refund ?? 0);
        $netTaxesCollected = max(0.0, $taxesCollected - $taxRefunded);

        $baseReturns = (float) ($returnsStats->base_refund ?? 0);
        $netSales = max(0.0, round($grossSales - $totalDiscounts - $baseReturns, 2));

        $unitsSold = max(0, (int) ($soldItemsStats->units_sold ?? 0) - (int) ($returnsStats->units_returned ?? 0));
        $netCostCents = max(0, (int) ($soldItemsStats->cost_cents ?? 0) - (int) ($returnsStats->restocked_cost_cents ?? 0));
        $fifoCogs = round($netCostCents / 100.0, 2);

        // Revenue-weighted cost coverage (primary indicator)
        $knownRevenue = (float) ($soldItemsStats->known_revenue ?? 0);
        $totalNetLineRevenue = (float) ($soldItemsStats->total_net_line_revenue ?? 0);
        $revenueCoveragePercent = $totalNetLineRevenue > 0
            ? round(($knownRevenue / $totalNetLineRevenue) * 100, 1)
            : 100.0;

        $totalLines = (int) ($soldItemsStats->total_lines ?? 0);
        $knownCostLines = (int) ($soldItemsStats->known_cost_lines ?? 0);
        $lineCoveragePercent = $totalLines > 0 ? round(($knownCostLines / $totalLines) * 100, 1) : 100.0;
        $isCoveragePartial = $revenueCoveragePercent < 99.9;

        // Profit & Margin
        $grossProfit = round($netSales - $fifoCogs, 2);
        $grossMarginPercent = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 1) : 0.0;

        // 4. Operating Expenses incurred in period (P&L recognises incurred obligation)
        $expensesStats = DB::table('expenses')
            ->where('shop_id', $shop->id)
            ->whereBetween('occurred_at', [$fromDatetime, $toDatetime])
            ->selectRaw('COALESCE(SUM(amount_cents), 0) as expenses_cents')
            ->first();

        $operatingExpenses = round(((int) ($expensesStats->expenses_cents ?? 0)) / 100.0, 2);
        $commissionsGenerated = (float) ($invoiceStats->commissions_generated ?? 0);

        $operatingProfit = round($grossProfit - $operatingExpenses - $commissionsGenerated, 2);
        $operatingMarginPercent = $netSales > 0 ? round(($operatingProfit / $netSales) * 100, 1) : 0.0;

        // 5. Collections and Credit in period
        $collectedFromInvoicesCents = (int) DB::table('invoice_payments')
            ->where('shop_id', $shop->id)
            ->whereNull('customer_account_entry_id')
            ->whereBetween('received_at', [$fromDatetime, $toDatetime])
            ->sum('amount_cents');

        $creditGenerated = (float) DB::table('customer_account_entries')
            ->where('shop_id', $shop->id)
            ->whereBetween('created_at', [$fromDatetime, $toDatetime])
            ->where('type', 'charge')
            ->whereNotNull('invoice_id')
            ->sum('amount');

        $collectedFromCredit = (float) abs(DB::table('customer_account_entries')
            ->where('shop_id', $shop->id)
            ->whereBetween('created_at', [$fromDatetime, $toDatetime])
            ->where('type', 'payment')
            ->sum('amount'));

        $totalCollectedInPeriod = round(($collectedFromInvoicesCents / 100.0) + $collectedFromCredit, 2);
        $salesCount = (int) ($invoiceStats->sales_count ?? 0);
        $averageTicket = $salesCount > 0 ? round($netSales / $salesCount, 2) : 0.0;

        return [
            'gross_sales' => round($grossSales, 2),
            'discounts' => round($totalDiscounts, 2),
            'line_discounts' => round($lineDiscounts, 2),
            'general_discounts' => round($generalDiscounts, 2),
            'tax_collected' => round($netTaxesCollected, 2),
            'gross_tax_collected' => round($taxesCollected, 2),
            'returns' => round($baseReturns, 2),
            'net_sales' => $netSales,
            'fifo_cogs' => $fifoCogs,
            'units_sold' => $unitsSold,
            'cost_coverage_percent' => $lineCoveragePercent,
            'revenue_cost_coverage' => $revenueCoveragePercent,
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
            'credit_generated' => round($creditGenerated, 2),
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
     * Enforces real invoice-level aging by due_date (or issued_at fallback) and real unpaid balance.
     */
    protected function computeCurrentState(Shop $shop): array
    {
        // 1. Receivables & Real Invoice Aging
        $customers = $shop->customers()->where('balance', '>', 0)->get();
        $totalReceivable = (float) $customers->sum('balance');

        $aging = [
            'days_0_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'days_over_90' => 0.0,
            'overdue_count' => 0,
        ];

        $now = now()->startOfDay();

        // Get all unpaid or partially paid invoices for this shop with customer attached
        $unpaidInvoices = Invoice::query()
            ->where('shop_id', $shop->id)
            ->whereNotNull('customer_id')
            ->whereIn('status', ['pending', 'partial'])
            ->with(['payments'])
            ->get();

        $invoiceIds = $unpaidInvoices->pluck('id');
        $invoiceReturns = $invoiceIds->isNotEmpty()
            ? DB::table('invoice_returns')
                ->whereIn('invoice_id', $invoiceIds)
                ->selectRaw('invoice_id, SUM(total) as return_total')
                ->groupBy('invoice_id')
                ->pluck('return_total', 'invoice_id')
            : collect();

        $customerOpenInvoicesCents = [];
        $totalInvoicesCents = 0;

        foreach ($unpaidInvoices as $invoice) {
            $invoiceTotalCents = Money::toCents($invoice->total);
            $paidCents = (int) $invoice->payments->sum('amount_cents');
            $returnedAmount = (float) ($invoiceReturns->get($invoice->id) ?? 0);
            $returnedCents = Money::toCents($returnedAmount);
            $unpaidCents = max(0, $invoiceTotalCents - $paidCents - $returnedCents);

            if ($unpaidCents <= 0) {
                continue;
            }

            $customerOpenInvoicesCents[$invoice->customer_id] = ($customerOpenInvoicesCents[$invoice->customer_id] ?? 0) + $unpaidCents;
            $totalInvoicesCents += $unpaidCents;

            $unpaidAmount = $unpaidCents / 100.0;

            // Determine reference date for aging: due_date has priority; fallback to issued_at
            if ($invoice->due_date) {
                $refDate = Carbon::parse($invoice->due_date)->startOfDay();
                $days = $now->isAfter($refDate) ? abs((int) $now->diffInDays($refDate)) : 0;
            } else {
                $refDate = Carbon::parse($invoice->issued_at)->startOfDay();
                $days = abs((int) $now->diffInDays($refDate));
            }

            if ($days <= 30) {
                $aging['days_0_30'] += $unpaidAmount;
            } elseif ($days <= 60) {
                $aging['days_31_60'] += $unpaidAmount;
                $aging['overdue_count']++;
            } elseif ($days <= 90) {
                $aging['days_61_90'] += $unpaidAmount;
                $aging['overdue_count']++;
            } else {
                $aging['days_over_90'] += $unpaidAmount;
                $aging['overdue_count']++;
            }
        }

        // Account for any remaining customer balance not tied to open invoices (e.g. manual charges)
        $totalUnallocatedCents = 0;
        foreach ($customers as $customer) {
            $customerBalCents = Money::toCents($customer->balance);
            $invoicesCents = $customerOpenInvoicesCents[$customer->id] ?? 0;
            if ($customerBalCents > $invoicesCents) {
                $totalUnallocatedCents += ($customerBalCents - $invoicesCents);
            }
        }

        $aging['days_0_30'] = round($aging['days_0_30'], 2);
        $aging['days_31_60'] = round($aging['days_31_60'], 2);
        $aging['days_61_90'] = round($aging['days_61_90'], 2);
        $aging['days_over_90'] = round($aging['days_over_90'], 2);
        $aging['invoices_total'] = round($totalInvoicesCents / 100.0, 2);
        $aging['unallocated_receivables'] = round($totalUnallocatedCents / 100.0, 2);
        $aging['total_receivable'] = round($totalReceivable, 2);
        $aging['reconciliation_difference'] = round($aging['total_receivable'] - ($aging['invoices_total'] + $aging['unallocated_receivables']), 2);

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

        $totalInventoryCostValue = round(($inventoryCostCents / 100.0) + ($directInventoryCost ?: 0.0), 2);

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
                'label' => "{$currentState['aging']['overdue_count']} facturas/clientes con créditos vencidos (>30 días)",
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
                SUM(invoice_items.unit_price * invoice_items.quantity - invoice_items.discount - COALESCE(invoice_items.general_discount_cents, 0) / 100.0) as revenue,
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
     * Enforces:
     * - expenses_by_category filtered strictly by occurred_at BETWEEN fromDatetime AND toDatetime.
     * - SUM(expenses_by_category) == operating_expenses_total.
     */
    protected function computeIncomeStatement(Shop $shop, string $fromDatetime, string $toDatetime, array $period): array
    {
        // Operating expenses by category strictly within the requested time frame
        $expensesByCategory = DB::table('expenses')
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->where('expenses.shop_id', $shop->id)
            ->whereBetween('expenses.occurred_at', [$fromDatetime, $toDatetime])
            ->selectRaw('expense_categories.name as category_name, SUM(expenses.amount_cents) / 100.0 as category_total')
            ->groupBy('expense_categories.name')
            ->orderByDesc('category_total')
            ->get()
            ->map(fn ($r) => ['name' => $r->category_name, 'total' => round((float) $r->category_total, 2)])
            ->all();

        return [
            'gross_sales' => $period['gross_sales'],
            'discounts' => $period['discounts'],
            'tax_collected' => $period['tax_collected'],
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
     * Only counts actual money moved.
     * Avoids double counting cash movements originated by invoice payments or expense payments.
     */
    protected function computeCashFlow(Shop $shop, string $fromDatetime, string $toDatetime, array $period): array
    {
        // 1. INFLOWS:
        // Cash collected from direct invoice payments (cash, card, transfer, other)
        $invoiceCollections = DB::table('invoice_payments')
            ->where('shop_id', $shop->id)
            ->whereNull('customer_account_entry_id')
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

        // Standalone cash register ins and owner contributions (excluding movements from sales/customer payments)
        $cashInMovements = (float) DB::table('cash_movements')
            ->where('shop_id', $shop->id)
            ->whereBetween('occurred_at', [$fromDatetime, $toDatetime])
            ->whereIn('type', ['cash_in', 'owner_contribution'])
            ->whereNull('reference_type')
            ->selectRaw('SUM(amount_cents) / 100.0 as total')
            ->value('total') ?: 0.0;

        $totalInflows = round($salesCash + $salesCard + $salesTransfer + $salesOther + $debtCollections + $cashInMovements, 2);

        // 2. OUTFLOWS:
        // Actual paid expenses from expense_payments
        $expensesPaidFromPayments = (float) DB::table('expense_payments')
            ->where('shop_id', $shop->id)
            ->whereBetween('paid_at', [$fromDatetime, $toDatetime])
            ->selectRaw('SUM(amount_cents) / 100.0 as total')
            ->value('total') ?: 0.0;

        // Fallback for legacy expenses without separate payment rows (if any)
        $legacyExpensesPaid = (float) DB::table('expenses')
            ->where('shop_id', $shop->id)
            ->whereBetween('occurred_at', [$fromDatetime, $toDatetime])
            ->where('payment_status', 'paid')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('expense_payments')
                    ->whereColumn('expense_payments.expense_id', 'expenses.id');
            })
            ->selectRaw('SUM(amount_cents) / 100.0 as total')
            ->value('total') ?: 0.0;

        $totalExpensesPaid = round($expensesPaidFromPayments + $legacyExpensesPaid, 2);

        // Standalone cash register outs and owner withdrawals (excluding movements from expense payments)
        $cashOutMovements = (float) abs(DB::table('cash_movements')
            ->where('shop_id', $shop->id)
            ->whereBetween('occurred_at', [$fromDatetime, $toDatetime])
            ->whereIn('type', ['cash_out', 'owner_withdrawal'])
            ->whereNull('reference_type')
            ->selectRaw('SUM(amount_cents) / 100.0 as total')
            ->value('total') ?: 0.0);

        $totalOutflows = round($totalExpensesPaid + $cashOutMovements, 2);
        $netCashFlow = round($totalInflows - $totalOutflows, 2);

        return [
            'inflows' => [
                'sales_cash' => round($salesCash, 2),
                'sales_card' => round($salesCard, 2),
                'sales_transfer' => round($salesTransfer, 2),
                'sales_other' => round($salesOther, 2),
                'debt_collections' => round($debtCollections, 2),
                'other_inflows' => round($cashInMovements, 2),
                'total' => $totalInflows,
            ],
            'outflows' => [
                'expenses_paid' => $totalExpensesPaid,
                'cash_out' => round($cashOutMovements, 2),
                'total' => $totalOutflows,
            ],
            'net_cash_flow' => $netCashFlow,
        ];
    }
}
