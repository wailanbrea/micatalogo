<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Shop;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class ShopAnalyticsService
{
    public function getMetricsSummary(Shop $shop): array
    {
        $today = now()->toDateString();
        $thirtyDaysAgo = now()->subDays(29)->toDateString();
        $sevenDaysAgo = now()->subDays(6)->toDateString();

        // 1. Métricas agregadas de la tienda
        $allTime = DB::table('shop_daily_metrics')
            ->where('shop_id', $shop->id)
            ->selectRaw('COALESCE(SUM(page_views), 0) as total_views, COALESCE(SUM(whatsapp_clicks), 0) as total_clicks')
            ->first();

        $last30Days = DB::table('shop_daily_metrics')
            ->where('shop_id', $shop->id)
            ->whereBetween('date', [$thirtyDaysAgo, $today])
            ->selectRaw('COALESCE(SUM(page_views), 0) as views, COALESCE(SUM(whatsapp_clicks), 0) as clicks')
            ->first();

        $last7Days = DB::table('shop_daily_metrics')
            ->where('shop_id', $shop->id)
            ->whereBetween('date', [$sevenDaysAgo, $today])
            ->selectRaw('COALESCE(SUM(page_views), 0) as views, COALESCE(SUM(whatsapp_clicks), 0) as clicks')
            ->first();

        $totalViews = (int) ($allTime->total_views ?? 0);
        $totalClicks = (int) ($allTime->total_clicks ?? 0);

        $views30d = (int) ($last30Days->views ?? 0);
        $clicks30d = (int) ($last30Days->clicks ?? 0);

        $views7d = (int) ($last7Days->views ?? 0);
        $clicks7d = (int) ($last7Days->clicks ?? 0);

        // Conversión a WhatsApp en los últimos 30 días
        $conversionRate30d = $views30d > 0
            ? round(($clicks30d / $views30d) * 100, 1)
            : 0.0;

        $conversionRateAllTime = $totalViews > 0
            ? round(($totalClicks / $totalViews) * 100, 1)
            : 0.0;

        // 2. Serie de tiempo de los últimos 30 días para graficar
        $rawDaily = DB::table('shop_daily_metrics')
            ->where('shop_id', $shop->id)
            ->whereBetween('date', [$thirtyDaysAgo, $today])
            ->get()
            ->keyBy('date');

        $dailySeries = [];
        $period = CarbonPeriod::create(Carbon::parse($thirtyDaysAgo), Carbon::parse($today));
        $maxDailyViews = 1;

        foreach ($period as $date) {
            $dateString = $date->toDateString();
            $record = $rawDaily->get($dateString);
            $views = (int) ($record->page_views ?? 0);
            $clicks = (int) ($record->whatsapp_clicks ?? 0);

            if ($views > $maxDailyViews) {
                $maxDailyViews = $views;
            }

            $dailySeries[] = [
                'date' => $dateString,
                'short_date' => $date->format('d M'),
                'day_name' => $date->isoFormat('ddd'),
                'views' => $views,
                'clicks' => $clicks,
            ];
        }

        // 3. Top productos de la tienda con métricas
        $productMetrics = DB::table('product_daily_metrics')
            ->join('products', 'product_daily_metrics.product_id', '=', 'products.id')
            ->where('products.shop_id', $shop->id)
            ->whereNull('products.deleted_at')
            ->select(
                'products.id',
                'products.name',
                'products.slug',
                'products.price',
                'products.currency',
                DB::raw('COALESCE(SUM(product_daily_metrics.page_views), 0) as views_count'),
                DB::raw('COALESCE(SUM(product_daily_metrics.whatsapp_clicks), 0) as clicks_count')
            )
            ->groupBy('products.id', 'products.name', 'products.slug', 'products.price', 'products.currency')
            ->get();

        // Cargar modelos con imágenes para los productos estrella
        $productIds = $productMetrics->pluck('id')->all();
        $productsWithImages = Product::with('primaryImage')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $topByClicks = $productMetrics
            ->sortByDesc('clicks_count')
            ->take(5)
            ->map(function ($item) use ($productsWithImages) {
                $model = $productsWithImages->get($item->id);

                return (object) [
                    'id' => $item->id,
                    'name' => $item->name,
                    'slug' => $item->slug,
                    'price' => $item->price,
                    'currency' => $item->currency,
                    'views_count' => (int) $item->views_count,
                    'clicks_count' => (int) $item->clicks_count,
                    'image_url' => $model?->image_url,
                ];
            })
            ->values();

        $topByViews = $productMetrics
            ->sortByDesc('views_count')
            ->take(5)
            ->map(function ($item) use ($productsWithImages) {
                $model = $productsWithImages->get($item->id);

                return (object) [
                    'id' => $item->id,
                    'name' => $item->name,
                    'slug' => $item->slug,
                    'price' => $item->price,
                    'currency' => $item->currency,
                    'views_count' => (int) $item->views_count,
                    'clicks_count' => (int) $item->clicks_count,
                    'image_url' => $model?->image_url,
                ];
            })
            ->values();

        return [
            'total_views' => $totalViews,
            'total_clicks' => $totalClicks,
            'views_30d' => $views30d,
            'clicks_30d' => $clicks30d,
            'views_7d' => $views7d,
            'clicks_7d' => $clicks7d,
            'conversion_rate_30d' => $conversionRate30d,
            'conversion_rate_all_time' => $conversionRateAllTime,
            'daily_series' => $dailySeries,
            'max_daily_views' => $maxDailyViews,
            'top_by_clicks' => $topByClicks,
            'top_by_views' => $topByViews,
            'total_products_count' => $shop->products()->count(),
            'active_products_count' => $shop->products()->where('availability_status', 'available')->where('moderation_status', 'active')->count(),
        ];
    }
}
