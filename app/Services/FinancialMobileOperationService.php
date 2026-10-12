<?php

namespace App\Services;

use App\Http\Controllers\Api\V1\CashRegisterController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Models\CashRegisterSession;
use App\Models\Expense;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Reuses existing validation, plan limits and cash ownership checks inside the durable envelope. */
class FinancialMobileOperationService
{
    public const TYPES = ['cash_open', 'cash_close', 'cash_movement', 'expense_create', 'expense_payment'];

    public function apply(Shop $shop, User $user, array $data): array
    {
        if (($data['request']['client_operation_uuid'] ?? null) !== $data['client_operation_uuid']) {
            throw ValidationException::withMessages(['client_operation_uuid' => 'La identidad del formulario no coincide con la operación.']);
        }
        $menu = str_starts_with($data['type'], 'cash_') ? 'cash' : 'expenses';
        abort_unless(in_array($menu, app(SellerMenuService::class)->visibleForUser($shop, $user), true), 403);
        $request = Request::create('/api/v1/mobile-financial-operation', 'POST', $data['request']);
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $user);
        [$controller, $method, $parameters] = match ($data['type']) {
            'cash_open' => [CashRegisterController::class, 'open', []],
            'cash_close' => [CashRegisterController::class, 'close', ['session' => CashRegisterSession::where('shop_id', $shop->id)->where('public_id', $data['resource_id'])->firstOrFail()]],
            'cash_movement' => [CashRegisterController::class, 'movement', ['session' => CashRegisterSession::where('shop_id', $shop->id)->where('public_id', $data['resource_id'])->firstOrFail()]],
            'expense_create' => [ExpenseController::class, 'store', []],
            'expense_payment' => [ExpenseController::class, 'pay', ['expense' => Expense::where('shop_id', $shop->id)->where('public_id', $data['resource_id'])->firstOrFail()]],
        };
        $response = app()->call([app($controller), $method], ['request' => $request, 'shop' => $shop] + $parameters);
        if ($response->getStatusCode() >= 400) throw new HttpResponseException($response);

        return $response->getData(true);
    }
}
