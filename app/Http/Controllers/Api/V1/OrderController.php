<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shop;
use App\Services\OrderConfirmationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function confirm(
        Request $request,
        Shop $shop,
        Order $order,
        OrderConfirmationService $confirmation
    ): JsonResponse {
        abort_unless($request->user()->canSellAtShop($shop), 404);
        abort_unless($order->shop_id === $shop->id, 404);

        $data = $request->validate([
            'payment_kind' => ['nullable', Rule::in(['paid', 'cash', 'credit', 'mixed'])],
            'payment_method' => ['nullable', Rule::in(array_keys(config('catalog.payment_methods', [])))],
            'customer_id' => ['nullable', 'string', 'max:64'],
            'credit_amount' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $invoiceNumber = $confirmation->confirm($shop, $order, $request->user(), $data);

        return response()->json([
            'message' => 'Pedido confirmado como venta.',
            'order_number' => $order->order_number,
            'invoice_number' => $invoiceNumber,
        ]);
    }
}
