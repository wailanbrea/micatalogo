<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Shop;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class SellerInventoryController extends Controller
{
    public function index(Shop $shop, InventoryService $inventoryService): View
    {
        $summary = $inventoryService->getShopInventorySummary($shop);

        return view('seller.inventory.index', [
            'shop' => $shop,
            'summary' => $summary,
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
        $movements = $product->inventoryMovements()->paginate(25);

        return view('seller.inventory.movements', [
            'shop' => $shop,
            'product' => $product,
            'movements' => $movements,
        ]);
    }
}
