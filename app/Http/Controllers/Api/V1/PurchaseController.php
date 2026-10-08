<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\PurchaseDocument;
use App\Models\Shop;
use App\Models\Supplier;
use App\Services\PurchaseDocumentService;
use App\Services\PurchaseInvoiceReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PurchaseController
{
    public function preview(Request $request, Shop $shop, PurchaseInvoiceReader $reader): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls,pdf,jpg,jpeg,png,webp', 'extensions:csv,txt,xlsx,xls,pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        return response()->json($reader->preview($request->file('file'), $shop, $request->user()));
    }

    public function index(Shop $shop, PurchaseDocumentService $purchases): JsonResponse
    {
        $documents = $shop->purchaseDocuments()
            ->with(['supplier', 'items.product', 'parentDocument'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (PurchaseDocument $document): array => $purchases->payload($document))
            ->values();

        return response()->json([
            'documents' => $documents,
            ...$purchases->catalogPayload($shop),
        ]);
    }

    public function suppliers(Shop $shop): JsonResponse
    {
        return response()->json([
            'suppliers' => $shop->suppliers()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Supplier $supplier): array => [
                    'id' => $supplier->public_id,
                    'name' => $supplier->name,
                    'invoice_currency' => $supplier->invoice_currency,
                    'phone' => $supplier->phone,
                    'email' => $supplier->email,
                    'address' => $supplier->address,
                    'notes' => $supplier->notes,
                ])
                ->values(),
        ]);
    }

    public function storeSupplier(Request $request, Shop $shop): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'invoice_currency' => ['nullable', 'string', 'size:3'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $supplier = $shop->suppliers()->create($data + ['is_active' => true]);

        return response()->json([
            'message' => 'Suplidor guardado.',
            'supplier' => [
                'id' => $supplier->public_id,
                'name' => $supplier->name,
                'invoice_currency' => $supplier->invoice_currency,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'address' => $supplier->address,
                'notes' => $supplier->notes,
            ],
        ], 201);
    }

    public function store(Request $request, Shop $shop, PurchaseDocumentService $purchases): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:container,load,purchase_invoice,supplier_debt'],
            'document_number' => ['required', 'string', 'max:80'],
            'supplier_id' => ['nullable', 'string'],
            'invoice_date' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'carrier' => ['nullable', 'string', 'max:160'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'expected_at' => ['nullable', 'date'],
            'shipping_pounds' => ['nullable', 'numeric', 'min:0'],
            'freight_amount' => ['nullable', 'numeric', 'min:0'],
            'customs_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_status' => ['nullable', 'in:pending,partial,paid'],
            'parent_document_id' => ['nullable', 'string'],
            'mode' => ['nullable', 'in:draft,received'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $document = $purchases->create($shop, $request->user(), $data);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => ($data['mode'] ?? 'received') === 'draft'
                ? 'Borrador guardado sin tocar el inventario.'
                : 'Compra recibida y lotes agregados al inventario.',
            'document' => $purchases->payload($document),
        ], 201);
    }

    public function receive(Request $request, Shop $shop, PurchaseDocument $document, PurchaseDocumentService $purchases): JsonResponse
    {
        try {
            $document = $purchases->receive($shop, $document, $request->user());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => "Compra {$document->document_number} recibida y agregada al inventario.",
            'document' => $purchases->payload($document),
        ]);
    }
}
