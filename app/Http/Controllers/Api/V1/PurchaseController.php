<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\PurchaseDocument;
use App\Models\Shop;
use App\Services\PurchaseDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PurchaseController
{
    public function index(Shop $shop, PurchaseDocumentService $purchases): JsonResponse
    {
        $documents = $shop->purchaseDocuments()
            ->with(['supplier', 'items.product'])
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

    public function store(Request $request, Shop $shop, PurchaseDocumentService $purchases): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:container,load,purchase_invoice'],
            'document_number' => ['required', 'string', 'max:80'],
            'supplier_id' => ['nullable', 'string'],
            'currency' => ['nullable', 'string', 'size:3'],
            'mode' => ['nullable', 'in:draft,received'],
            'items' => ['required', 'array', 'min:1'],
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
