<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class EmailVerificationCodeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('seller.dashboard');
        }

        return view('auth.verify-email');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => 'Escribe el código que recibiste por correo.',
            'code.digits' => 'El código debe tener 6 dígitos.',
        ]);

        $verification = DB::table('email_verification_codes')
            ->where('user_id', $request->user()->id)
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();

        if (! $verification || ! Hash::check($request->string('code')->toString(), $verification->code_hash)) {
            return back()->withErrors(['code' => 'El código no es válido o ya venció. Solicita uno nuevo.']);
        }

        $request->user()->forceFill(['email_verified_at' => now()])->save();
        DB::table('email_verification_codes')->where('user_id', $request->user()->id)->delete();

        return redirect()->route('seller.dashboard')->with('status', 'Correo verificado correctamente.');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('seller.dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-code-sent');
    }
}
