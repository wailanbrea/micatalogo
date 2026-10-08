<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\ShopMember;
use App\Services\AccountantAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountantAccessController extends Controller
{
    public function store(Request $request, Shop $shop, AccountantAccessService $access): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $result = $access->grant($shop, $validated['email']);

        return back()->with('status', $result['alreadyActive']
            ? 'Este contador ya tenía acceso de solo lectura.'
            : ($result['wasInvited']
                ? "Se creó la cuenta de {$result['user']->name} y se envió la invitación."
                : "{$result['user']->name} recibió acceso de solo lectura."));
    }

    public function destroy(Shop $shop, ShopMember $member, AccountantAccessService $access): RedirectResponse
    {
        $access->revoke($shop, $member);

        return back()->with('status', 'El acceso del contador fue desactivado.');
    }
}
