<?php

namespace App\Http\Controllers;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\ProductModerationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Report;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $storageBytes = (int) ProductImage::sum('size_bytes');
        $thirtyDaysAgo = now()->subDays(29)->toDateString();
        $today = now()->toDateString();

        // Métricas globales de la plataforma
        $global30d = DB::table('shop_daily_metrics')
            ->whereBetween('date', [$thirtyDaysAgo, $today])
            ->selectRaw('COALESCE(SUM(page_views), 0) as views, COALESCE(SUM(whatsapp_clicks), 0) as clicks')
            ->first();

        $globalAllTime = DB::table('shop_daily_metrics')
            ->selectRaw('COALESCE(SUM(page_views), 0) as views, COALESCE(SUM(whatsapp_clicks), 0) as clicks')
            ->first();

        $views30d = (int) ($global30d->views ?? 0);
        $clicks30d = (int) ($global30d->clicks ?? 0);
        $totalViews = (int) ($globalAllTime->views ?? 0);
        $totalClicks = (int) ($globalAllTime->clicks ?? 0);

        $conversionRate30d = $views30d > 0
            ? round(($clicks30d / $views30d) * 100, 1)
            : 0.0;

        $stats = [
            'shops' => [
                'total' => Shop::count(),
                'active' => Shop::where('status', 'active')->count(),
                'suspended' => Shop::where('status', 'suspended')->count(),
            ],
            'products' => [
                'total' => Product::count(),
                'active' => Product::where('moderation_status', ProductModerationStatus::Active)->count(),
                'suspended' => Product::where('moderation_status', ProductModerationStatus::Suspended)->count(),
                'out_of_stock' => Product::where('availability_status', ProductAvailabilityStatus::OutOfStock)->count(),
            ],
            'users' => [
                'total' => User::count(),
                'active' => User::where('status', UserStatus::Active)->count(),
                'suspended' => User::where('status', UserStatus::Suspended)->count(),
                'admins' => User::where('role', UserRole::Admin)->count(),
            ],
            'traffic' => [
                'views_30d' => $views30d,
                'clicks_30d' => $clicks30d,
                'total_views' => $totalViews,
                'total_clicks' => $totalClicks,
                'conversion_rate_30d' => $conversionRate30d,
            ],
            'reports' => [
                'total' => Report::count(),
                'open' => Report::where('status', 'open')->count(),
                'resolved' => Report::where('status', 'resolved')->count(),
            ],
            'media' => [
                'total_images' => ProductImage::count(),
                'storage_bytes' => $storageBytes,
                'storage_mb' => round($storageBytes / (1024 * 1024), 2),
                'failed_images' => ProductImage::where('processing_status', 'failed')->count(),
                'products_without_images' => Product::whereDoesntHave('images')->count(),
            ],
        ];

        $recentReports = Report::with('reportable')
            ->where('status', 'open')
            ->latest('id')
            ->take(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentReports'));
    }
}
