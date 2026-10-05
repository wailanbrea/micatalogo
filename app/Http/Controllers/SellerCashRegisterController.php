<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterSession;
use App\Models\Shop;
use App\Services\CashRegisterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class SellerCashRegisterController extends Controller
{
    public function index(Request $request, Shop $shop, CashRegisterService $cashRegisterService): View
    {
        $user = $request->user();
        $currentSession = $cashRegisterService->getCurrentSession($shop, $user);
        $sessionSummary = null;
        $movements = collect();

        if ($currentSession) {
            $currentSession->load(['movements' => fn ($q) => $q->latest()->limit(50)]);
            $movements = $currentSession->movements;
            $sessionSummary = $cashRegisterService->getSessionSummary($currentSession);
        }

        $pastSessions = CashRegisterSession::query()
            ->where('shop_id', $shop->id)
            ->where('status', 'closed')
            ->with('user')
            ->latest('closed_at')
            ->paginate(10)
            ->withQueryString();

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
            'allowedTypes'
        ));
    }

    public function open(Request $request, Shop $shop, CashRegisterService $cashRegisterService): RedirectResponse
    {
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

            return back()->with('status', 'Sesión de caja abierta correctamente con RD$ ' . number_format((float) $validated['opening_amount'], 2));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cash_register' => $e->getMessage()]);
        }
    }

    public function close(Request $request, Shop $shop, CashRegisterSession $session, CashRegisterService $cashRegisterService): RedirectResponse
    {
        abort_unless($session->shop_id === $shop->id, 404);

        $validated = $request->validate([
            'counted_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $closed = $cashRegisterService->closeSession(
                $session,
                $request->user(),
                $validated['counted_amount'],
                $validated['notes'] ?? null
            );

            $diff = $closed->difference_cents / 100.0;
            $diffText = $diff == 0.0
                ? 'Arqueo perfecto: caja cuadrada.'
                : ($diff > 0
                    ? 'Caja cerrada con SOBRANTE de RD$ ' . number_format($diff, 2)
                    : 'Caja cerrada con FALTANTE de RD$ ' . number_format(abs($diff), 2));

            return back()->with('status', 'Caja cerrada. ' . $diffText);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cash_register' => $e->getMessage()]);
        }
    }

    public function movement(Request $request, Shop $shop, CashRegisterSession $session, CashRegisterService $cashRegisterService): RedirectResponse
    {
        abort_unless($session->shop_id === $shop->id, 404);

        $validated = $request->validate([
            'type' => ['required', 'in:cash_in,cash_out,owner_contribution,owner_withdrawal'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['required', 'string', 'max:500'],
        ]);

        try {
            $cashRegisterService->recordMovement(
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
