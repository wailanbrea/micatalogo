<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;
use App\Notifications\SellerInvitationNotification;
use App\Services\SellerMenuService;
use App\Services\PlanLimitsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SellerManagementController extends Controller
{
    public function index(Shop $shop): View
    {
        $sellers = $shop->sellers()->with('user')->orderByDesc('is_active')->orderBy('created_at')->get();
        $members = $shop->members()->with('user')->orderByDesc('is_active')->orderBy('created_at')->get();
        $quota = app(\App\Services\PlanLimitsService::class)->shopQuota($shop);
        $stats = Invoice::query()
            ->where('shop_id', $shop->id)
            ->whereNotNull('salesperson_id')
            ->selectRaw('salesperson_id, COUNT(*) as sales_count, COALESCE(SUM(total), 0) as sales_total, COALESCE(SUM(commission_amount), 0) as commission_total')
            ->groupBy('salesperson_id')
            ->get()
            ->keyBy('salesperson_id');
        $sales = Invoice::query()
            ->with('salesperson')
            ->where('shop_id', $shop->id)
            ->whereNotNull('salesperson_id')
            ->latest('issued_at')
            ->paginate(20);

        return view('seller.sellers.index', compact('shop', 'sellers', 'members', 'quota', 'stats', 'sales'));
    }

    public function store(Request $request, Shop $shop, SellerMenuService $menus, PlanLimitsService $limits): RedirectResponse
    {
        $validated = $this->validateSeller($request);
        $email = Str::lower(trim($validated['email']));
        $seller = User::query()->where('email', $email)->first();
        $wasInvited = ! $seller;
        $assignment = $shop->sellers()->firstOrNew(['user_id' => $seller?->id]);

        if (! $assignment->exists || ! $assignment->is_active) {
            $limits->assertCanAddSeller($shop);
        }

        if (! $seller) {
            $seller = User::create([
                'name' => Str::headline(Str::before($email, '@')),
                'email' => $email,
                // The invite link is the only way to choose the initial password.
                'password' => Str::random(40),
            ]);
        }
        if ($seller->id === $shop->user_id) {
            return back()->withErrors(['email' => 'El propietario no puede asignarse como vendedor.']);
        }

        $assignment = $shop->sellers()->firstOrNew(['user_id' => $seller->id]);
        $isNewAssignment = ! $assignment->exists;
        $assignment->fill([
            'commission_type' => $validated['commission_type'],
            'commission_value' => $validated['commission_value'],
            'is_active' => true,
        ])->save();
        if ($isNewAssignment) {
            $assignment->update(['menu_permissions' => $menus->assignableKeys()]);
        }

        if (! $seller->hasVerifiedEmail()) {
            $seller->notify(new SellerInvitationNotification($shop));

            $message = $wasInvited
                ? "Se creó la cuenta de {$seller->name} y se envió la invitación a {$seller->email}."
                : "{$seller->name} fue asignado y recibió una invitación para crear su contraseña.";

            return back()->with('status', $message);
        }

        return back()->with('status', "{$seller->name} fue asignado como vendedor.");
    }

    public function update(Request $request, Shop $shop, ShopSeller $seller, SellerMenuService $menus): RedirectResponse
    {
        abort_unless($seller->shop_id === $shop->id, 404);
        $validated = $this->validateCommission($request, $menus);
        $updates = [
            'commission_type' => $validated['commission_type'],
            'commission_value' => $validated['commission_value'],
        ];
        if ($request->boolean('menu_permissions_configured')) {
            $updates['menu_permissions'] = $menus->normalize($validated['menu_permissions'] ?? []);
        }

        $seller->update($updates);

        return back()->with('status', 'Vendedor actualizado. Las ventas anteriores conservan su comisión original.');
    }

    public function destroy(Shop $shop, ShopSeller $seller): RedirectResponse
    {
        abort_unless($seller->shop_id === $shop->id, 404);
        $seller->update(['is_active' => false]);

        return back()->with('status', 'Vendedor desactivado. Su historial de ventas permanece disponible.');
    }

    /** @return array<string, mixed> */
    private function validateSeller(Request $request): array
    {
        return $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            ...$this->commissionRules(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validateCommission(Request $request, SellerMenuService $menus): array
    {
        return $request->validate([
            ...$this->commissionRules(),
            'menu_permissions_configured' => ['sometimes', 'boolean'],
            'menu_permissions' => ['nullable', 'array'],
            'menu_permissions.*' => [Rule::in($menus->assignableKeys())],
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    private function commissionRules(): array
    {
        return [
            'commission_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'commission_value' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
