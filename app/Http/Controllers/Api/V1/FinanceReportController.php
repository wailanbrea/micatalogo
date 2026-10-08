<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\BusinessDashboardService;
use App\Services\DailyCloseService;
use App\Services\SellerMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceReportController extends Controller
{
    public function summary(Request $request, Shop $shop, BusinessDashboardService $service, SellerMenuService $menus): JsonResponse
    {
        abort_unless($menus->canManage($shop, $request->user()) || $request->user()->canSellAtShop($shop) || $request->user()->isActiveShopAccountant($shop), 403);
        abort_unless(in_array('finance', $menus->visibleForUser($shop, $request->user()), true), 403);

        $from = $request->query('from');
        $to = $request->query('to');
        $sort = (string) $request->query('sort', 'profit');
        $direction = (string) $request->query('direction', 'desc');

        $summary = $service->getSummary($shop, $from, $to, $sort, $direction);

        // Mask sensitive costs/profit if seller cannot view finance
        $canViewSensitiveFinance = $menus->canManage($shop, $request->user())
            || $request->user()->isAdmin()
            || $request->user()->ownsShop($shop)
            || in_array('finance', $menus->visibleForUser($shop, $request->user()), true);

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
        abort_unless(($request->user()->isAdmin() || $request->user()->canSellAtShop($shop) || $request->user()->isActiveShopAccountant($shop)) && in_array('finance', $menus->visibleForUser($shop, $request->user()), true), 403);

        $from = $request->query('from');
        $to = $request->query('to');

        $summary = $service->getSummary($shop, $from, $to);

        return response()->json($summary['income_statement']);
    }

    public function cashFlow(Request $request, Shop $shop, BusinessDashboardService $service, SellerMenuService $menus): JsonResponse
    {
        abort_unless(($request->user()->isAdmin() || $request->user()->canSellAtShop($shop) || $request->user()->isActiveShopAccountant($shop)) && in_array('finance', $menus->visibleForUser($shop, $request->user()), true), 403);

        $from = $request->query('from');
        $to = $request->query('to');

        $summary = $service->getSummary($shop, $from, $to);

        return response()->json($summary['cash_flow']);
    }

    public function dayClose(Request $request, Shop $shop, DailyCloseService $service, SellerMenuService $menus): JsonResponse
    {
        $this->authorizeDayClose($request, $shop, $menus);

        $date = $request->query('date', now()->toDateString());
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return response()->json($service->calculate($shop, $date));
    }

    public function closeDay(Request $request, Shop $shop, DailyCloseService $service, SellerMenuService $menus): JsonResponse
    {
        $this->authorizeDayClose($request, $shop, $menus, true);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'counted_cash' => ['nullable', 'decimal:0,2', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $closure = $service->close(
                $shop,
                $request->user(),
                $validated['date'],
                $validated['counted_cash'] ?? null,
                $validated['notes'] ?? null
            );

            $calculation = $service->calculate($shop, $validated['date']);
            $calculation['closure'] = $service->serializeClosure($closure);

            return response()->json($calculation);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }
    }

    private function authorizeDayClose(Request $request, Shop $shop, SellerMenuService $menus, bool $mutate = false): void
    {
        $user = $request->user();
        $visible = $menus->visibleForUser($shop, $user);
        $canRead = ($menus->canManage($shop, $user) || $user->canSellAtShop($shop) || $user->isActiveShopAccountant($shop))
            && in_array('finance', $visible, true);

        if (! $mutate) {
            abort_unless($canRead, 403);

            return;
        }

        // Accountants are intentionally read-only. A seller may close only
        // when the owner explicitly delegated the day_close menu; owners and
        // managers retain the operational control path.
        $canMutate = $menus->canManage($shop, $user)
            || ($user->canSellAtShop($shop) && in_array('day_close', $visible, true));

        abort_unless($canMutate, 403);
    }
}
