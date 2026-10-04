<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        if ($user->status !== UserStatus::Active) {
            return response()->json(['message' => 'Esta cuenta no está activa.'], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Debes verificar tu correo antes de conectar BSPOS.'], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken($validated['device_name'] ?? 'BSPOS', ['catalog:read', 'pos:write', 'customers:read', 'customers:write'])->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    /**
     * @return array{id: string, name: string, email: string, role: string}
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
        ];
    }
}
