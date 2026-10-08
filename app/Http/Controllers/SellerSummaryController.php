<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Shop;
use App\Services\SellerMenuService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SellerSummaryController extends Controller
{
    public function api(Request $request, Shop $shop, SellerMenuService $menus): JsonResponse
    {
        $data = $this->index($request, $shop, $menus)->getData();

        return response()->json([
            'period' => $data['period'],
            'period_label' => $data['periodLabel'],
            'metrics' => $data['metrics'],
            'chart' => $data['chart']->values(),
            'sales' => $data['sales']->getCollection()->map(fn (Invoice $sale): array => [
                'invoice_number' => $sale->invoice_number,
                'customer' => $sale->customer?->name ?? 'Consumidor final',
                'date' => $sale->issued_at->format('d/m/Y h:i A'),
                'total' => Money::toCents($sale->total),
                'commission' => Money::toCents($sale->commission_amount),
                'status' => $sale->status,
            ])->values(),
            'has_more_sales' => $data['sales']->hasMorePages(),
        ]);
    }

    public function index(Request $request, Shop $shop, SellerMenuService $menus): View
    {
        $visibleMenus = $menus->visibleForUser($shop, $request->user());
        $canManage = $menus->canManage($shop, $request->user());
        $period = $request->validate(['period' => ['sometimes', 'in:today,week,month']])['period'] ?? 'today';
        $from = match ($period) {
            'week' => today()->subDays(6),
            'month' => today()->startOfMonth(),
            default => today(),
        };
        $periodLabel = match ($period) {
            'week' => 'Últimos 7 días',
            'month' => 'Este mes',
            default => 'Hoy',
        };
        $base = Invoice::query()->where('shop_id', $shop->id)
            ->when(! $canManage, fn ($query) => $query->where('salesperson_id', $request->user()->id))
            ->whereNotIn('status', ['cancelled', 'void']);
        $periodSales = (clone $base)->whereBetween('issued_at', [$from, today()->endOfDay()]);
        $totals = (clone $periodSales)->selectRaw('COUNT(*) AS sales_count, COALESCE(SUM(total), 0) AS sales_total, COALESCE(SUM(commission_amount), 0) AS commission_total')->first();
        $metrics = [
            'count' => (int) $totals->sales_count,
            'total' => Money::toCents((string) $totals->sales_total),
            'commission' => Money::toCents((string) $totals->commission_total),
        ];
        $metrics['average'] = $metrics['count'] ? intdiv($metrics['total'], $metrics['count']) : 0;
        $sales = (clone $periodSales)->with('customer')->latest('issued_at')->paginate(8)->withQueryString();
        $daily = (clone $base)->whereBetween('issued_at', [today()->subDays(6), today()->endOfDay()])
            ->selectRaw('DATE(issued_at) AS day, COALESCE(SUM(total), 0) AS sales_total')
            ->groupByRaw('DATE(issued_at)')->get()->keyBy('day');
        $chart = collect(range(6, 0))->map(function (int $daysAgo) use ($daily): array {
            $date = today()->subDays($daysAgo);

            return ['label' => $date->format('d/m'), 'total' => Money::toCents((string) ($daily->get($date->toDateString())?->sales_total ?? '0'))];
        });

        return view('seller.summary', compact('shop', 'visibleMenus', 'canManage', 'sales', 'period', 'periodLabel', 'metrics', 'chart'));
    }
}
