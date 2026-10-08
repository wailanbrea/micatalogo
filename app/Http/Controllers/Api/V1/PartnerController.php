<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BusinessPartner;
use App\Models\PartnerTransaction;
use App\Models\Shop;
use App\Services\CashRegisterService;
use App\Services\SellerMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PartnerController extends Controller
{
    public function store(Request $request, Shop $shop, SellerMenuService $menus): JsonResponse
    {
        $this->authorizeShop($request, $shop, $menus);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'ownership_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $partner = $shop->partners()->create($data + ['ownership_percent' => $data['ownership_percent'] ?? 0, 'is_active' => true]);

        return response()->json([
            'message' => 'Socio guardado.',
            'partner' => ['id' => $partner->public_id, 'name' => $partner->name],
        ], 201);
    }

    public function transaction(
        Request $request,
        Shop $shop,
        BusinessPartner $partner,
        SellerMenuService $menus,
        CashRegisterService $cash
    ): JsonResponse {
        $this->authorizeShop($request, $shop, $menus);
        abort_unless($partner->shop_id === $shop->id && $partner->is_active, 404);
        $data = $request->validate([
            'type' => ['required', 'in:contribution,withdrawal,distribution'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $session = $cash->getCurrentSession($shop, $request->user());
        abort_unless($session, 422, 'Abre la caja antes de registrar un movimiento de socio.');

        try {
            DB::transaction(function () use ($request, $shop, $partner, $data, $session, $cash): void {
                $cashMovement = $cash->recordManualMovement(
                    $session,
                    $request->user(),
                    $data['type'] === 'contribution' ? 'owner_contribution' : 'owner_withdrawal',
                    $data['amount'],
                    $data['notes'] ?? 'Movimiento de socio'
                );
                PartnerTransaction::create([
                    'shop_id' => $shop->id,
                    'partner_id' => $partner->id,
                    'user_id' => $request->user()->id,
                    'cash_movement_id' => $cashMovement->id,
                    'type' => $data['type'],
                    'amount' => $data['amount'],
                    'notes' => $data['notes'] ?? null,
                    'occurred_at' => now(),
                ]);
            });
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json(['message' => 'Movimiento de socio registrado en caja.']);
    }

    private function authorizeShop(Request $request, Shop $shop, SellerMenuService $menus): void
    {
        abort_unless($menus->canManage($shop, $request->user()), 403);
    }
}
