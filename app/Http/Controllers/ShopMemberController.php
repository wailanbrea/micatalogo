<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use App\Notifications\SellerInvitationNotification;
use App\Services\PlanLimitsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShopMemberController extends Controller
{
    public function store(Request $request, Shop $shop, PlanLimitsService $limits): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $email = Str::lower(trim($validated['email']));
        $user = User::query()->where('email', $email)->first();

        if ($user?->id === $shop->user_id) {
            return back()->withErrors(['email' => 'El propietario ya tiene acceso administrativo.']);
        }

        $membership = $shop->members()->firstOrNew(['user_id' => $user?->id]);
        if ($membership->exists && $membership->is_active) {
            return back()->withErrors(['email' => 'Este usuario ya tiene acceso administrativo a la tienda.']);
        }

        $limits->assertCanAddUser($shop);

        $wasInvited = ! $user;
        if (! $user) {
            $user = User::create([
                'name' => Str::headline(Str::before($email, '@')),
                'email' => $email,
                'password' => Str::random(40),
            ]);
        }

        $membership = $shop->members()->firstOrNew(['user_id' => $user->id]);
        $membership->fill(['role' => 'manager', 'is_active' => true])->save();

        if (! $user->hasVerifiedEmail()) {
            $user->notify(new SellerInvitationNotification($shop, 'usuario administrativo'));
        }

        return back()->with('status', $wasInvited
            ? "Se creó la cuenta de {$user->name} y se envió la invitación administrativa."
            : "{$user->name} recibió acceso administrativo a la tienda.");
    }

    public function destroy(Shop $shop, ShopMember $member): RedirectResponse
    {
        abort_unless($member->shop_id === $shop->id, 404);
        $member->update(['is_active' => false]);

        return back()->with('status', 'El acceso administrativo fue desactivado.');
    }
}
