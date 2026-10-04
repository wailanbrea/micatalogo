<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\InventoryService;
use App\Services\ProductPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerBusinessController extends Controller
{
    public function index(Request $request, Shop $shop)
    {
        $range = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = $range['from'] ?? now()->startOfMonth()->toDateString();
        $to = $range['to'] ?? now()->toDateString();
        $sales = Invoice::where('shop_id', $shop->id)->whereDate('issued_at', '>=', $from)->whereDate('issued_at', '<=', $to);
        $products = DB::table('invoice_items')->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.shop_id', $shop->id)->whereDate('issued_at', '>=', $from)->whereDate('issued_at', '<=', $to)
            ->selectRaw('product_id, product_name, SUM(quantity) as units, SUM(line_total - invoice_items.tax - general_discount_cents / 100.0) as revenue, SUM(total_cost_cents) / 100.0 as known_cost, SUM(CASE WHEN total_cost_cents IS NULL THEN 1 ELSE 0 END) as unknown_lines')
            ->groupBy('product_id', 'product_name')->orderByDesc('revenue')->get();
        $inventory = app(InventoryService::class)->getShopInventorySummary($shop);

        return view('seller.business', [
            'shop' => $shop, 'from' => $from, 'to' => $to, 'products' => $products,
            'today' => Invoice::where('shop_id', $shop->id)->whereDate('issued_at', today())->sum('total'),
            'total' => (clone $sales)->sum('total'), 'discount' => (clone $sales)->sum('discount'),
            'receivable' => $shop->customers()->sum('balance'), 'inventory' => $inventory,
            'orders' => $shop->orders()->with('items')->whereNull('invoice_id')->latest()->paginate(15),
            'rules' => DB::table('product_price_rules')->whereIn('product_id', $shop->products()->select('id'))->get()->keyBy('product_id'),
            'lots' => \App\Models\InventoryLot::whereIn('product_id', $shop->products()->select('id'))->orderBy('received_at')->get(),
        ]);
    }

    public function rule(Request $request, Shop $shop, Product $product, ProductPricingService $pricing)
    {
        $pricing->requirePro($product);
        $data = $request->validate(['margin_percent' => ['required', 'numeric', 'min:0', 'max:95'],
            'round_step' => ['required', 'decimal:0,2', 'min:0.01', 'max:10000'], 'auto_increase' => ['sometimes', 'boolean']]);
        DB::transaction(function () use ($product, $data) {
            Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            DB::table('product_price_rules')->updateOrInsert(['product_id' => $product->id], [
                'margin_percent' => $data['margin_percent'], 'round_step_cents' => (int) round((float) $data['round_step'] * 100),
                'auto_increase' => $data['auto_increase'] ?? false, 'pending_price' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return back()->with('status', 'Regla guardada. Se evaluará al recibir mercancía.');
    }

    public function approve(Request $request, Shop $shop, Product $product, ProductPricingService $pricing)
    {
        $pricing->requirePro($product);
        $data = $request->validate(['expected_price' => ['required', 'decimal:0,2', 'min:0']]);
        DB::transaction(function () use ($request, $product, $pricing, $data) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $rule = DB::table('product_price_rules')->where('product_id', $product->id)->lockForUpdate()->first();
            if (! $rule || $rule->pending_price === null || (float) $rule->pending_price !== (float) $data['expected_price']) {
                throw ValidationException::withMessages(['pricing' => 'La propuesta cambió; revisa el precio antes de aprobar.']);
            }
            $pricing->apply($product, (float) $rule->pending_price, $request->user()->id, 'approved');
            DB::table('product_price_rules')->where('id', $rule->id)->update(['pending_price' => null, 'updated_at' => now()]);
        });

        return back()->with('status', 'Precio aprobado.');
    }

    public function confirm(Request $request, Shop $shop, Order $order, InventoryService $inventory)
    {
        abort_unless($order->shop_id === $shop->id, 404);
        DB::transaction(function () use ($request, $order, $shop, $inventory) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->invoice_id !== null) {
                return;
            }
            $lines = $order->items()->get()->map(function ($item) use ($shop) {
                $product = $shop->products()->find($item->product_id);
                if (! $product) {
                    throw ValidationException::withMessages(['order' => 'Un producto del pedido ya no está disponible.']);
                }

                return ['product' => $product, 'quantity' => $item->quantity, 'unit_price' => (float) $item->unit_price];
            })->all();
            $movements = $inventory->recordCartSales($lines, $request->user()->id);
            $order->update(['status' => 'confirmed', 'invoice_id' => $movements[0]->invoice_id]);
        });

        return back()->with('status', 'Pedido confirmado como venta.');
    }
}
