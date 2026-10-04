<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SellerInvitationController extends Controller
{
    public function show(Request $request, User $user, string $hash, Shop $shop): View|RedirectResponse
    {
        $this->ensureInvitationIsValid($request, $user, $hash, $shop);

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('login')->with('status', 'Tu invitación ya fue activada. Inicia sesión con tu contraseña.');
        }

        return view('auth.activate-seller-invitation', [
            'seller' => $user,
            'shop' => $shop,
            'activationUrl' => $request->fullUrl(),
        ]);
    }

    public function activate(Request $request, User $user, string $hash, Shop $shop): RedirectResponse
    {
        $this->ensureInvitationIsValid($request, $user, $hash, $shop);

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('login')->with('status', 'Tu invitación ya fue activada. Inicia sesión con tu contraseña.');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ])->save();

        event(new Verified($user));
        Auth::login($user, remember: true);

        return redirect()->route('seller.dashboard')->with('status', 'Tu cuenta fue activada. Ya puedes entrar desde la web o BSPOS.');
    }

    private function ensureInvitationIsValid(Request $request, User $user, string $hash, Shop $shop): void
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless(hash_equals($hash, sha1($user->getEmailForVerification())), 403);
        abort_unless($shop->sellers()->where('user_id', $user->id)->where('is_active', true)->exists(), 404);
    }
}
