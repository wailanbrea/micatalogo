<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\SellerMenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SellerShopMenuController extends Controller
{
    public function edit(Request $request, Shop $shop, SellerMenuService $menus): View
    {
        abort_unless($menus->canManageMenuVisibility($shop, $request->user()), 403);

        return view('seller.shops.menu-visibility', [
            'shop' => $shop,
            'groups' => collect($menus->ownerMenuOptions())->groupBy('group'),
            'enabledKeys' => $menus->enabledKeys($shop),
            'protectedKeys' => $menus->ownerControlKeys(),
        ]);
    }

    public function update(Request $request, Shop $shop, SellerMenuService $menus): RedirectResponse
    {
        abort_unless($menus->canManageMenuVisibility($shop, $request->user()), 403);

        $validated = $request->validate([
            'enabled_menu_keys' => ['nullable', 'array'],
            'enabled_menu_keys.*' => [Rule::in($menus->keys())],
        ]);
        $enabled = $menus->normalizeEnabled($validated['enabled_menu_keys'] ?? []);

        // Keep the owner control path available even when every operational
        // module is disabled.
        $shop->update([
            'enabled_menu_keys' => $menus->normalizeEnabled(array_merge($enabled, $menus->ownerControlKeys())),
        ]);

        return to_route('seller.shops.menus.edit', $shop)
            ->with('status', 'Menús de la tienda actualizados. Los cambios aplican en web y Android.');
    }
}
