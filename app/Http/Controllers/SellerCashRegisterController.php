<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterSession;
use App\Models\Shop;
use App\Services\CashRegisterService;
use App\Services\PlanLimitsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class SellerCashRegisterController extends Controller
{
    public function index(Request $request, Shop $shop, CashRegisterService $cashRegisterService, PlanLimitsService $limits): View
    {
        $limits->assertFeature($shop->user, 'cash_registers');

        $user = $request->user();
        $currentSession = $cashRegisterService->getCurrentSession($shop, $user);
        $sessionSummary = null;
        $movements = collect();

        if ($currentSession) {
            $currentSession->load(['movements' => fn ($q) => $q->latest()->limit(50)]);
            $movements = $currentSession->movements;
            $sessionSummary = $cashRegisterService->getSessionSummary($currentSession);
        }

        $canManageAll = $cashRegisterService->canManageAllCashRegisters($shop, $user);

        $pastSessionsQuery = CashRegisterSession::query()
            ->where('shop_id', $shop->id)
            ->where('status', 'closed')
            ->with('user')
            ->latest('closed_at');

        if (! $canManageAll) {
            $pastSessionsQuery->where('user_id', $user->id);
        }

        $pastSessions = $pastSessionsQuery->paginate(10)->withQueryString();

        $allowedTypes = [
            'cash_in' => 'Entrada de efectivo',
            'cash_out' => 'Salida de efectivo',
            'owner_contribution' => 'Aporte de capital (dueño)',
            'owner_withdrawal' => 'Retiro de ganancias (dueño)',
        ];

        return view('seller.cash.index', compact(
            'shop',
            'currentSession',
            'sessionSummary',
            'movements',
            'pastSessions',
            'allowedTypes',
            'canManageAll'
        ));
    }

    public function open(Request $request, Shop $shop, CashRegisterService $cashRegisterService, PlanLimitsService $limits): RedirectResponse
    {
        $limits->assertFeature($shop->user, 'cash_registers');

        $validated = $request->validate([
            'opening_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $cashRegisterService->openSession(
                $shop,
                $request->user(),
                $validated['opening_amount'],
                $validated['notes'] ?? null
            );

            return back()->with('status', 'Sesión de caja abierta correctamente con RD$ '.number_format((float) $validated['opening_amount'], 2));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cash_register' => $e->getMessage()]);
        }
    }

    public function close(Request $request, Shop $shop, CashRegisterSession $session, CashRegisterService $cashRegisterService, PlanLimitsService $limits): RedirectResponse
    {
        abort_unless($session->shop_id === $shop->id, 404);
        $limits->assertFeature($shop->user, 'cash_registers');

        abort_unless($cashRegisterService->canManageSession($shop, $request->user(), $session), 403, 'No tienes permiso para cerrar la sesión de caja de otro usuario.');

        $validated = $request->validate([
            'counted_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $closed = $cashRegisterService->closeSession(
                $session,
                $request->user(),
                $validated['counted_amount'],
                $validated['notes'] ?? null
            );

            $diffText = $closed->difference_cents === null
                ? 'Cierre sin arqueo registrado.'
                : (($closed->difference_cents / 100.0) == 0.0
                    ? 'Arqueo perfecto: caja cuadrada.'
                    : ($closed->difference_cents > 0
                        ? 'Caja cerrada con SOBRANTE de RD$ '.number_format($closed->difference_cents / 100.0, 2)
                        : 'Caja cerrada con FALTANTE de RD$ '.number_format(abs($closed->difference_cents / 100.0), 2)));

            return back()->with('status', 'Caja cerrada. '.$diffText);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cash_register' => $e->getMessage()]);
        }
    }

    public function movement(Request $request, Shop $shop, CashRegisterSession $session, CashRegisterService $cashRegisterService, PlanLimitsService $limits): RedirectResponse
    {
        abort_unless($session->shop_id === $shop->id, 404);
        $limits->assertFeature($shop->user, 'cash_registers');

        abort_unless($cashRegisterService->canManageSession($shop, $request->user(), $session), 403, 'No tienes permiso para registrar movimientos en la caja de otro usuario.');

        $validated = $request->validate([
            'type' => ['required', 'in:cash_in,cash_out,owner_contribution,owner_withdrawal'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['required', 'string', 'max:500'],
        ]);

        try {
            $cashRegisterService->recordManualMovement(
                $session,
                $request->user(),
                $validated['type'],
                $validated['amount'],
                $validated['notes']
            );

            return back()->with('status', 'Movimiento de caja registrado exitosamente.');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cash_register' => $e->getMessage()]);
        }
    }
}
