<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\V1\PosSaleController;
use App\Models\Shop;
use App\Services\BusinessPresentationService;
use App\Services\CustomerAccountService;
use App\Services\InventoryService;
use App\Services\PaymentService;
use App\Services\ShopOperationalSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SellerPosController extends Controller
{
    public function index(Shop $shop, BusinessPresentationService $presentation): View
    {
        $products = $shop->products()
            ->where(function ($query): void {
                $query->whereHas('inventory', fn ($inventory) => $inventory->where('track_inventory', true))
                    ->orWhere('sale_unit', 'service')
                    ->orWhere('is_combo', true);
            })
            ->with(['inventory', 'images', 'primaryImage', 'sourceProduct.inventory', 'shopCategory', 'globalCategory', 'attributeValues.attributeDefinition', 'comboItems.component.inventory'])
            ->orderBy('name')
            ->get()
            ->map(function ($product): array {
                $sourceInventory = $product->sourceProduct?->inventory;
                $sourceAvailableMl = $product->isDecant() && $sourceInventory
                    ? ($sourceInventory->available_ml ?? (($sourceInventory->stock_quantity ?? 0) * (int) ($product->sourceProduct?->volume_ml ?? 0)))
                    : null;
                $stock = $product->isService()
                    ? null
                    : ($product->isCombo()
                    ? $product->comboAvailableQuantity()
                    : ($product->isDecant() && $product->volume_ml
                    ? intdiv(max(0, (int) $sourceAvailableMl), (int) $product->volume_ml)
                    : (int) ($product->inventory?->stock_quantity ?? 0)));

                return [
                'id' => $product->public_id,
                'name' => $product->name,
                'code' => $product->product_code,
                'brand' => $product->brand,
                'attributes' => $product->attributeValues
                    ->map(fn ($attribute) => $attribute->value)
                    ->filter()
                    ->values()
                    ->all(),
                'price' => (float) $product->currentPrice(),
                'wholesale_price' => $product->wholesale_price === null ? null : (float) $product->wholesale_price,
                'stock' => $stock,
                'sale_unit' => $product->sale_unit ?: 'unit',
                'sale_unit_label' => $product->isDecant() && $product->volume_ml
                    ? 'Decant · '.$product->volume_ml.' ml'
                    : $product->saleUnitLabel(),
                'volume_ml' => $product->volume_ml,
                'source_product_id' => $product->sourceProduct?->public_id,
                'source_product_name' => $product->sourceProduct?->name,
                'source_available_ml' => $sourceAvailableMl,
                'is_decant' => $product->isDecant(),
                'is_service' => $product->isService(),
                'is_combo' => $product->isCombo(),
                'combo_items' => $product->isCombo() ? $product->comboItems->map(fn ($item): array => [
                    'product_id' => $item->component?->public_id,
                    'name' => $item->component?->name,
                    'quantity' => (int) $item->quantity,
                ])->values()->all() : [],
                'image_url' => $product->image_url,
                'category' => $product->shopCategory?->name ?? $product->globalCategory?->name ?? 'Sin categoría',
                ];
            })
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
            'paymentAccounts' => $shop->paymentAccounts()->where('is_active', true)->get(),
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
        PaymentService $paymentService,
        ShopOperationalSettingsService $operational
    ): RedirectResponse {
        $response = $posSaleController->store(
            $request,
            $shop,
            $inventoryService,
            $customerAccountService,
            $paymentService,
            $operational,
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
            ->with('status', 'Venta '.($payload['invoice_number'] ?? '').' registrada correctamente.')
            ->with('bottle_recovery', $payload['bottle_recovery'] ?? []);
    }
}
