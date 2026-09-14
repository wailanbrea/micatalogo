<?php

namespace App\Http\Controllers;

use App\Enums\ProductModerationStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Services\MetricRecordingService;
use Illuminate\Http\RedirectResponse;

class WhatsAppRedirectController extends Controller
{
    public function shop(Shop $shop, MetricRecordingService $metricService): RedirectResponse
    {
        abort_unless($shop->status === 'active', 404);

        $metricService->recordShopWhatsAppClick($shop);

        $greeting = "Hola {$shop->name}, vi su catálogo en MiCatalogo y quiero hacer una consulta.";
        $url = "https://wa.me/{$shop->whatsapp_country_code}{$shop->whatsapp_number}?text=".rawurlencode($greeting);

        return redirect()->away($url);
    }

    public function product(Shop $shop, Product $product, MetricRecordingService $metricService): RedirectResponse
    {
        abort_unless(
            $shop->status === 'active'
            && $product->shop_id === $shop->id
            && $product->moderation_status === ProductModerationStatus::Active,
            404
        );

        $metricService->recordProductWhatsAppClick($product);

        $productUrl = route('products.show', [$shop, $product]);
        $message = "Hola, me interesa \"{$product->name}\" que vi en MiCatalogo:\n{$productUrl}";
        $url = "https://wa.me/{$shop->whatsapp_country_code}{$shop->whatsapp_number}?text=".rawurlencode($message);

        return redirect()->away($url);
    }
}
