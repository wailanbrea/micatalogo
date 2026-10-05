<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\BusinessDashboardService;
use App\Services\SellerMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceReportController extends Controller
{
    public function summary(Request $request, Shop $shop, BusinessDashboardService $service, SellerMenuService $menus): JsonResponse
    {
        abort_unless($menus->canManage($shop, $request->user()) || $request->user()->canSellAtShop($shop), 403);
        abort_unless(in_array('finance', $menus->forUser($shop, $request->user()), true), 403);

        $from = $request->query('from');
        $to = $request->query('to');
        $sort = (string) $request->query('sort', 'profit');
        $direction = (string) $request->query('direction', 'desc');

        $summary = $service->getSummary($shop, $from, $to, $sort, $direction);

        // Mask sensitive costs/profit if seller cannot view finance
        $canViewSensitiveFinance = $menus->canManage($shop, $request->user())
            || $request->user()->isAdmin()
            || $request->user()->ownsShop($shop)
            || in_array('finance', $menus->forUser($shop, $request->user()), true);

        if (! $canViewSensitiveFinance) {
            $summary['period']['fifo_cogs'] = null;
            $summary['period']['gross_profit'] = null;
            $summary['period']['gross_margin_percent'] = null;
            $summary['period']['operating_profit'] = null;
            $summary['income_statement'] = null;
            foreach ($summary['profitability'] as &$item) {
                $item['cost'] = null;
                $item['gross_profit'] = null;
                $item['margin_percent'] = null;
            }
            $summary['current_state']['aging']['invoice_details'] = [];
        }

        return response()->json($summary);
    }

    public function incomeStatement(Request $request, Shop $shop, BusinessDashboardService $service, SellerMenuService $menus): JsonResponse
    {
        abort_unless(($request->user()->isAdmin() || $request->user()->canSellAtShop($shop)) && in_array('finance', $menus->forUser($shop, $request->user()), true), 403);

        $from = $request->query('from');
        $to = $request->query('to');

        $summary = $service->getSummary($shop, $from, $to);

        return response()->json($summary['income_statement']);
    }

    public function cashFlow(Request $request, Shop $shop, BusinessDashboardService $service, SellerMenuService $menus): JsonResponse
    {
        abort_unless(($request->user()->isAdmin() || $request->user()->canSellAtShop($shop)) && in_array('finance', $menus->forUser($shop, $request->user()), true), 403);

        $from = $request->query('from');
        $to = $request->query('to');

        $summary = $service->getSummary($shop, $from, $to);

        return response()->json($summary['cash_flow']);
    }
}
