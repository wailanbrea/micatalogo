<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\V1\PosSaleController;
use App\Models\Shop;
use App\Services\BusinessPresentationService;
use App\Services\CustomerAccountService;
use App\Services\InventoryService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SellerPosController extends Controller
{
    public function index(Shop $shop, BusinessPresentationService $presentation): View
    {
        $products = $shop->products()
            ->whereHas('inventory', fn ($query) => $query->where('track_inventory', true))
            ->with(['inventory', 'images', 'primaryImage', 'sourceProduct.inventory', 'shopCategory', 'globalCategory'])
            ->orderBy('name')
            ->get()
            ->map(fn ($product): array => [
                'id' => $product->public_id,
                'name' => $product->name,
                'code' => $product->product_code,
                'price' => (float) $product->currentPrice(),
                'wholesale_price' => $product->wholesale_price === null ? null : (float) $product->wholesale_price,
                'stock' => (int) $product->inventory->stock_quantity,
                'sale_unit' => $product->sale_unit ?: 'unit',
                'sale_unit_label' => $product->isDecant() && $product->volume_ml
                    ? 'Decant · '.$product->volume_ml.' ml'
                    : $product->saleUnitLabel(),
                'volume_ml' => $product->volume_ml,
                'source_product_id' => $product->sourceProduct?->public_id,
                'image_url' => $product->image_url,
                'category' => $product->shopCategory?->name ?? $product->globalCategory?->name ?? 'Sin categoría',
            ])
            ->values();

        $lowStockCount = $shop->products()
            ->whereHas('inventory', function ($query): void {
                $query->where('track_inventory', true)
                    ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
                    ->where('stock_quantity', '>', 0);
            })
            ->count();

        $customers = $shop->customers()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'public_id', 'name', 'phone', 'balance', 'credit_limit'])
            ->map(fn ($customer): array => [
                'id' => $customer->public_id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'balance' => (float) $customer->balance,
                'credit_limit' => (float) $customer->credit_limit,
            ])
            ->values();

        return view('seller.pos.index', [
            'shop' => $shop,
            'products' => $products,
            'customers' => $customers,
            'presentation' => $presentation->resolve($shop),
            'paymentMethods' => config('catalog.payment_methods', []),
            'clientSaleUuid' => old('client_sale_uuid') ?: (string) Str::uuid(),
            'posSummary' => [
                'available_products' => $products->where('stock', '>', 0)->count(),
                'low_stock' => $lowStockCount,
            ],
        ]);
    }

    public function store(
        Request $request,
        Shop $shop,
        PosSaleController $posSaleController,
        InventoryService $inventoryService,
        CustomerAccountService $customerAccountService,
        PaymentService $paymentService
    ): RedirectResponse {
        $response = $posSaleController->store(
            $request,
            $shop,
            $inventoryService,
            $customerAccountService,
            $paymentService,
        );

        $payload = $response->getData(true);
        if ($response->getStatusCode() >= 400) {
            $errors = $payload['errors'] ?? [];
            if (! is_array($errors) || $errors === []) {
                $errors = ['sale' => $payload['message'] ?? 'No se pudo registrar la venta.'];
            }

            return back()->withInput()->withErrors($errors);
        }

        return redirect()
            ->route('seller.shops.pos', $shop)
            ->with('status', 'Venta '.($payload['invoice_number'] ?? '').' registrada correctamente.');
    }
}
