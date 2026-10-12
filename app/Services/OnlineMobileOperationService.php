<?php

namespace App\Services;

use App\Http\Controllers\Api\V1\{QuoteController, OrderController, PurchaseController, InventoryImportController, FinanceReportController};
use App\Models\{Shop, User};
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Durable envelope for existing online modules; original controllers remain the business authority. */
class OnlineMobileOperationService
{
    public const TYPES = ['quote_create', 'quote_convert', 'order_confirm', 'purchase_create', 'purchase_receive', 'supplier_create', 'inventory_import_confirm', 'day_close', 'partner_create', 'partner_transaction'];

    public function apply(Shop $shop, User $user, array $data): array
    {
        if (($data['request']['client_operation_uuid'] ?? null) !== $data['client_operation_uuid']) {
            throw ValidationException::withMessages(['client_operation_uuid' => 'El formulario debe conservar el UUID de su operación.']);
        }
        $menu = match ($data['type']) {
            'quote_create', 'quote_convert' => 'quotes', 'order_confirm' => 'orders', 'supplier_create' => 'suppliers',
            'inventory_import_confirm' => 'inventory', 'day_close' => 'day_close',
            'partner_create', 'partner_transaction' => 'partners',
            default => 'containers',
        };
        abort_unless(in_array($menu, app(SellerMenuService::class)->visibleForUser($shop, $user), true), 403);
        $request = Request::create('/api/v1/mobile-online-operation', 'POST', $data['request']);
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $user);
        [$controller, $method, $parameters] = match ($data['type']) {
            'partner_create' => [\App\Http\Controllers\Api\V1\PartnerController::class, 'store', []],
            'partner_transaction' => [\App\Http\Controllers\Api\V1\PartnerController::class, 'transaction', ['partner' => $shop->partners()->where('public_id', $data['resource_id'])->lockForUpdate()->firstOrFail()]],
            'quote_create' => [QuoteController::class, 'store', []],
            'quote_convert' => [QuoteController::class, 'convert', ['quote' => $shop->quotes()->where('public_id', $data['resource_id'])->lockForUpdate()->firstOrFail()]],
            'order_confirm' => [OrderController::class, 'confirm', ['order' => $shop->orders()->whereKey((int) $data['resource_id'])->lockForUpdate()->firstOrFail()]],
            'purchase_create' => [PurchaseController::class, 'store', []],
            'purchase_receive' => [PurchaseController::class, 'receive', ['document' => \App\Models\PurchaseDocument::where('shop_id', $shop->id)->where('public_id', $data['resource_id'])->lockForUpdate()->firstOrFail()]],
            'supplier_create' => [PurchaseController::class, 'storeSupplier', []],
            'inventory_import_confirm' => [InventoryImportController::class, 'store', []],
            'day_close' => [FinanceReportController::class, 'closeDay', []],
        };
        $response = app()->call([app($controller), $method], ['request' => $request, 'shop' => $shop] + $parameters);
        if ($response->getStatusCode() >= 400) throw new HttpResponseException($response);
        return $response->getData(true);
    }
}
