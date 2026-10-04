<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebAccountIsActive
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if ($request->user()?->status === UserStatus::Active) {
            return $next($request);
        }

        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('error', 'Esta cuenta está suspendida.');
    }
}
