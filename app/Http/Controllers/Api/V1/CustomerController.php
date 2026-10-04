<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\Shop;
use App\Services\CustomerAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CustomerController extends Controller
{
    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->ensureShopOwner($request, $shop);
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'updated_since' => ['nullable', 'date'],
        ]);

        $customers = $shop->customers()
            ->when($validated['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%");
                });
            })
            ->when($validated['updated_since'] ?? null, fn ($query, string $since) => $query->where('updated_at', '>', $since))
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $customer) => $this->customerPayload($customer))
            ->values();

        return response()->json(['customers' => $customers, 'generated_at' => now()->toISOString()]);
    }

    public function store(Request $request, Shop $shop): JsonResponse
    {
        $this->ensureShopOwner($request, $shop);
        $validated = $this->validateCustomer($request);
        $payloadHash = hash('sha256', json_encode($validated, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        try {
            $customer = DB::transaction(function () use ($shop, $validated, $payloadHash): Customer {
                if (! empty($validated['client_customer_uuid'])) {
                    $existing = $shop->customers()
                        ->where('client_customer_uuid', $validated['client_customer_uuid'])
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        if (! hash_equals((string) $existing->payload_sha256, $payloadHash)) {
                            throw new InvalidArgumentException('Este identificador de cliente ya fue usado con datos distintos.', 409);
                        }

                        return $existing;
                    }
                }

                return $shop->customers()->create(array_merge($validated, ['payload_sha256' => $payloadHash]));
            });
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'reason' => 'idempotency_conflict'], $exception->getCode() ?: 422);
        }

        return response()->json($this->customerPayload($customer), 201);
    }

    public function show(Request $request, Shop $shop, Customer $customer): JsonResponse
    {
        $this->ensureCustomerOwner($request, $shop, $customer);

        return response()->json([
            'customer' => $this->customerPayload($customer),
            'entries' => $customer->accountEntries()
                ->with('invoice:id,invoice_number')
                ->limit(100)
                ->get()
                ->map(fn (CustomerAccountEntry $entry) => $this->entryPayload($entry))
                ->values(),
        ]);
    }

    public function payment(Request $request, Shop $shop, Customer $customer, CustomerAccountService $accountService): JsonResponse
    {
        $this->ensureCustomerOwner($request, $shop, $customer);
        $validated = $request->validate([
            'client_transaction_uuid' => ['required', 'uuid'],
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $payloadHash = hash('sha256', json_encode($validated, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        try {
            $entry = $accountService->recordPayment(
                $customer,
                $validated['amount'],
                $request->user()->id,
                $validated['client_transaction_uuid'],
                $payloadHash,
                $validated['notes'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'reason' => $exception->getCode() === 409 ? 'idempotency_conflict' : 'invalid_payment'], $exception->getCode() ?: 422);
        }

        return response()->json([
            'customer' => $this->customerPayload($customer->fresh()),
            'entry' => $this->entryPayload($entry),
        ], 201);
    }

    public function adjustment(Request $request, Shop $shop, Customer $customer, CustomerAccountService $accountService): JsonResponse
    {
        $this->ensureCustomerOwner($request, $shop, $customer);
        $validated = $request->validate([
            'client_transaction_uuid' => ['required', 'uuid'],
            'amount' => ['required', 'regex:/^-?\d+(\.\d{1,2})?$/', 'not_in:0,0.0,0.00,-0,-0.0,-0.00'],
            'notes' => ['required', 'string', 'max:255'],
        ]);
        $payloadHash = hash('sha256', json_encode($validated, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        try {
            $entry = $accountService->recordAdjustment(
                $customer,
                $validated['amount'],
                $request->user()->id,
                $validated['client_transaction_uuid'],
                $payloadHash,
                $validated['notes'],
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'reason' => $exception->getCode() === 409 ? 'idempotency_conflict' : 'invalid_adjustment'], $exception->getCode() ?: 422);
        }

        return response()->json([
            'customer' => $this->customerPayload($customer->fresh()),
            'entry' => $this->entryPayload($entry),
        ], 201);
    }

    /** @return array<string, mixed> */
    private function validateCustomer(Request $request): array
    {
        $validated = $request->validate([
            'client_customer_uuid' => ['nullable', 'uuid'],
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
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (! empty($validated['first_name']) && ! empty($validated['last_name'])) {
            $validated['name'] = trim($validated['first_name'].' '.$validated['last_name']);
        }

        if (empty($validated['name'])) {
            abort(422, 'El nombre del cliente es obligatorio.');
        }

        return $validated;
    }

    private function ensureShopOwner(Request $request, Shop $shop): void
    {
        abort_unless($request->user()->ownsShop($shop), 404);
    }

    private function ensureCustomerOwner(Request $request, Shop $shop, Customer $customer): void
    {
        $this->ensureShopOwner($request, $shop);
        abort_unless($customer->shop_id === $shop->id, 404);
    }

    /** @return array<string, mixed> */
    private function customerPayload(Customer $customer): array
    {
        return [
            'id' => $customer->public_id,
            'name' => $customer->name,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'document_type' => $customer->document_type,
            'document_number' => $customer->document_number,
            'phone' => $customer->phone,
            'whatsapp' => $customer->whatsapp,
            'email' => $customer->email,
            'address' => $customer->address,
            'reference' => $customer->reference,
            'notes' => $customer->notes,
            'credit_limit' => $customer->credit_limit,
            'balance' => $customer->balance,
            'available_credit' => number_format(max(0, (float) $customer->credit_limit - (float) $customer->balance), 2, '.', ''),
            'is_active' => $customer->is_active,
            'updated_at' => $customer->updated_at->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function entryPayload(CustomerAccountEntry $entry): array
    {
        return [
            'id' => (string) $entry->id,
            'type' => $entry->type,
            'amount' => $entry->amount,
            'balance_after' => $entry->balance_after,
            'invoice_number' => $entry->invoice?->invoice_number,
            'notes' => $entry->notes,
            'created_at' => $entry->created_at->toISOString(),
        ];
    }
}
