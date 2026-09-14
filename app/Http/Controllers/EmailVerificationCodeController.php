<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EmailVerificationCodeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user() && $request->user()->hasVerifiedEmail()) {
            return redirect()->route('seller.dashboard');
        }

        $email = $request->user()?->email
            ?? $request->query('email')
            ?? session('registered_email')
            ?? old('email');

        return view('auth.verify-email', [
            'email' => $email,
            'user' => $request->user(),
        ]);
    }

    public function verifyDirectLink(Request $request, $id, $hash): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('verification.notice')
                ->withErrors(['code' => 'El enlace de activación ha expirado o es inválido. Puedes ingresar tu código o solicitar uno nuevo.']);
        }

        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect()->route('verification.notice', ['email' => $user->email])
                ->withErrors(['code' => 'El enlace de activación no corresponde a este usuario.']);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            DB::table('email_verification_codes')->where('user_id', $user->id)->delete();
            event(new Verified($user));
        }

        if (! Auth::check() || Auth::id() !== $user->id) {
            Auth::login($user, remember: true);
        }

        return redirect()->route('seller.dashboard')
            ->with('status', '¡Tu cuenta ha sido activada con éxito! Bienvenido a MiCatalogo.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            $validated = $request->validate([
                'email' => ['required', 'email', 'exists:users,email'],
                'code' => ['required', 'digits:6'],
            ], [
                'email.required' => 'Ingresa tu correo electrónico.',
                'email.email' => 'Ingresa un correo electrónico válido.',
                'email.exists' => 'No encontramos ninguna cuenta con este correo.',
                'code.required' => 'Escribe el código de 6 dígitos que recibiste por correo.',
                'code.digits' => 'El código debe tener exactamente 6 dígitos.',
            ]);

            $user = User::where('email', trim(Str::lower($validated['email'])))->first();
        } else {
            $request->validate([
                'code' => ['required', 'digits:6'],
            ], [
                'code.required' => 'Escribe el código de 6 dígitos que recibiste por correo.',
                'code.digits' => 'El código debe tener exactamente 6 dígitos.',
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            if (! Auth::check() || Auth::id() !== $user->id) {
                Auth::login($user, remember: true);
            }

            return redirect()->route('seller.dashboard')->with('status', 'Tu correo ya está verificado.');
        }

        $code = $request->string('code')->toString();

        $verification = DB::table('email_verification_codes')
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();

        if (! $verification || ! Hash::check($code, $verification->code_hash)) {
            return back()->withInput()->withErrors([
                'code' => 'El código no es válido o ya venció. Solicita uno nuevo.',
            ]);
        }

        $user->markEmailAsVerified();
        DB::table('email_verification_codes')->where('user_id', $user->id)->delete();
        event(new Verified($user));

        if (! Auth::check() || Auth::id() !== $user->id) {
            Auth::login($user, remember: true);
        }

        return redirect()->route('seller.dashboard')->with('status', '¡Correo verificado correctamente! Bienvenido a tu vitrina.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            $validated = $request->validate([
                'email' => ['required', 'email', 'exists:users,email'],
            ], [
                'email.required' => 'Ingresa tu correo electrónico para reenviar el código.',
                'email.email' => 'Ingresa un correo electrónico válido.',
                'email.exists' => 'No encontramos ninguna cuenta con este correo.',
            ]);

            $user = User::where('email', trim(Str::lower($validated['email'])))->first();
        }

        if ($user->hasVerifiedEmail()) {
            if (! Auth::check() || Auth::id() !== $user->id) {
                Auth::login($user, remember: true);
            }

            return redirect()->route('seller.dashboard')->with('status', 'Tu correo ya está verificado.');
        }

        $user->sendEmailVerificationNotification();

        return back()->withInput()->with('status', 'verification-code-sent');
    }
}
