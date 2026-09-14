<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\QrCodeSvgService;
use App\Services\ShopAnalyticsService;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SellerShopMetricController extends Controller
{
    public function index(Shop $shop, ShopAnalyticsService $analyticsService, QrCodeSvgService $qrCodeService): View
    {
        $metrics = $analyticsService->getMetricsSummary($shop);
        $shopUrl = route('shops.show', $shop);
        $qrSvg = $qrCodeService->generateSvg($shopUrl, 240);

        return view('seller.shops.metrics', [
            'shop' => $shop,
            'metrics' => $metrics,
            'shopUrl' => $shopUrl,
            'qrSvg' => $qrSvg,
        ]);
    }

    public function downloadQr(Shop $shop, QrCodeSvgService $qrCodeService): Response
    {
        $shopUrl = route('shops.show', $shop);
        $svg = $qrCodeService->generateSvg($shopUrl, 500);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="qr-'.$shop->slug.'.svg"',
        ]);
    }

    public function print(Shop $shop, QrCodeSvgService $qrCodeService): View
    {
        $shopUrl = route('shops.show', $shop);
        $qrSvg = $qrCodeService->generateSvg($shopUrl, 320);

        return view('seller.shops.qr-print', [
            'shop' => $shop,
            'shopUrl' => $shopUrl,
            'qrSvg' => $qrSvg,
        ]);
    }
}
