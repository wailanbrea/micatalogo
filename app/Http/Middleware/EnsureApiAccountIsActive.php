<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAccountIsActive
{
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $user = $request->user();

        if ($user?->status !== UserStatus::Active) {
            return response()->json(['message' => 'Esta cuenta no está activa.'], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Debes verificar tu correo antes de conectar BSPOS.'], 403);
        }

        return $next($request);
    }
}
