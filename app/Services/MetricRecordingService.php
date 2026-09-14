<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MetricRecordingService
{
    public function recordShopPageView(Shop $shop, ?Request $request = null): void
    {
        if ($this->isCrawler($request)) {
            return;
        }

        $date = now()->toDateString();

        DB::table('shop_daily_metrics')->upsert(
            [
                'shop_id' => $shop->id,
                'date' => $date,
                'page_views' => 1,
                'whatsapp_clicks' => 0,
            ],
            ['shop_id', 'date'],
            [
                'page_views' => DB::raw('page_views + 1'),
            ]
        );
    }

    public function recordProductPageView(Product $product, ?Request $request = null): void
    {
        if ($this->isCrawler($request)) {
            return;
        }

        $date = now()->toDateString();

        DB::table('product_daily_metrics')->upsert(
            [
                'product_id' => $product->id,
                'date' => $date,
                'page_views' => 1,
                'whatsapp_clicks' => 0,
            ],
            ['product_id', 'date'],
            [
                'page_views' => DB::raw('page_views + 1'),
            ]
        );

        $this->recordShopPageView($product->shop, $request);
    }

    public function recordShopWhatsAppClick(Shop $shop): void
    {
        $date = now()->toDateString();

        DB::table('shop_daily_metrics')->upsert(
            [
                'shop_id' => $shop->id,
                'date' => $date,
                'page_views' => 0,
                'whatsapp_clicks' => 1,
            ],
            ['shop_id', 'date'],
            [
                'whatsapp_clicks' => DB::raw('whatsapp_clicks + 1'),
            ]
        );
    }

    public function recordProductWhatsAppClick(Product $product): void
    {
        $date = now()->toDateString();

        DB::table('product_daily_metrics')->upsert(
            [
                'product_id' => $product->id,
                'date' => $date,
                'page_views' => 0,
                'whatsapp_clicks' => 1,
            ],
            ['product_id', 'date'],
            [
                'whatsapp_clicks' => DB::raw('whatsapp_clicks + 1'),
            ]
        );

        $this->recordShopWhatsAppClick($product->shop);
    }

    private function isCrawler(?Request $request): bool
    {
        if (! $request) {
            return false;
        }

        $userAgent = strtolower((string) $request->header('User-Agent'));

        $crawlers = [
            'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'facebookexternalhit',
            'telegrambot', 'twitterbot', 'pinterest', 'pingdom',
        ];

        foreach ($crawlers as $crawler) {
            if (str_contains($userAgent, $crawler)) {
                return true;
            }
        }

        return false;
    }
}
