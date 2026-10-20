<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\DailyCloseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SellerDailyCloseController extends Controller
{
    public function adjust(Request $request, Shop $shop, string $closure, DailyCloseService $service)
    {
        abort_unless($service->canAdjust($shop, $request->user()), 403);
        $data = $request->validate(['client_operation_uuid' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:1000'],
            'counted_cash' => ['nullable', 'decimal:0,2', 'min:0', 'max:1000000000000']]);
        try {
            $service->adjust($shop, $request->user(), $closure, $data['client_operation_uuid'], $data['counted_cash'] ?? null, $data['reason']);
            return back()->with('status', 'Ajuste de conciliación registrado en el historial; el cierre original no cambió.');
        } catch (InvalidArgumentException $error) {
            if ($request->expectsJson()) return response()->json(['message' => $error->getMessage()], $error->getCode() ?: 422);
            return back()->withInput()->withErrors(['daily_close' => $error->getMessage()]);
        }
    }

    public function store(Request $request, Shop $shop, DailyCloseService $service): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'counted_cash' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $closure = $service->close(
                $shop,
                $request->user(),
                $validated['date'],
                $validated['counted_cash'] ?? null,
                $validated['notes'] ?? null
            );

            $message = $closure->difference_cents === null
                ? 'Cierre diario guardado sin arqueo.'
                : ($closure->difference_cents === 0
                    ? 'Cierre diario guardado: caja cuadrada.'
                    : 'Cierre diario guardado con '.($closure->difference_cents > 0 ? 'sobrante' : 'faltante').' de RD$ '.number_format(abs($closure->difference_cents) / 100, 2));

            return back()->with('status', $message);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['daily_close' => $e->getMessage()]);
        }
    }
}
