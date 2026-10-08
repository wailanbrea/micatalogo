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
        // Resolve the tenant boundary before validating operation-specific input.
        // Otherwise an external actor could probe validation details for a shop
        // they cannot access, even though no mutation would be committed.
        abort_unless($request->user()->canSellAtShop($shop), 404);
        $type = $request->validate(['type' => ['required', 'in:product_upsert,product_archive,restock,adjustment,open_bottle,return']])['type'];
        $rules = ['type' => ['required'], 'client_operation_uuid' => ['required', 'uuid'], 'notes' => ['nullable', 'string', 'max:1000']];
        if ($type === 'return') {
            $rules += ['client_sale_uuid' => ['required', 'uuid'], 'items' => ['required', 'array', 'min:1', 'max:100'],
                'items.*.product_id' => ['required', 'ulid', 'distinct'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
                'items.*.refund_price' => ['required', 'decimal:0,2', 'min:0', 'max:1000000000'],
                'items.*.refund_total' => ['sometimes', 'decimal:0,2', 'min:0', 'max:10000000000000'], 'items.*.restock' => ['required', 'boolean']];
        } else {
            $rules['product_id'] = ['required', 'ulid'];
            if ($type === 'product_upsert') {
                $rules += ['name' => ['sometimes', 'required', 'string', 'max:255'],
                    'internal_code' => ['nullable', 'string', 'max:120'], 'barcode' => ['nullable', 'regex:/^[0-9]{8,14}$/'],
                    'description' => ['nullable', 'string', 'max:10000'], 'category_name' => ['nullable', 'string', 'max:100'],
                    'sale_unit' => ['sometimes', 'nullable', 'in:unit,bottle,ml,decant,service'],
                    'is_combo' => ['sometimes', 'boolean'],
                    'combo_items' => ['sometimes', 'array', 'max:30'],
                    'combo_items.*.product_id' => ['required', 'ulid', 'distinct'],
                    'combo_items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
                    'volume_ml' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
                    'inventory_source_product_id' => ['sometimes', 'nullable', 'ulid'],
                    'image_base64' => ['sometimes', 'string', 'max:699052'],
                    'image_sha256' => ['required_with:image_base64', 'regex:/^[a-f0-9]{64}$/'],
                    'price' => ['sometimes', 'decimal:0,2', 'min:0', 'max:1000000000'], 'expected_price' => ['sometimes', 'decimal:0,2', 'min:0'],
                    'cost_price' => ['nullable', 'decimal:0,2', 'min:0', 'max:1000000000'], 'minimum_stock' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
                    'published' => ['sometimes', 'boolean']];
            }
            if ($type === 'restock') {
                $rules += ['quantity' => ['required', 'integer', 'min:1', 'max:1000000'], 'unit_cost' => ['required', 'decimal:0,2', 'min:0', 'max:1000000000']];
            }
            if ($type === 'adjustment') {
                $rules += ['stock' => ['required', 'integer', 'min:0', 'max:1000000'], 'expected_stock' => ['required', 'integer', 'min:0'], 'notes' => ['required', 'string', 'max:1000']];
            }
            if ($type === 'adjustment') {
                $rules['notes'] = ['required', 'string', 'max:1000'];
            }
            if ($type === 'adjustment') {
                $rules['expected_available_ml'] = ['sometimes', 'integer', 'min:0'];
            }
            if ($type === 'open_bottle') {
                $rules += [
                    'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
                    'expected_stock' => ['required', 'integer', 'min:0'],
                    'expected_available_ml' => ['sometimes', 'integer', 'min:0'],
                ];
            }
        }
        $data = $request->validate($rules);
        try {
            return response()->json($service->apply($shop, $request->user(), $data), 201);
        } catch (InvalidArgumentException $error) {
            return response()->json(['message' => $error->getMessage(), 'reason' => 'operation_conflict'], $error->getCode() ?: 422);
        }
    }
}
