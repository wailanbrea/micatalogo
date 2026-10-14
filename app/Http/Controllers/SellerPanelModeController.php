<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SellerPanelModeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'mode' => ['required', Rule::in(['store', 'admin'])],
        ]);

        $storeMode = $data['mode'] === 'store';
        $request->session()->put('owner_store_mode', $storeMode);

        if ($storeMode && ($request->routeIs('admin.*') || $request->query('view') === 'all')) {
            return to_route('seller.dashboard')->with('status', 'Modo tienda activado. Se ocultó el menú administrativo.');
        }

        return back()->with(
            'status',
            $storeMode
                ? 'Modo tienda activado. Se ocultó el menú administrativo.'
                : 'Modo administrador activado. El menú administrativo está visible.'
        );
    }
}
