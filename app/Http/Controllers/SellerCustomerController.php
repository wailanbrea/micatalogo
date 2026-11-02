<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Shop;
use App\Services\CustomerAccountService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class SellerCustomerController extends Controller
{
    public function payments(Request $request, Shop $shop, \App\Services\CustomerPaymentReversalService $service): View
    {
        $data = $request->validate(['customer' => ['nullable', 'string', 'max:36']]);
        $payments = \App\Models\CustomerAccountEntry::where('shop_id', $shop->id)->where('type', 'payment')
            ->when($data['customer'] ?? null, fn ($q, $id) => $q->whereHas('customer', fn ($c) => $c->where('public_id', $id)))
            ->with(['customer', 'reversal.user'])->orderByDesc('id')->paginate(30)->withQueryString();
        return view('seller.customers.payments', ['shop' => $shop, 'payments' => $payments, 'canReverse' => $service->canReverse($shop, $request->user())]);
    }

    public function reversePayment(Request $request, Shop $shop, Customer $customer, int $paymentId, \App\Services\CustomerPaymentReversalService $service): RedirectResponse
    {
        $data = $request->validate(['client_transaction_uuid' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:255']]);
        try {
            $service->reverse($shop, $customer, $paymentId, $request->user(), $data['client_transaction_uuid'], $data['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reversal' => $e->getMessage()]);
        }
        return back()->with('status', 'Abono anulado. Saldo, facturas y conciliación actualizados; historial conservado.');
    }

    public function index(Request $request, Shop $shop): View
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $search = trim((string) ($validated['q'] ?? ''));
        $customers = $shop->customers()
            ->with(['accountEntries' => fn ($query) => $query->with('reversal')->reorder()->latest('created_at')->latest('id')->limit(6)])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('document_number', 'like', "%{$search}%"));
            })
            ->orderByDesc('balance')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'active_count' => $shop->customers()->where('is_active', true)->count(),
            'total_balance' => (float) $shop->customers()->sum('balance'),
            'total_credit_limit' => (float) $shop->customers()->where('is_active', true)->sum('credit_limit'),
        ];

        $visible = app(\App\Services\SellerMenuService::class)->visibleForUser($shop, $request->user());

        return view('seller.customers.index', compact('shop', 'customers', 'search', 'summary') + [
            'canCreateCustomer' => in_array('customers', $visible, true),
            'canChargeCredit' => in_array('credit', $visible, true),
            'canCollect' => in_array('collections', $visible, true),
        ]);
    }

    public function store(Request $request, Shop $shop): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'first_name' => ['required_without:name', 'string', 'max:80'],
            'last_name' => ['required_without:name', 'string', 'max:80'],
            'document_type' => ['required_without:name', 'in:cedula,pasaporte'],
            'document_number' => ['required_without:name', 'string', 'max:40'],
            'phone' => ['required_without:name', 'string', 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'address' => ['required_without:name', 'string', 'max:2000'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'credit_limit' => ['required', 'decimal:0,2', 'min:0'],
        ]);

        if (! empty($validated['first_name']) && ! empty($validated['last_name'])) {
            $validated['name'] = trim($validated['first_name'].' '.$validated['last_name']);
        }

        $shop->customers()->create($validated);

        return back()->with('status', 'Cliente creado correctamente.');
    }

    public function charge(Request $request, Shop $shop, Customer $customer, CustomerAccountService $accountService): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
            'notes' => ['required', 'string', 'max:255'],
        ]);

        try {
            $accountService->recordManualCharge(
                $customer,
                $validated['amount'],
                $request->user()->id,
                (string) Str::uuid(),
                $this->payloadHash('charge', $customer, $validated),
                $validated['notes'],
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['charge' => $exception->getMessage()]);
        }

        return back()->with('status', "Crédito registrado para {$customer->name}.");
    }

    public function payment(
        Request $request,
        Shop $shop,
        Customer $customer,
        PaymentService $paymentService
    ): RedirectResponse {
        $validated = $request->validate([
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
            'payment_method' => ['sometimes', 'string', 'in:cash,card,bank_transfer,other'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $paymentMethod = $validated['payment_method'] ?? 'cash';
        $uuid = (string) Str::uuid();

        try {
            $paymentService->recordCustomerDebtPayment(
                $shop,
                $customer,
                $request->user(),
                $validated['amount'],
                $paymentMethod,
                $uuid,
                $this->payloadHash('payment', $customer, $validated),
                $validated['notes'] ?? null,
                $validated['reference'] ?? null
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return back()->with('status', "Cobro de RD\${$validated['amount']} ({$paymentMethod}) registrado para {$customer->name}.");
    }

    /** @param array<string, mixed> $payload */
    private function payloadHash(string $type, Customer $customer, array $payload): string
    {
        return hash('sha256', json_encode([
            'type' => $type,
            'customer_id' => $customer->public_id,
            'amount' => $payload['amount'],
            'notes' => $payload['notes'] ?? null,
        ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
