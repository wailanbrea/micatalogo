<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\DailyCloseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SellerDailyCloseController extends Controller
{
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
