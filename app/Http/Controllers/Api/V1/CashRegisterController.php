<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashRegisterSession;
use App\Models\Shop;
use App\Services\CashRegisterService;
use App\Services\PlanLimitsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashRegisterController extends Controller
{
    public function current(Request $request, Shop $shop, CashRegisterService $service, PlanLimitsService $limits): JsonResponse
    {
        abort_unless($request->user()->canSellAtShop($shop), 403);
        $limits->assertFeature($shop->user, 'cash_registers');

        $session = $service->getCurrentSession($shop, $request->user());

        if (! $session) {
            return response()->json([
                'session' => null,
                'has_open_session' => false,
            ]);
        }

        $summary = $service->getSessionSummary($session);

        return response()->json([
            'session' => [
                'id' => $session->public_id,
                'opened_at' => $session->opened_at->toIso8601String(),
                'opening_amount' => (float) $session->opening_amount,
                'status' => $session->status,
                'notes' => $session->notes,
                'summary' => $summary,
            ],
            'has_open_session' => true,
        ]);
    }

    public function open(Request $request, Shop $shop, CashRegisterService $service, PlanLimitsService $limits): JsonResponse
    {
        abort_unless($request->user()->canSellAtShop($shop), 403);
        $limits->assertFeature($shop->user, 'cash_registers');

        $validated = $request->validate([
            'opening_amount' => ['required', 'decimal:0,2', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
            'client_operation_uuid' => ['nullable', 'uuid'],
        ]);

        $payloadHash = ! empty($validated['client_operation_uuid'])
            ? hash('sha256', json_encode($validated, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION))
            : null;

        $session = $service->openSession(
            $shop,
            $request->user(),
            $validated['opening_amount'],
            $validated['notes'] ?? null,
            $validated['client_operation_uuid'] ?? null,
            $payloadHash
        );

        return response()->json([
            'message' => 'Caja abierta exitosamente.',
            'session' => [
                'id' => $session->public_id,
                'opened_at' => $session->opened_at->toIso8601String(),
                'opening_amount' => (float) $session->opening_amount,
                'status' => $session->status,
            ],
        ], 201);
    }

    public function close(Request $request, Shop $shop, CashRegisterSession $session, CashRegisterService $service, PlanLimitsService $limits): JsonResponse
    {
        abort_unless($request->user()->canSellAtShop($shop), 403);
        abort_unless($session->shop_id === $shop->id, 404);
        $limits->assertFeature($shop->user, 'cash_registers');

        abort_unless($service->canManageSession($shop, $request->user(), $session), 403, 'No tienes permiso para cerrar la sesión de caja de otro usuario.');

        $validated = $request->validate([
            'counted_amount' => ['required', 'decimal:0,2', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'client_operation_uuid' => ['nullable', 'uuid'],
        ]);

        $payloadHash = ! empty($validated['client_operation_uuid'])
            ? hash('sha256', json_encode($validated, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION))
            : null;

        $closed = $service->closeSession(
            $session,
            $request->user(),
            $validated['counted_amount'],
            $validated['notes'] ?? null,
            $validated['client_operation_uuid'] ?? null,
            $payloadHash
        );

        return response()->json([
            'message' => 'Caja cerrada exitosamente.',
            'session' => [
                'id' => $closed->public_id,
                'opened_at' => $closed->opened_at->toIso8601String(),
                'closed_at' => $closed->closed_at?->toIso8601String(),
                'opening_amount' => (float) $closed->opening_amount,
                'expected_closing_amount' => (float) $closed->expected_closing_amount,
                'counted_closing_amount' => (float) $closed->counted_closing_amount,
                'difference' => (float) $closed->difference,
                'status' => $closed->status,
                'notes' => $closed->notes,
            ],
        ]);
    }

    public function movement(Request $request, Shop $shop, CashRegisterSession $session, CashRegisterService $service, PlanLimitsService $limits): JsonResponse
    {
        abort_unless($request->user()->canSellAtShop($shop), 403);
        abort_unless($session->shop_id === $shop->id, 404);
        $limits->assertFeature($shop->user, 'cash_registers');

        abort_unless($service->canManageSession($shop, $request->user(), $session), 403, 'No tienes permiso para registrar movimientos en la sesión de caja de otro usuario.');

        $validated = $request->validate([
            'type' => ['required', 'in:cash_in,cash_out,owner_contribution,owner_withdrawal,adjustment'],
            'amount' => ['required', 'decimal:0,2', 'min:0.01'],
            'notes' => ['required', 'string', 'max:255'],
            'client_operation_uuid' => ['nullable', 'uuid'],
        ]);

        $payloadHash = ! empty($validated['client_operation_uuid'])
            ? hash('sha256', json_encode($validated, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION))
            : null;

        $movement = $service->recordManualMovement(
            $session,
            $request->user(),
            $validated['type'],
            $validated['amount'],
            $validated['notes'],
            $validated['client_operation_uuid'] ?? null,
            $payloadHash
        );

        return response()->json([
            'message' => 'Movimiento registrado exitosamente.',
            'movement' => [
                'id' => $movement->public_id,
                'type' => $movement->type,
                'amount' => (float) $movement->amount,
                'notes' => $movement->notes,
                'occurred_at' => $movement->occurred_at->toIso8601String(),
            ],
        ], 201);
    }
}
