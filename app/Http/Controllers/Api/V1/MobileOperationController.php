<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\MobileOperationService;
use App\Services\ShopRecordSyncService;
use App\Services\ShopRouteSyncService;
use App\Services\FinancialMobileOperationService;
use App\Services\OnlineMobileOperationService;
use App\Services\ShopAdministrationMobileOperationService;
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
        $type = $request->validate(['type' => ['required', 'in:'.implode(',', array_merge(ShopRecordSyncService::TYPES, ShopRouteSyncService::TYPES, FinancialMobileOperationService::TYPES, OnlineMobileOperationService::TYPES, ShopAdministrationMobileOperationService::TYPES, ['product_upsert','product_archive','product_photo_update','restock','adjustment','open_bottle','customer_upsert','return','decant_setup','decant_vial_receive','decant_vial_update','decant_prepare','decant_presentation_update','decant_discard','decant_undo_open','decant_sell_remainder','decant_reconcile_opening']))]])['type'];
        $rules = ['type' => ['required'], 'client_operation_uuid' => ['required', 'uuid'], 'notes' => ['nullable', 'string', 'max:1000']];
        if (in_array($type, array_merge(FinancialMobileOperationService::TYPES, OnlineMobileOperationService::TYPES, ShopAdministrationMobileOperationService::TYPES), true)) {
            $rules += ['request' => ['required', 'array'], 'resource_id' => ['required_if:type,cash_close,cash_movement,expense_payment,quote_convert,order_confirm,purchase_receive,partner_transaction,seller_menu_permissions_update,authorization_approve,authorization_reject,day_close_adjustment', 'nullable', 'string', 'max:80']];
            if ($type === 'order_confirm') {
                // Orders expose their numeric tenant-scoped primary key, unlike quotes and purchases.
                $rules['resource_id'] = ['required', 'integer', 'min:1'];
            }
        } elseif (in_array($type, ShopRouteSyncService::TYPES, true)) {
            try {
                $data = app(ShopRouteSyncService::class)->validate($request->all());
                return response()->json($service->apply($shop, $request->user(), $data), 201);
            } catch (InvalidArgumentException $error) {
                return response()->json(['message' => $error->getMessage(), 'reason' => 'operation_conflict'], $error->getCode() ?: 422);
            }
        } elseif (in_array($type, ShopRecordSyncService::TYPES, true)) {
            $rules += ['record_id' => ['required', 'uuid'], 'name' => ['required', 'string', 'max:100'],
                'is_active' => ['required', 'boolean'], 'is_deleted' => ['required', 'boolean'], 'created_at' => ['required', 'date']];
            if (in_array($type, ['category_upsert', 'unit_upsert'], true)) {
                $rules['name'] = ['required', 'string', 'max:'.($type === 'category_upsert' ? 120 : 100)];
                $rules += ['scope' => ['sometimes', 'string', 'in:shared,product,service'], 'expected_revision' => ['sometimes', 'nullable', 'uuid']];
            }
            $rules += match ($type) {
                'category_upsert' => ['description' => ['nullable', 'string', 'max:2000'], 'icon' => ['required', 'string', 'max:80'], 'sort_order' => ['required', 'integer', 'min:0'], 'slug' => ['sometimes', 'nullable', 'string', 'max:120']],
                'unit_upsert' => ['abbreviation' => ['required', 'string', 'max:12']],
                'adjustment_reason_upsert' => ['direction' => ['required', 'in:IN,OUT,BOTH']],
            };
        } elseif (str_starts_with($type, 'decant_')) {
            $rules += match ($type) {
                'decant_setup' => ['source_product_id' => ['required', 'ulid'], 'expected_stock' => ['required', 'integer', 'min:0'],
                    'mode' => ['sometimes', 'in:ready,on_demand'], 'presentations' => ['required', 'array', 'min:1', 'max:12'],
                    'presentations.*.volume_ml' => ['required', 'integer', 'min:1', 'max:100000'],
                    'presentations.*.price' => ['required', 'decimal:0,2', 'min:0', 'max:1000000000'],
                    'presentations.*.quantity' => ['sometimes', 'integer', 'min:0', 'max:100000'],
                    'presentations.*.vial_quantity' => ['sometimes', 'integer', 'min:0', 'max:100000'],
                    'presentations.*.vial_unit_cost' => ['nullable', 'decimal:0,2', 'min:0', 'max:1000000'],
                    'presentations.*.name' => ['nullable', 'string', 'max:255']],
                'decant_vial_receive' => ['volume_ml' => ['required', 'integer', 'min:1', 'max:1000'], 'name' => ['nullable', 'string', 'max:120'],
                    'quantity' => ['required', 'integer', 'min:1', 'max:100000'], 'unit_cost' => ['required', 'decimal:0,2', 'min:0', 'max:1000000'],
                    'sale_price' => ['nullable', 'decimal:0,2', 'min:0', 'max:1000000']],
                'decant_vial_update' => ['vial_id' => ['required', 'ulid'], 'name' => ['nullable', 'string', 'max:120'], 'active' => ['required', 'boolean']],
                'decant_presentation_update' => ['product_id' => ['required', 'ulid'], 'on_demand' => ['sometimes', 'boolean'], 'offered' => ['sometimes', 'boolean'],
                    'price' => ['sometimes', 'decimal:0,2', 'min:0', 'max:1000000'], 'expected_price' => ['required_with:price', 'decimal:0,2', 'min:0']],
                'decant_prepare' => ['opening_id' => ['required_without:source_product_id', 'nullable', 'ulid'], 'source_product_id' => ['required_without:opening_id', 'nullable', 'ulid'],
                    'product_id' => ['required', 'ulid'], 'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
                    'expected_stock' => ['required_with:source_product_id', 'integer', 'min:1'],
                    'expected_ml' => ['required_without:source_product_id', 'integer', 'min:0'], 'price' => ['required', 'decimal:0,2', 'min:0', 'max:1000000']],
                'decant_discard' => ['opening_id' => ['required', 'ulid'], 'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
                    'expected_ml' => ['required', 'integer', 'min:0'], 'notes' => ['required', 'string', 'max:1000']],
                'decant_undo_open' => ['opening_id' => ['required', 'ulid'], 'expected_ml' => ['required', 'integer', 'min:0']],
                'decant_sell_remainder' => ['opening_id' => ['required', 'ulid'], 'expected_ml' => ['required', 'integer', 'min:1'],
                    'price' => ['required', 'decimal:0,2', 'min:0', 'max:1000000'], 'payment_method' => ['required', 'in:cash,card,bank_transfer']],
                'decant_reconcile_opening' => ['product_id' => ['required', 'ulid'], 'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
                    'expected_ml' => ['required', 'integer', 'min:1']],
            };
        } elseif ($type === 'return') {
            // A return may reference a sale uploaded from the mobile outbox or
            // any server invoice (web/POS/quote conversion). Both identities
            // use the same idempotent mobile operation envelope.
            $rules += ['client_sale_uuid' => ['required_without:invoice_id', 'nullable', 'uuid'], 'invoice_id' => ['required_without:client_sale_uuid', 'nullable', 'integer'], 'items' => ['required', 'array', 'min:1', 'max:100'],
                'items.*.product_id' => ['required', 'ulid', 'distinct'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
                'items.*.refund_price' => ['required', 'decimal:0,2', 'min:0', 'max:1000000000'],
                'items.*.refund_total' => ['sometimes', 'decimal:0,2', 'min:0', 'max:10000000000000'], 'items.*.restock' => ['required', 'boolean']];
        } elseif ($type === 'customer_upsert') {
            $rules['notes'] = ['nullable', 'string', 'max:2000'];
            $rules += [
                'client_customer_uuid' => ['required', 'uuid'],
                'customer_id' => ['sometimes', 'nullable', 'ulid'],
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
                'credit_limit' => ['required', 'decimal:0,2', 'min:0'],
                'is_active' => ['sometimes', 'boolean'],
                'is_deleted' => ['sometimes', 'boolean'],
            ];
        } else {
            $rules['product_id'] = ['required', 'ulid'];
            if ($type === 'product_photo_update') {
                $rules += ['image_base64' => ['required', 'string', 'max:699052'], 'image_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/']];
                foreach (['name', 'price', 'expected_price', 'cost_price', 'sale_unit', 'stock', 'quantity', 'is_combo',
                    'category_uuid', 'category_name', 'unit_uuid', 'internal_code', 'barcode', 'description', 'combo_items',
                    'volume_ml', 'inventory_source_product_id', 'minimum_stock', 'published'] as $field) {
                    $rules[$field] = ['prohibited'];
                }
            }
            if ($type === 'product_upsert') {
                $rules += ['name' => ['sometimes', 'required', 'string', 'max:255'],
                    'internal_code' => ['nullable', 'string', 'max:120'], 'barcode' => ['nullable', 'regex:/^[0-9]{8,14}$/'],
                    'description' => ['nullable', 'string', 'max:10000'], 'category_name' => ['nullable', 'string', 'max:100'],
                    'category_uuid' => ['sometimes', 'nullable', 'uuid'], 'unit_uuid' => ['sometimes', 'uuid'],
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
                    'track_decants' => ['sometimes', 'boolean'],
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
