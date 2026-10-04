<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Shop;
use App\Services\InventoryService;
use App\Services\PlanLimitsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class SellerInventoryController extends Controller
{
    public function index(Request $request, Shop $shop, InventoryService $inventoryService, PlanLimitsService $limits): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'stock' => ['nullable', 'in:all,low,out,available,untracked'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'cost_min' => ['nullable', 'numeric', 'min:0'],
            'cost_max' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', 'in:product,stock,sold,price,cost'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $summary = $inventoryService->getShopInventorySummary($shop);
        $filters = [
            'q' => trim((string) ($validated['q'] ?? '')),
            'stock' => $validated['stock'] ?? 'all',
            'price_min' => $validated['price_min'] ?? null,
            'price_max' => $validated['price_max'] ?? null,
            'cost_min' => $validated['cost_min'] ?? null,
            'cost_max' => $validated['cost_max'] ?? null,
            'sort' => $validated['sort'] ?? 'product',
            'direction' => $validated['direction'] ?? 'asc',
        ];

        $summary['all_products'] = $summary['all_products']
            ->filter(function (Product $product) use ($filters): bool {
                $inventory = $product->inventory;
                $isControlled = $inventory?->track_inventory ?? false;

                if ($filters['q'] !== '' && ! str_contains(strtolower($product->name), strtolower($filters['q']))) {
                    return false;
                }

                if (match ($filters['stock']) {
                    'low' => ! $isControlled || ! $inventory->isLowStock($product),
                    'out' => ! $isControlled || $inventory->stock_quantity > 0,
                    'available' => ! $isControlled || $inventory->stock_quantity <= 0 || $inventory->isLowStock($product),
                    'untracked' => $isControlled,
                    default => false,
                }) {
                    return false;
                }

                $price = (float) $product->price;
                $cost = $isControlled && $inventory->cost_price !== null ? (float) $inventory->cost_price : null;

                return ($filters['price_min'] === null || $price >= (float) $filters['price_min'])
                    && ($filters['price_max'] === null || $price <= (float) $filters['price_max'])
                    && ($filters['cost_min'] === null || ($cost !== null && $cost >= (float) $filters['cost_min']))
                    && ($filters['cost_max'] === null || ($cost !== null && $cost <= (float) $filters['cost_max']));
            })
            ->sortBy(function (Product $product) use ($filters): float|string {
                $inventory = $product->inventory;

                return match ($filters['sort']) {
                    'stock' => $inventory?->track_inventory ? $inventory->stock_quantity : -1,
                    'sold' => $inventory?->track_inventory ? $inventory->sold_quantity : -1,
                    'price' => (float) $product->price,
                    'cost' => $inventory?->cost_price !== null ? (float) $inventory->cost_price : -1,
                    default => strtolower($product->name),
                };
            }, SORT_REGULAR, $filters['direction'] === 'desc')
            ->values();

        return view('seller.inventory.index', [
            'shop' => $shop,
            'summary' => $summary,
            'filters' => $filters,
            'quota' => $limits->shopQuota($shop),
        ]);
    }

    public function recordSale(Request $request, Shop $shop, Product $product, InventoryService $inventoryService): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $inventoryService->recordSale($product, (int) $validated['quantity'], $validated['notes'] ?? null, $request->user()->id);

            return back()->with('status', "Venta de {$validated['quantity']} unidad(es) de {$product->name} registrada exitosamente.");
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }
    }

    public function checkout(Request $request, Shop $shop, InventoryService $inventoryService): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $items = collect($validated['items'])
            ->keyBy('product_id')
            ->map(fn (array $item) => (int) $item['quantity']);
        $products = $shop->products()->whereIn('id', $items->keys())->get()->keyBy('id');

        if ($products->count() !== $items->count()) {
            return response()->json(['message' => 'Uno o más productos no pertenecen a esta tienda.'], 422);
        }

        try {
            $inventoryService->recordCartSales(
                $items->map(fn (int $quantity, int $productId) => [
                    'product' => $products->get($productId),
                    'quantity' => $quantity,
                ])->values()->all(),
                $request->user()->id,
            );

            return response()->json([
                'redirect' => route('seller.shops.inventory.index', $shop),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function recordRestock(Request $request, Shop $shop, Product $product, InventoryService $inventoryService): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $inventoryService->recordRestock($product, (int) $validated['quantity'], $validated['notes'] ?? null, $request->user()->id);

            return back()->with('status', "Se repusieron {$validated['quantity']} unidad(es) en el inventario de {$product->name}.");
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }
    }

    public function adjustStock(Request $request, Shop $shop, Product $product, InventoryService $inventoryService): RedirectResponse
    {
        $validated = $request->validate([
            'new_stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $inventoryService->adjustStock($product, (int) $validated['new_stock'], $validated['notes'] ?? null, $request->user()->id);

            return back()->with('status', "El stock de {$product->name} fue ajustado a {$validated['new_stock']} unidades.");
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['new_stock' => $e->getMessage()]);
        }
    }

    public function movements(Shop $shop, Product $product): View
    {
        $movements = $product->inventoryMovements()->with('invoice')->paginate(25);

        return view('seller.inventory.movements', [
            'shop' => $shop,
            'product' => $product,
            'movements' => $movements,
        ]);
    }
}
