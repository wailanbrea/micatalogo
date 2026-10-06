<?php

namespace App\Http\Controllers;

use App\Models\InventoryLot;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\BusinessDashboardService;
use App\Services\InventoryService;
use App\Services\ProductPricingService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SellerBusinessController extends Controller
{
    public function index(
        Request $request,
        Shop $shop,
        ?BusinessDashboardService $dashboardService = null,
        ?InventoryService $inventoryService = null
    ) {
        $dashboardService = $dashboardService ?: app(BusinessDashboardService::class);
        $inventoryService = $inventoryService ?: app(InventoryService::class);
        $range = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort' => ['nullable', 'string', 'in:profit,revenue,units,margin,cost_incomplete'],
            'dir' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $from = $range['from'] ?? now()->startOfMonth()->toDateString();
        $to = $range['to'] ?? now()->toDateString();
        $sort = $range['sort'] ?? 'profit';
        $dir = $range['dir'] ?? 'desc';

        $summary = $dashboardService->getSummary($shop, $from, $to, $sort, $dir);

        $inventory = $inventoryService->getShopInventorySummary($shop);
        $orders = $shop->orders()->with('items')->whereNull('invoice_id')->latest()->paginate(15);
        $rules = DB::table('product_price_rules')->whereIn('product_id', $shop->products()->select('id'))->get()->keyBy('product_id');
        $lots = InventoryLot::whereIn('product_id', $shop->products()->select('id'))->orderBy('received_at')->get();

        // Calculate today net sales for backward compatibility and quick KPI
        $refundBase = DB::table('invoice_returns')->join('invoices', 'invoices.id', '=', 'invoice_returns.invoice_id')->where('invoices.shop_id', $shop->id);
        $todayRefunds = (clone $refundBase)->whereDate('invoice_returns.created_at', today())->sum('invoice_returns.total');
        $today = Invoice::where('shop_id', $shop->id)->whereDate('issued_at', today())->where('status', '!=', 'void')->sum('total') - $todayRefunds;

        // Legacy products collection mapping for backward compatibility
        $products = collect($summary['profitability'])->map(fn ($p) => (object) [
            'product_id' => $p['product_id'],
            'product_name' => $p['product_name'],
            'units' => $p['units'],
            'revenue' => $p['revenue'],
            'known_cost' => $p['cost'],
            'unknown_lines' => $p['has_unknown_cost'] ? 1 : 0,
            'gross_profit' => $p['gross_profit'],
            'margin_percent' => $p['margin_percent'],
        ]);

        return view('seller.business', [
            'shop' => $shop,
            'from' => $from,
            'to' => $to,
            'sort' => $sort,
            'dir' => $dir,
            'summary' => $summary,
            'products' => $products,
            'today' => max(0.0, (float) $today),
            'total' => $summary['period']['net_sales'],
            'discount' => $summary['period']['discounts'],
            'receivable' => $summary['current_state']['receivable_total'],
            'inventory' => $inventory,
            'orders' => $orders,
            'rules' => $rules,
            'lots' => $lots,
        ]);
    }

    public function lots(Request $request, Shop $shop)
    {
        $products = $shop->products()->select(['id', 'name'])->get()->keyBy('id');
        $lots = InventoryLot::whereIn('product_id', $products->keys())
            ->orderByDesc('received_at')
            ->paginate(25);

        return view('seller.inventory.lots', compact('shop', 'lots', 'products'));
    }

    public function pricing(Request $request, Shop $shop)
    {
        $products = $shop->products()->where('sale_unit', '!=', 'decant')->with('inventory')->get();
        $rules = DB::table('product_price_rules')->whereIn('product_id', $products->pluck('id'))->get()->keyBy('product_id');

        return view('seller.pricing.index', compact('shop', 'products', 'rules'));
    }

    public function rule(Request $request, Shop $shop, Product $product, ProductPricingService $pricing)
    {
        $pricing->requirePro($product);
        $data = $request->validate([
            'margin_percent' => ['required', 'numeric', 'min:0', 'max:95'],
            'round_step' => ['required', 'decimal:0,2', 'min:0.01', 'max:10000'],
            'auto_increase' => ['sometimes', 'boolean'],
        ]);
        DB::transaction(function () use ($product, $data) {
            Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            DB::table('product_price_rules')->updateOrInsert(['product_id' => $product->id], [
                'margin_percent' => $data['margin_percent'],
                'round_step_cents' => (int) round((float) $data['round_step'] * 100),
                'auto_increase' => $data['auto_increase'] ?? false,
                'pending_price' => null,
                'created_at' => now(),
                'updated_at' => now(),
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
        $data = $request->validate([
            'payment_kind' => ['nullable', Rule::in(['paid', 'cash', 'credit', 'mixed'])],
            'payment_method' => ['nullable', Rule::in(array_keys(config('catalog.payment_methods', [])))],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('shop_id', $shop->id)->where('is_active', true)),
            ],
            'credit_amount' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        DB::transaction(function () use ($request, $order, $shop, $inventory, $data) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->invoice_id !== null) {
                return;
            }

            $paymentKind = $data['payment_kind'] ?? 'paid';
            $paymentKind = $paymentKind === 'cash' ? 'paid' : $paymentKind;
            $totalCents = Money::toCents($order->total);
            $creditCents = match ($paymentKind) {
                'credit' => $totalCents,
                'mixed' => Money::toCents($data['credit_amount'] ?? 0),
                default => 0,
            };

            if ($paymentKind === 'mixed' && ($creditCents <= 0 || $creditCents >= $totalCents)) {
                throw ValidationException::withMessages(['credit_amount' => 'En un pago mixto, el crédito debe ser menor que el total y mayor que cero.']);
            }
            if ($creditCents > $totalCents) {
                throw ValidationException::withMessages(['credit_amount' => 'El crédito no puede superar el total del pedido.']);
            }

            $customer = null;
            if ($creditCents > 0) {
                if (empty($data['customer_id'])) {
                    throw ValidationException::withMessages(['customer_id' => 'Selecciona el cliente que asumirá el crédito.']);
                }
                $customer = $shop->customers()->whereKey($data['customer_id'])->where('is_active', true)->firstOrFail();
            } elseif (! empty($data['customer_id'])) {
                $customer = $shop->customers()->whereKey($data['customer_id'])->where('is_active', true)->firstOrFail();
            }

            $paymentMethod = $data['payment_method'] ?? 'cash';
            if ($paymentMethod === 'credit') {
                $paymentMethod = 'cash';
            }
            $lines = $order->items()->get()->map(function ($item) use ($shop) {
                $product = $shop->products()->find($item->product_id);
                if (! $product) {
                    throw ValidationException::withMessages(['order' => 'Un producto del pedido ya no está disponible.']);
                }

                return ['product' => $product, 'quantity' => $item->quantity, 'unit_price' => (float) $item->unit_price];
            })->all();
            $initialStatus = $creditCents === 0 ? 'paid' : ($totalCents === $creditCents ? 'pending' : 'partial');
            $movements = $inventory->recordCartSales(
                $lines,
                $request->user()->id,
                'whatsapp',
                $initialStatus,
                0,
                0,
                $paymentMethod,
                $customer,
                Money::toDecimal($creditCents),
                $data['reference'] ?? null,
            );
            $order->update(['status' => 'confirmed', 'invoice_id' => $movements[0]->invoice_id]);
        });

        return back()->with('status', 'Pedido confirmado como venta.');
    }

    public function confirmForm(Request $request, Shop $shop, Order $order)
    {
        abort_unless($order->shop_id === $shop->id, 404);

        $order->load(['items', 'invoice.customer', 'invoice.payments']);
        $customers = $shop->customers()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'balance', 'credit_limit']);
        $invoiceUrl = $order->invoice
            ? URL::temporarySignedRoute('track.wa.shop', now()->addDays(7), [$shop, 'invoice' => $order->invoice->id])
            : null;

        return view('seller.orders.confirm', [
            'shop' => $shop,
            'order' => $order,
            'customers' => $customers,
            'paymentMethods' => config('catalog.payment_methods', []),
            'invoiceUrl' => $invoiceUrl,
        ]);
    }
}
