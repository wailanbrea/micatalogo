<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\SellerMenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminMenuVisibilityController extends Controller
{
    public function index(Request $request, SellerMenuService $menus): View
    {
        abort_unless($menus->isPlatformOwner($request->user()), 403);

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $query = Shop::query()
            ->with('user')
            ->withCount(['products', 'sellers']);

        if ($search !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->where(function ($shops) use ($escaped): void {
                $shops->where('name', 'like', "%{$escaped}%")
                    ->orWhere('slug', 'like', "%{$escaped}%")
                    ->orWhereHas('user', function ($users) use ($escaped): void {
                        $users->where('name', 'like', "%{$escaped}%")
                            ->orWhere('email', 'like', "%{$escaped}%");
                    });
            });
        }

        if (in_array($status, ['active', 'suspended'], true)) {
            $query->where('status', $status);
        } else {
            $status = 'all';
        }

        $shops = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('admin.menu-visibility.index', compact('shops', 'search', 'status'));
    }

    public function edit(Request $request, Shop $shop, SellerMenuService $menus): View
    {
        abort_unless($menus->isPlatformOwner($request->user()), 403);

        return view('admin.menu-visibility.edit', [
            'shop' => $shop->load('user'),
            'groups' => collect($menus->ownerMenuOptions())->groupBy('group'),
            'enabledKeys' => $menus->enabledKeys($shop),
            'protectedKeys' => $menus->ownerControlKeys(),
        ]);
    }

    public function update(Request $request, Shop $shop, SellerMenuService $menus): RedirectResponse
    {
        abort_unless($menus->isPlatformOwner($request->user()), 403);

        $validated = $request->validate([
            'enabled_menu_keys' => ['nullable', 'array'],
            'enabled_menu_keys.*' => [Rule::in($menus->keys())],
        ]);

        $enabled = $menus->normalizeEnabled($validated['enabled_menu_keys'] ?? []);
        $shop->update([
            'enabled_menu_keys' => $menus->normalizeEnabled(array_merge($enabled, $menus->ownerControlKeys())),
        ]);

        return to_route('admin.menus.edit', $shop)
            ->with('status', 'Menús actualizados para '.$shop->name.'. Aplican en web y Android a todos los usuarios de esta tienda.');
    }
}
