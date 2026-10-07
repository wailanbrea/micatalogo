<?php

namespace App\Http\Controllers;

use App\Models\BusinessPartner;
use App\Models\CommercialQuote;
use App\Models\PartnerTransaction;
use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\Shop;
use App\Services\CashRegisterService;
use App\Services\InventoryService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SellerCommerceController extends Controller
{
    public function storeQuote(Request $request, Shop $shop): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'string'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $products = Product::query()
            ->where('shop_id', $shop->id)
            ->whereIn('public_id', collect($data['items'])->pluck('product_id'))
            ->get()
            ->keyBy('public_id');

        if ($products->count() !== count(array_unique(collect($data['items'])->pluck('product_id')->all()))) {
            return back()->withErrors(['items' => 'Uno o más productos no pertenecen a esta tienda.'])->withInput();
        }
        $customerId = null;
        if (! empty($data['customer_id'])) {
            $customerId = $shop->customers()->where('public_id', $data['customer_id'])->value('id');
            if (! $customerId) {
                return back()->withErrors(['customer_id' => 'El cliente no pertenece a esta tienda.'])->withInput();
            }
        }

        $lines = [];
        $subtotalCents = 0;
        foreach ($data['items'] as $line) {
            $product = $products->get($line['product_id']);
            $unitPriceCents = Money::toCents($line['unit_price'] ?? $product->currentPrice());
            $quantity = (int) $line['quantity'];
            $lineTotalCents = $unitPriceCents * $quantity;
            $subtotalCents += $lineTotalCents;
            $lines[] = compact('product', 'quantity', 'unitPriceCents', 'lineTotalCents');
        }

        $quote = DB::transaction(function () use ($shop, $request, $data, $customerId, $lines, $subtotalCents): CommercialQuote {
            $quote = CommercialQuote::create([
                'shop_id' => $shop->id,
                'customer_id' => $customerId,
                'user_id' => $request->user()->id,
                'quote_number' => $this->nextQuoteNumber($shop),
                'status' => 'draft',
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'currency' => 'DOP',
                'subtotal' => Money::toDecimal($subtotalCents),
                'discount' => '0.00',
                'tax' => '0.00',
                'total' => Money::toDecimal($subtotalCents),
                'valid_until' => $data['valid_until'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $product = $line['product'];
                $quote->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_code' => $product->product_code,
                    'sale_unit' => $product->sale_unit ?: 'unit',
                    'volume_ml' => $product->volume_ml,
                    'quantity' => $line['quantity'],
                    'unit_price' => Money::toDecimal($line['unitPriceCents']),
                    'line_total' => Money::toDecimal($line['lineTotalCents']),
                ]);
            }

            return $quote;
        });

        return back()->with('status', "Cotización {$quote->quote_number} guardada.");
    }

    public function convertQuote(Request $request, Shop $shop, CommercialQuote $quote, InventoryService $inventory): RedirectResponse
    {
        abort_unless($quote->shop_id === $shop->id, 404);
        $quotesRoute = route('seller.shops.feature', [$shop, 'feature' => 'quotes']);
        if ($quote->converted_invoice_id || $quote->status === 'converted') {
            return redirect()->to($quotesRoute)->withErrors(['quote' => 'Esta cotización ya fue convertida en venta.']);
        }

        try {
            DB::transaction(function () use ($request, $shop, $quote, $inventory): void {
                $quote->load('items');
                $products = Product::query()->where('shop_id', $shop->id)->whereIn('id', $quote->items->pluck('product_id'))->get()->keyBy('id');
                if ($products->count() !== $quote->items->pluck('product_id')->unique()->count()) {
                    throw new InvalidArgumentException('La cotización contiene un producto que ya no está disponible.');
                }

                $sales = $quote->items->map(fn ($item) => [
                    'product' => $products->get($item->product_id),
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                ])->all();
                $movements = $inventory->recordCartSales($sales, $request->user()->id, 'web', 'paid', 0, 0, 'cash');
                $invoice = $movements[0]->fresh()->invoice;
                if ($quote->customer_id && $invoice) {
                    $invoice->update(['customer_id' => $quote->customer_id]);
                }
                $quote->update(['status' => 'converted', 'converted_invoice_id' => $invoice?->id]);
            });
        } catch (InvalidArgumentException $exception) {
            return redirect()->to($quotesRoute)->withErrors(['quote' => $exception->getMessage()]);
        }

        return redirect()->to($quotesRoute)->with('status', "Cotización {$quote->quote_number} convertida en venta.");
    }

    public function storeSupplier(Request $request, Shop $shop): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $shop->suppliers()->create($data);

        return back()->with('status', 'Suplidor guardado.');
    }

    public function storePurchaseDocument(Request $request, Shop $shop, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:container,load,purchase_invoice'],
            'document_number' => ['required', 'string', 'max:80'],
            'supplier_id' => ['nullable', 'string'],
            'product_id' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $product = Product::query()->where('shop_id', $shop->id)->where('public_id', $data['product_id'])->firstOrFail();
        $supplier = ! empty($data['supplier_id']) ? $shop->suppliers()->where('public_id', $data['supplier_id'])->firstOrFail() : null;

        try {
            DB::transaction(function () use ($request, $shop, $data, $product, $supplier, $inventory): void {
                $document = PurchaseDocument::create([
                    'shop_id' => $shop->id,
                    'supplier_id' => $supplier?->id,
                    'user_id' => $request->user()->id,
                    'document_number' => $data['document_number'],
                    'type' => $data['type'],
                    'status' => 'received',
                    'currency' => 'DOP',
                    'subtotal' => Money::toDecimal(Money::toCents($data['unit_cost']) * (int) $data['quantity']),
                    'total' => Money::toDecimal(Money::toCents($data['unit_cost']) * (int) $data['quantity']),
                    'received_at' => now(),
                    'notes' => $data['notes'] ?? null,
                ]);
                $movement = $inventory->recordRestock($product, (int) $data['quantity'], $data['notes'] ?? null, $request->user()->id, (float) $data['unit_cost']);
                $document->items()->create([
                    'product_id' => $product->id,
                    'inventory_movement_id' => $movement->id,
                    'product_name' => $product->name,
                    'quantity' => (int) $data['quantity'],
                    'unit_cost' => $data['unit_cost'],
                    'line_total' => Money::toDecimal(Money::toCents($data['unit_cost']) * (int) $data['quantity']),
                ]);
            });
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['purchase' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', 'Compra recibida y lote agregado al inventario.');
    }

    public function storePartner(Request $request, Shop $shop): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'ownership_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $shop->partners()->create($data + ['ownership_percent' => $data['ownership_percent'] ?? 0]);

        return back()->with('status', 'Socio guardado.');
    }

    public function storePartnerTransaction(Request $request, Shop $shop, BusinessPartner $partner, CashRegisterService $cash): RedirectResponse
    {
        abort_unless($partner->shop_id === $shop->id, 404);
        $data = $request->validate([
            'type' => ['required', 'in:contribution,withdrawal,distribution'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $session = $cash->getCurrentSession($shop, $request->user());
        if (! $session) {
            return back()->withErrors(['partner' => 'Abre la caja antes de registrar un movimiento de socio.']);
        }

        try {
            DB::transaction(function () use ($request, $shop, $partner, $data, $session, $cash): void {
                $cashMovement = $cash->recordManualMovement(
                    $session,
                    $request->user(),
                    $data['type'] === 'contribution' ? 'owner_contribution' : 'owner_withdrawal',
                    $data['amount'],
                    $data['notes'] ?? 'Movimiento de socio'
                );
                PartnerTransaction::create([
                    'shop_id' => $shop->id,
                    'partner_id' => $partner->id,
                    'user_id' => $request->user()->id,
                    'cash_movement_id' => $cashMovement->id,
                    'type' => $data['type'],
                    'amount' => $data['amount'],
                    'notes' => $data['notes'] ?? null,
                    'occurred_at' => now(),
                ]);
            });
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['partner' => $exception->getMessage()]);
        }

        return back()->with('status', 'Movimiento de socio registrado en caja.');
    }

    private function nextQuoteNumber(Shop $shop): string
    {
        do {
            $number = 'COT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while ($shop->quotes()->where('quote_number', $number)->exists());

        return $number;
    }
}
