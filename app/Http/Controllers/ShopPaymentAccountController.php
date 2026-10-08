<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\ShopPaymentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopPaymentAccountController extends Controller
{
    public function store(Request $request, Shop $shop): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:120'],
            'account_holder' => ['nullable', 'string', 'max:160'],
            'instructions' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['sort_order'] = ((int) $shop->paymentAccounts()->max('sort_order')) + 1;
        $shop->paymentAccounts()->create($data);

        return back()->with('status', 'Cuenta de pago agregada.');
    }

    public function update(Request $request, Shop $shop, ShopPaymentAccount $paymentAccount): RedirectResponse
    {
        abort_unless($paymentAccount->shop_id === $shop->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:120'],
            'account_holder' => ['nullable', 'string', 'max:160'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $paymentAccount->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('status', 'Cuenta de pago actualizada.');
    }

    public function destroy(Shop $shop, ShopPaymentAccount $paymentAccount): RedirectResponse
    {
        abort_unless($paymentAccount->shop_id === $shop->id, 404);
        $paymentAccount->update(['is_active' => false]);

        return back()->with('status', 'Cuenta de pago desactivada.');
    }
}
