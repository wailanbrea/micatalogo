<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\MobileOperationService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MobileOperationController extends Controller
{
    public function store(Request $request, Shop $shop, MobileOperationService $service)
    {
        $type = $request->validate(['type' => ['required', 'in:product_upsert,product_archive,restock,adjustment,return']])['type'];
        $rules = ['type' => ['required'], 'client_operation_uuid' => ['required', 'uuid'], 'notes' => ['nullable', 'string', 'max:1000']];
        if ($type === 'return') {
            $rules += ['client_sale_uuid' => ['required', 'uuid'], 'items' => ['required', 'array', 'min:1', 'max:100'],
                'items.*.product_id' => ['required', 'ulid', 'distinct'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
                'items.*.refund_price' => ['required', 'decimal:0,2', 'min:0', 'max:1000000000'], 'items.*.restock' => ['required', 'boolean']];
        } else {
            $rules['product_id'] = ['required', 'ulid'];
            if ($type === 'product_upsert') $rules += ['name' => ['sometimes', 'required', 'string', 'max:255'],
                'internal_code' => ['nullable', 'string', 'max:120'], 'barcode' => ['nullable', 'regex:/^[0-9]{8,14}$/'],
                'description' => ['nullable', 'string', 'max:10000'], 'category_name' => ['nullable', 'string', 'max:100'],
                'price' => ['sometimes', 'decimal:0,2', 'min:0', 'max:1000000000'], 'expected_price' => ['sometimes', 'decimal:0,2', 'min:0'],
                'cost_price' => ['nullable', 'decimal:0,2', 'min:0', 'max:1000000000'], 'minimum_stock' => ['sometimes', 'integer', 'min:0', 'max:1000000']];
            if ($type === 'restock') $rules += ['quantity' => ['required', 'integer', 'min:1', 'max:1000000'], 'unit_cost' => ['required', 'decimal:0,2', 'min:0', 'max:1000000000']];
            if ($type === 'adjustment') $rules += ['stock' => ['required', 'integer', 'min:0', 'max:1000000'], 'expected_stock' => ['required', 'integer', 'min:0'], 'notes' => ['required', 'string', 'max:1000']];
            if ($type === 'adjustment') $rules['notes'] = ['required', 'string', 'max:1000'];
        }
        $data = $request->validate($rules);
        try { return response()->json($service->apply($shop, $request->user(), $data), 201); }
        catch (InvalidArgumentException $error) { return response()->json(['message' => $error->getMessage(), 'reason' => 'operation_conflict'], $error->getCode() ?: 422); }
    }
}
