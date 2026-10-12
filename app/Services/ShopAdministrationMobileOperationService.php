<?php

namespace App\Services;

use App\Http\Controllers\Api\V1\ShopController;
use App\Http\Controllers\Api\V1\ShopSettingsController;
use App\Http\Controllers\SellerAuthorizationController;
use App\Models\AuthorizationRequest;
use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Durable envelope for shop administration mutations. */
class ShopAdministrationMobileOperationService
{
    public const TYPES = [
        'shop_settings_update',
        'seller_menu_permissions_update',
        'shop_menu_visibility_update',
        'seller_create',
        'authorization_approve',
        'authorization_reject',
    ];

    public function apply(Shop $shop, User $user, array $data): array
    {
        if (($data['request']['client_operation_uuid'] ?? null) !== null
            && ($data['request']['client_operation_uuid'] ?? null) !== $data['client_operation_uuid']) {
            throw ValidationException::withMessages([
                'client_operation_uuid' => 'La identidad del formulario no coincide con la operación.',
            ]);
        }

        $menus = app(SellerMenuService::class);
        $requiredMenu = match ($data['type']) {
            'shop_settings_update', 'shop_menu_visibility_update' => 'shop_settings',
            'seller_menu_permissions_update', 'seller_create' => 'sellers',
            'authorization_approve', 'authorization_reject' => 'authorizations',
        };
        abort_unless(in_array($requiredMenu, $menus->visibleForUser($shop, $user), true), 403);

        $request = Request::create('/api/v1/mobile-shop-administration', 'POST', $data['request']);
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $user);

        [$controller, $method, $parameters] = match ($data['type']) {
            'shop_settings_update' => [ShopSettingsController::class, 'update', []],
            'seller_menu_permissions_update' => [ShopController::class, 'updateSellerMenus', [
                'seller' => ShopSeller::where('shop_id', $shop->id)->whereKey($data['resource_id'])->firstOrFail(),
            ]],
            'shop_menu_visibility_update' => [ShopController::class, 'updateMenuVisibility', []],
            'seller_create' => [ShopController::class, 'storeSeller', []],
            'authorization_approve', 'authorization_reject' => [SellerAuthorizationController::class,
                $data['type'] === 'authorization_approve' ? 'approveApi' : 'rejectApi', [
                    'authorizationRequest' => AuthorizationRequest::where('shop_id', $shop->id)
                        ->where('public_id', $data['resource_id'])->lockForUpdate()->firstOrFail(),
                ]],
        };

        $response = app()->call([app($controller), $method], ['request' => $request, 'shop' => $shop] + $parameters);
        if ($response->getStatusCode() >= 400) {
            throw new HttpResponseException($response);
        }

        return $response->getData(true);
    }
}
