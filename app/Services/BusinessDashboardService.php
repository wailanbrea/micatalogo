<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Shop;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
                COALESCE(SUM(ROUND(discount * 100)), 0) as general_discounts_cents,
                COALESCE(SUM(ROUND(tax * 100)), 0) as invoice_tax_cents,
                COALESCE(SUM(ROUND(total * 100)), 0) as total_invoiced_cents,
                COALESCE(SUM(ROUND(commission_amount * 100)), 0) as commissions_generated_cents
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
                COALESCE(SUM(ROUND(invoice_items.unit_price * invoice_items.quantity * 100)), 0) as gross_line_sales_cents,
                COALESCE(SUM(ROUND(invoice_items.discount * 100)), 0) as line_discounts_cents,
                COALESCE(SUM(ROUND(invoice_items.tax * 100)), 0) as line_tax_cents,
                COALESCE(SUM(invoice_items.total_cost_cents), 0) as cost_cents,
                COUNT(invoice_items.id) as total_lines,
                SUM(CASE WHEN invoice_items.total_cost_cents IS NOT NULL THEN 1 ELSE 0 END) as known_cost_lines,
                SUM(CASE WHEN invoice_items.total_cost_cents IS NOT NULL THEN ROUND(invoice_items.unit_price * invoice_items.quantity * 100) - ROUND(invoice_items.discount * 100) - COALESCE(invoice_items.general_discount_cents, 0) ELSE 0 END) as known_revenue_cents,
                SUM(ROUND(invoice_items.unit_price * invoice_items.quantity * 100) - ROUND(invoice_items.discount * 100) - COALESCE(invoice_items.general_discount_cents, 0)) as total_net_line_revenue_cents
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
                COALESCE(SUM(ROUND((invoice_return_items.refund - invoice_return_items.tax_refund) * 100)), 0) as base_refund_cents,
                COALESCE(SUM(ROUND(invoice_return_items.tax_refund * 100)), 0) as tax_refund_cents,
                COALESCE(SUM(CASE WHEN invoice_return_items.restock = 1 THEN invoice_return_items.total_cost_cents ELSE 0 END), 0) as restocked_cost_cents
            ')
            ->first();

        $grossSalesCents = (int) ($soldItemsStats->gross_line_sales_cents ?? 0);
        $lineDiscountsCents = (int) ($soldItemsStats->line_discounts_cents ?? 0);
        $generalDiscountsCents = (int) ($invoiceStats->general_discounts_cents ?? 0);
        $totalDiscountsCents = $lineDiscountsCents + $generalDiscountsCents;

        $taxesCollectedCents = (int) ($soldItemsStats->line_tax_cents ?? 0) + (int) ($invoiceStats->invoice_tax_cents ?? 0);
        $taxRefundedCents = (int) ($returnsStats->tax_refund_cents ?? 0);
        $netTaxesCollectedCents = max(0, $taxesCollectedCents - $taxRefundedCents);

        $baseReturnsCents = (int) ($returnsStats->base_refund_cents ?? 0);
        $netSalesCents = max(0, $grossSalesCents - $totalDiscountsCents - $baseReturnsCents);

        $unitsSold = max(0, (int) ($soldItemsStats->units_sold ?? 0) - (int) ($returnsStats->units_returned ?? 0));
        $netCostCents = max(0, (int) ($soldItemsStats->cost_cents ?? 0) - (int) ($returnsStats->restocked_cost_cents ?? 0));

        // Revenue-weighted cost coverage (primary indicator)
        $knownRevenueCents = (int) ($soldItemsStats->known_revenue_cents ?? 0);
        $totalNetLineRevenueCents = (int) ($soldItemsStats->total_net_line_revenue_cents ?? 0);
        $revenueCoveragePercent = $totalNetLineRevenueCents > 0
            ? round(($knownRevenueCents / $totalNetLineRevenueCents) * 100, 1)
            : 100.0;

        $totalLines = (int) ($soldItemsStats->total_lines ?? 0);
        $knownCostLines = (int) ($soldItemsStats->known_cost_lines ?? 0);
        $lineCoveragePercent = $totalLines > 0 ? round(($knownCostLines / $totalLines) * 100, 1) : 100.0;
        $isCoveragePartial = $revenueCoveragePercent < 99.9;

        // Profit & Margin
        $grossProfitCents = $netSalesCents - $netCostCents;
        $grossMarginPercent = $netSalesCents > 0 ? round(($grossProfitCents / $netSalesCents) * 100, 1) : 0.0;

        // 4. Operating Expenses incurred in period (P&L recognises incurred obligation)
        $expensesStats = DB::table('expenses')
            ->where('shop_id', $shop->id)
            ->whereBetween('occurred_at', [$fromDatetime, $toDatetime])
            ->selectRaw('COALESCE(SUM(amount_cents), 0) as expenses_cents')
            ->first();

        $operatingExpensesCents = (int) ($expensesStats->expenses_cents ?? 0);
        $commissionsGeneratedCents = (int) ($invoiceStats->commissions_generated_cents ?? 0);

        $operatingProfitCents = $grossProfitCents - $operatingExpensesCents - $commissionsGeneratedCents;
        $operatingMarginPercent = $netSalesCents > 0 ? round(($operatingProfitCents / $netSalesCents) * 100, 1) : 0.0;

        // 5. Collections and Credit in period
        $invoicePaymentsQuery = DB::table('invoice_payments')
            ->where('shop_id', $shop->id);
        if (Schema::hasColumn('invoice_payments', 'customer_account_entry_id')) {
            $invoicePaymentsQuery->whereNull('customer_account_entry_id');
        }
        $collectedFromInvoicesCents = (int) $invoicePaymentsQuery
            ->whereBetween('received_at', [$fromDatetime, $toDatetime])
            ->sum('amount_cents');

        $creditGeneratedCents = Money::toCents(DB::table('customer_account_entries')
            ->where('shop_id', $shop->id)
            ->whereBetween('created_at', [$fromDatetime, $toDatetime])
            ->where('type', 'charge')
            ->whereNotNull('invoice_id')
            ->sum('amount'));

        $collectedFromCreditCents = abs(Money::toCents(DB::table('customer_account_entries')
            ->where('shop_id', $shop->id)
            ->whereBetween('created_at', [$fromDatetime, $toDatetime])
            ->where('type', 'payment')
            ->sum('amount')));

        $totalCollectedInPeriodCents = $collectedFromInvoicesCents + $collectedFromCreditCents;
        $salesCount = (int) ($invoiceStats->sales_count ?? 0);
        $averageTicketCents = $salesCount > 0
            ? Money::toCents(Money::perUnitDecimal($netSalesCents, $salesCount))
            : 0;

        return [
            'gross_sales' => $this->moneyNumber($grossSalesCents),
            'discounts' => $this->moneyNumber($totalDiscountsCents),
            'line_discounts' => $this->moneyNumber($lineDiscountsCents),
            'general_discounts' => $this->moneyNumber($generalDiscountsCents),
            'tax_collected' => $this->moneyNumber($netTaxesCollectedCents),
            'gross_tax_collected' => $this->moneyNumber($taxesCollectedCents),
            'returns' => $this->moneyNumber($baseReturnsCents),
            'net_sales' => $this->moneyNumber($netSalesCents),
            'fifo_cogs' => $this->moneyNumber($netCostCents),
            'units_sold' => $unitsSold,
            'cost_coverage_percent' => $lineCoveragePercent,
            'revenue_cost_coverage' => $revenueCoveragePercent,
            'is_cost_coverage_partial' => $isCoveragePartial,
            'gross_profit' => $this->moneyNumber($grossProfitCents),
            'gross_margin_percent' => $grossMarginPercent,
            'operating_expenses' => $this->moneyNumber($operatingExpensesCents),
            'commissions_generated' => $this->moneyNumber($commissionsGeneratedCents),
            'operating_profit' => $this->moneyNumber($operatingProfitCents),
            'operating_margin_percent' => $operatingMarginPercent,
            'sales_count' => $salesCount,
            'average_ticket' => $this->moneyNumber($averageTicketCents),
            'collected_in_period' => $this->moneyNumber($totalCollectedInPeriodCents),
            'credit_generated' => $this->moneyNumber($creditGeneratedCents),
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
        $totalReceivableCents = $customers->sum(
            fn (Customer $customer): int => Money::toCents($customer->balance)
        );

        $agingCents = [
            'days_0_30' => 0,
            'days_31_60' => 0,
            'days_61_90' => 0,
            'days_over_90' => 0,
            'overdue_count' => 0,
            'invoice_details' => [],
        ];

        $now = now()->startOfDay();

        // Get all unpaid or partially paid invoices for this shop with customer attached
        $unpaidInvoices = Invoice::query()
            ->where('shop_id', $shop->id)
            ->whereNotNull('customer_id')
            ->whereIn('status', ['pending', 'partial'])
            ->with(['payments', 'customer:id,name'])
            ->orderByRaw('COALESCE(due_date, issued_at) asc')
            ->get();

        $invoiceIds = $unpaidInvoices->pluck('id');
        $invoiceReturns = $invoiceIds->isNotEmpty()
            ? DB::table('invoice_returns')
                ->whereIn('invoice_id', $invoiceIds)
                ->selectRaw('invoice_id, SUM(total) as return_total')
                ->groupBy('invoice_id')
                ->pluck('return_total', 'invoice_id')
            : collect();

        $remainingByCustomerCents = $customers->mapWithKeys(fn ($customer) => [
            $customer->id => Money::toCents($customer->balance),
        ])->all();
        $totalInvoicesCents = 0;

        foreach ($unpaidInvoices as $invoice) {
            $invoiceTotalCents = Money::toCents($invoice->total);
            $paidCents = (int) $invoice->payments->sum('amount_cents');
            $returnedCents = Money::toCents($invoiceReturns->get($invoice->id) ?? 0);
            $invoiceUnpaidCents = max(0, $invoiceTotalCents - $paidCents - $returnedCents);
            $customerBalanceCents = $remainingByCustomerCents[$invoice->customer_id] ?? 0;
            $unpaidCents = min($invoiceUnpaidCents, $customerBalanceCents);

            if ($unpaidCents <= 0) {
                continue;
            }

            $remainingByCustomerCents[$invoice->customer_id] -= $unpaidCents;
            $totalInvoicesCents += $unpaidCents;

            // Determine reference date for aging: due_date has priority; fallback to issued_at
            if ($invoice->due_date) {
                $refDate = Carbon::parse($invoice->due_date)->startOfDay();
                $days = $now->isAfter($refDate) ? abs((int) $now->diffInDays($refDate)) : 0;
            } else {
                $refDate = Carbon::parse($invoice->issued_at)->startOfDay();
                $days = abs((int) $now->diffInDays($refDate));
            }

            if ($days <= 30) {
                $bucket = '0-30';
                $agingCents['days_0_30'] += $unpaidCents;
            } elseif ($days <= 60) {
                $bucket = '31-60';
                $agingCents['days_31_60'] += $unpaidCents;
                $agingCents['overdue_count']++;
            } elseif ($days <= 90) {
                $bucket = '61-90';
                $agingCents['days_61_90'] += $unpaidCents;
                $agingCents['overdue_count']++;
            } else {
                $bucket = '>90';
                $agingCents['days_over_90'] += $unpaidCents;
                $agingCents['overdue_count']++;
            }

            $agingCents['invoice_details'][] = [
                'invoice_number' => (string) $invoice->invoice_number,
                'customer_name' => (string) ($invoice->customer?->name ?? 'Cliente'),
                'reference_date' => $refDate->toDateString(),
                'overdue_days' => $days,
                'outstanding_amount' => $this->moneyNumber($unpaidCents),
                'aging_bucket' => $bucket,
            ];
        }

        // Include balances not represented by open invoices as current, unallocated receivables.
        $totalUnallocatedCents = 0;
        foreach ($customers as $customer) {
            $unallocatedCents = $remainingByCustomerCents[$customer->id] ?? Money::toCents($customer->balance);
            $totalUnallocatedCents += $unallocatedCents;
        }
        $agingCents['days_0_30'] += $totalUnallocatedCents;
        $aging = [
            'days_0_30' => $this->moneyNumber($agingCents['days_0_30']),
            'days_31_60' => $this->moneyNumber($agingCents['days_31_60']),
            'days_61_90' => $this->moneyNumber($agingCents['days_61_90']),
            'days_over_90' => $this->moneyNumber($agingCents['days_over_90']),
            'overdue_count' => $agingCents['overdue_count'],
            'invoice_details' => $agingCents['invoice_details'],
            'invoices_total' => $this->moneyNumber($totalInvoicesCents),
            'unallocated_receivables' => $this->moneyNumber($totalUnallocatedCents),
            'total_receivable' => $this->moneyNumber($totalReceivableCents),
            'reconciliation_difference' => $this->moneyNumber(
                $totalReceivableCents - $totalInvoicesCents - $totalUnallocatedCents
            ),
        ];

        // 2. Inventory Value at COST (FIFO remaining cost)
        $inventoryCostCents = (int) DB::table('inventory_lots')
            ->join('products', 'products.id', '=', 'inventory_lots.product_id')
            ->where('products.shop_id', $shop->id)
            ->where('inventory_lots.remaining_quantity', '>', 0)
            ->whereNotNull('inventory_lots.remaining_cost_cents')
            ->sum('inventory_lots.remaining_cost_cents');

        // Fallback for products without lots but with stock & cost_price
        $directInventoryCostCents = (int) DB::table('product_inventories')
            ->join('products', 'products.id', '=', 'product_inventories.product_id')
            ->leftJoin('inventory_lots', 'inventory_lots.product_id', '=', 'products.id')
            ->where('products.shop_id', $shop->id)
            ->whereNull('inventory_lots.id')
            ->where('product_inventories.track_inventory', 1)
            ->where('product_inventories.stock_quantity', '>', 0)
            ->whereNotNull('product_inventories.cost_price')
            ->selectRaw('SUM(ROUND(product_inventories.stock_quantity * product_inventories.cost_price * 100)) as cost_cents')
            ->value('cost_cents');

        $totalInventoryCostCents = $inventoryCostCents + $directInventoryCostCents;

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
            'receivable_total' => $this->moneyNumber($totalReceivableCents),
            'aging' => $aging,
            'inventory_cost_value' => $this->moneyNumber($totalInventoryCostCents),
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
                'url' => route('seller.shops.feature', [$shop, 'orders'], false),
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
                SUM(ROUND(invoice_items.unit_price * invoice_items.quantity * 100) - ROUND(invoice_items.discount * 100) - COALESCE(invoice_items.general_discount_cents, 0)) as revenue_cents,
                SUM(invoice_items.total_cost_cents) as known_cost_cents,
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
                SUM(ROUND((invoice_return_items.refund - invoice_return_items.tax_refund) * 100)) as refund_revenue_cents,
                SUM(CASE WHEN invoice_return_items.restock = 1 THEN COALESCE(invoice_return_items.total_cost_cents, 0) ELSE 0 END) as restocked_cost_cents
            ')
            ->groupBy('invoice_items.product_id', 'invoice_items.product_name')
            ->get()
            ->keyBy(fn ($item) => "{$item->product_id}:{$item->product_name}");

        $result = $products->map(function ($row) use ($returns) {
            $key = "{$row->product_id}:{$row->product_name}";
            $ret = $returns->get($key);

            $units = (int) $row->units_sold - ($ret ? (int) $ret->units_returned : 0);
            $revenueCents = (int) $row->revenue_cents - ($ret ? (int) $ret->refund_revenue_cents : 0);
            $costCents = max(0, (int) $row->known_cost_cents - ($ret ? (int) $ret->restocked_cost_cents : 0));
            $profitCents = $revenueCents - $costCents;
            $margin = $revenueCents > 0 ? round(($profitCents / $revenueCents) * 100, 1) : 0.0;

            return [
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'units' => $units,
                'revenue' => $this->moneyNumber($revenueCents),
                'cost' => $this->moneyNumber($costCents),
                'gross_profit' => $this->moneyNumber($profitCents),
                '_revenue_cents' => $revenueCents,
                '_gross_profit_cents' => $profitCents,
                'margin_percent' => $margin,
                'has_unknown_cost' => (int) $row->unknown_lines > 0,
            ];
        });

        // Sort by requested metric (default: gross_profit DESC)
        $isDesc = strtolower($direction) !== 'asc';

        $sorted = match ($sort) {
            'units' => $result->sortBy('units', SORT_REGULAR, $isDesc),
            'revenue' => $result->sortBy('_revenue_cents', SORT_REGULAR, $isDesc),
            'margin' => $result->sortBy('margin_percent', SORT_REGULAR, $isDesc),
            'cost_incomplete' => $result->sortByDesc('has_unknown_cost'),
            default => $result->sortBy('_gross_profit_cents', SORT_REGULAR, $isDesc),
        };

        return $sorted->values()->map(function (array $row): array {
            unset($row['_revenue_cents'], $row['_gross_profit_cents']);

            return $row;
        })->all();
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
            ->selectRaw('expense_categories.name as category_name, SUM(expenses.amount_cents) as category_total_cents')
            ->groupBy('expense_categories.name')
            ->orderByDesc('category_total_cents')
            ->get()
            ->map(fn ($r) => ['name' => $r->category_name, 'total' => $this->moneyNumber((int) $r->category_total_cents)])
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
        $invoicePaymentsQuery = DB::table('invoice_payments')
            ->where('shop_id', $shop->id);
        if (Schema::hasColumn('invoice_payments', 'customer_account_entry_id')) {
            $invoicePaymentsQuery->whereNull('customer_account_entry_id');
        }
        $invoiceCollections = $invoicePaymentsQuery
            ->whereBetween('received_at', [$fromDatetime, $toDatetime])
            ->selectRaw('payment_method, SUM(amount_cents) as total_cents')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $salesCashCents = (int) ($invoiceCollections->get('cash')?->total_cents ?? 0);
        $salesCardCents = (int) ($invoiceCollections->get('card')?->total_cents ?? 0);
        $salesTransferCents = (int) ($invoiceCollections->get('bank_transfer')?->total_cents ?? 0);
        $salesOtherCents = (int) ($invoiceCollections->get('other')?->total_cents ?? 0);

        // Debt payments are allocated to invoice_payments, so retain their payment method.
        $debtPaymentCollections = DB::table('invoice_payments')
            ->where('shop_id', $shop->id)
            ->whereNotNull('customer_account_entry_id')
            ->whereBetween('received_at', [$fromDatetime, $toDatetime])
            ->selectRaw('payment_method, SUM(amount_cents) as total_cents')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');
        $debtCollectionsCents = (int) $debtPaymentCollections->sum('total_cents');
        if ($debtCollectionsCents === 0) {
            $legacyDebtAmount = DB::table('customer_account_entries')
                ->where('shop_id', $shop->id)
                ->whereBetween('created_at', [$fromDatetime, $toDatetime])
                ->where('type', 'payment')
                ->sum('amount');
            $debtCollectionsCents = abs(Money::toCents($legacyDebtAmount));
        }
        $debtCollectionsCashCents = (int) ($debtPaymentCollections->get('cash')?->total_cents ?? 0);

        // Standalone cash register ins and owner contributions (excluding movements from sales/customer payments)
        $cashInMovementsCents = (int) DB::table('cash_movements')
            ->where('shop_id', $shop->id)
            ->whereBetween('occurred_at', [$fromDatetime, $toDatetime])
            ->whereIn('type', ['cash_in', 'owner_contribution'])
            ->whereNull('reference_type')
            ->sum('amount_cents');

        $totalInflowsCents = $salesCashCents + $salesCardCents + $salesTransferCents + $salesOtherCents + $debtCollectionsCents + $cashInMovementsCents;

        // 2. OUTFLOWS:
        // Actual paid expenses from expense_payments
        $expensePayments = DB::table('expense_payments')
            ->where('shop_id', $shop->id)
            ->whereBetween('paid_at', [$fromDatetime, $toDatetime])
            ->selectRaw('payment_method, SUM(amount_cents) as total_cents')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');
        $expensesPaidFromPaymentsCents = (int) $expensePayments->sum('total_cents');
        $expensesPaidCashCents = (int) ($expensePayments->get('cash')?->total_cents ?? 0);

        // Fallback for legacy expenses without separate payment rows (if any)
        $legacyExpensesPaidCents = (int) DB::table('expenses')
            ->where('shop_id', $shop->id)
            ->whereBetween('occurred_at', [$fromDatetime, $toDatetime])
            ->where('payment_status', 'paid')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('expense_payments')
                    ->whereColumn('expense_payments.expense_id', 'expenses.id');
            })
            ->sum('amount_cents');
        $legacyExpensesPaidCashCents = (int) DB::table('expenses')
            ->where('shop_id', $shop->id)
            ->whereBetween('occurred_at', [$fromDatetime, $toDatetime])
            ->where('payment_status', 'paid')
            ->where('payment_method', 'cash')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('expense_payments')
                    ->whereColumn('expense_payments.expense_id', 'expenses.id');
            })
            ->sum('amount_cents');

        $totalExpensesPaidCents = $expensesPaidFromPaymentsCents + $legacyExpensesPaidCents;
        $totalExpensesPaidCashCents = $expensesPaidCashCents + $legacyExpensesPaidCashCents;

        // Standalone cash register outs and owner withdrawals (excluding movements from expense payments)
        $cashOutMovementsCents = abs((int) DB::table('cash_movements')
            ->where('shop_id', $shop->id)
            ->whereBetween('occurred_at', [$fromDatetime, $toDatetime])
            ->whereIn('type', ['cash_out', 'owner_withdrawal'])
            ->whereNull('reference_type')
            ->sum('amount_cents'));

        $totalOutflowsCents = $totalExpensesPaidCents + $cashOutMovementsCents;
        $netCashFlowCents = $totalInflowsCents - $totalOutflowsCents;

        return [
            'inflows' => [
                'sales_cash' => (float) Money::toDecimal($salesCashCents),
                'sales_card' => (float) Money::toDecimal($salesCardCents),
                'sales_transfer' => (float) Money::toDecimal($salesTransferCents),
                'sales_other' => (float) Money::toDecimal($salesOtherCents),
                'debt_collections' => (float) Money::toDecimal($debtCollectionsCents),
                'debt_collections_cash' => (float) Money::toDecimal($debtCollectionsCashCents),
                'other_inflows' => (float) Money::toDecimal($cashInMovementsCents),
                'total' => (float) Money::toDecimal($totalInflowsCents),
                'sales_cash_cents' => $salesCashCents,
                'sales_card_cents' => $salesCardCents,
                'sales_transfer_cents' => $salesTransferCents,
                'sales_other_cents' => $salesOtherCents,
                'debt_collections_cents' => $debtCollectionsCents,
                'debt_collections_cash_cents' => $debtCollectionsCashCents,
                'other_inflows_cents' => $cashInMovementsCents,
                'total_cents' => $totalInflowsCents,
            ],
            'outflows' => [
                'expenses_paid' => (float) Money::toDecimal($totalExpensesPaidCents),
                'expenses_paid_cash' => (float) Money::toDecimal($totalExpensesPaidCashCents),
                'cash_out' => (float) Money::toDecimal($cashOutMovementsCents),
                'total' => (float) Money::toDecimal($totalOutflowsCents),
                'expenses_paid_cents' => $totalExpensesPaidCents,
                'expenses_paid_cash_cents' => $totalExpensesPaidCashCents,
                'cash_out_cents' => $cashOutMovementsCents,
                'total_cents' => $totalOutflowsCents,
            ],
            'net_cash_flow' => (float) Money::toDecimal($netCashFlowCents),
            'net_cash_flow_cents' => $netCashFlowCents,
        ];
    }

    /**
     * Preserve the legacy numeric response shape at the presentation boundary.
     * All calculations reaching this helper already use integer cents.
     */
    protected function moneyNumber(int $cents): float
    {
        return (float) Money::toDecimal($cents);
    }
}
