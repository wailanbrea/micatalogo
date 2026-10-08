<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\AccountantAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountantAccessController extends Controller
{
    public function store(Request $request, Shop $shop, AccountantAccessService $access): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->ownsShop($shop), 403);
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $result = $access->grant($shop, $validated['email']);

        return response()->json([
            'message' => $result['alreadyActive'] ? 'El contador ya tenía acceso de solo lectura.' : 'Acceso de contador guardado.',
            'accountant' => [
                'id' => (string) $result['membership']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'is_active' => $result['membership']->is_active,
            ],
        ], $result['wasInvited'] ? 201 : 200);
    }
}
