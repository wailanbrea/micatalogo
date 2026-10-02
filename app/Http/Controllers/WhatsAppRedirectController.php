<?php

namespace App\Http\Controllers;

use App\Enums\ProductModerationStatus;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Shop;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\MetricRecordingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppRedirectController extends Controller
{
    public function shop(Request $request, Shop $shop, MetricRecordingService $metricService): RedirectResponse|Response
    {
        abort_unless($shop->status === 'active', 404);

        if ($request->filled('invoice')) {
            abort_unless($request->hasValidSignature(), 403);

            $invoice = Invoice::query()
                ->where('shop_id', $shop->id)
                ->with(['shop', 'items'])
                ->findOrFail($request->integer('invoice'));

            return Pdf::loadView('invoices.pdf', compact('invoice'))
                ->setPaper('a4')
                ->stream($invoice->invoice_number.'.pdf');
        }

        $metricService->recordShopWhatsAppClick($shop);

        $requestedProducts = $request->input('products', []);
        $requestedProducts = is_array($requestedProducts)
            ? collect($requestedProducts)->filter(fn ($publicId) => is_string($publicId))->take(20)->values()
            : collect();
        $selectedProducts = $shop->products()
            ->where('moderation_status', ProductModerationStatus::Active)
            ->whereIn('public_id', $requestedProducts->all())
            ->get()
            ->keyBy('public_id');

        if ($selectedProducts->isNotEmpty()) {
            $message = "Hola {$shop->name}, me interesan estos productos de su catálogo en MiCatalogo:";
            $cartUrl = route('shops.show', $shop).'?cart='.rawurlencode($requestedProducts->implode(','));
            foreach ($requestedProducts as $publicId) {
                $product = $selectedProducts->get($publicId);
                if ($product) {
                    $message .= "\n- {$product->name}: ".route('products.show', [$shop, $product]);
                }
            }
            $message .= "\n\nVer carrito y total: {$cartUrl}";
        } else {
            $message = "Hola {$shop->name}, vi su catálogo en MiCatalogo y quiero hacer una consulta.";
        }

        $url = "https://wa.me/{$shop->whatsapp_country_code}{$shop->whatsapp_number}?text=".rawurlencode($message);

        return redirect()->away($url);
    }

    public function product(Request $request, Shop $shop, Product $product, MetricRecordingService $metricService): RedirectResponse
    {
        abort_unless(
            $shop->status === 'active'
            && $product->shop_id === $shop->id
            && $product->moderation_status === ProductModerationStatus::Active,
            404
        );

        $metricService->recordProductWhatsAppClick($product);
        $product->loadMissing(['inventory', 'sourceProduct.inventory']);

        $productUrl = route('products.show', [$shop, $product]);
        $quantity = max(1, min(10000, $request->integer('quantity', 1)));
        $message = "Hola, me interesa \"{$product->name}\" que vi en MiCatalogo:\n{$productUrl}";

        if ($product->isDecant()) {
            $quantity = min($quantity, max(1, (int) ($product->inventory?->stock_quantity ?? 1)));
            $totalMl = $quantity * (int) $product->volume_ml;
            $message .= "\nCantidad solicitada: {$quantity} decant(s) de {$product->volume_ml} ml ({$totalMl} ml total).";
        }

        $url = "https://wa.me/{$shop->whatsapp_country_code}{$shop->whatsapp_number}?text=".rawurlencode($message);

        return redirect()->away($url);
    }
}
