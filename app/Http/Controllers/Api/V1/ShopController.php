<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopSeller;
use App\Models\User;
use App\Notifications\SellerInvitationNotification;
use App\Services\PlanLimitsService;
use App\Services\BusinessCapabilityService;
use App\Services\SellerMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShopController extends Controller
{
    public function index(Request $request, PlanLimitsService $limits, SellerMenuService $menus, BusinessCapabilityService $capabilities): JsonResponse
    {
        $query = Shop::query()
            ->with(['user', 'sellers.user'])
            ->withCount('products')
            ->orderBy('name');

        // The mobile client has no global shop selector. Keep platform admins
        // scoped to shops they can operate instead of returning every tenant.
        $query->where(function ($query) use ($request): void {
            $query->where('user_id', $request->user()->id)
                ->orWhereHas('members', fn ($members) => $members
                    ->where('user_id', $request->user()->id)
                    ->where('is_active', true))
                ->orWhereHas('sellers', fn ($sellers) => $sellers
                    ->where('user_id', $request->user()->id)
                    ->where('is_active', true));
        });

        $shops = $query->get(['id', 'user_id', 'public_id', 'name', 'slug'])
            ->map(function ($shop) use ($limits, $menus, $request, $capabilities): array {
                $canManage = $menus->canManage($shop, $request->user());

                return [
                    'id' => $shop->public_id,
                    'name' => $shop->name,
                    'slug' => $shop->slug,
                    ...$capabilities->payload($shop),
                    'quota' => $limits->shopQuota($shop, $shop->products_count),
                    'menu_permissions' => $menus->visibleForUser($shop, $request->user()),
                    'can_manage_sellers' => $canManage,
                    'sellers' => $canManage ? $shop->sellers->map(fn ($seller): array => [
                        'id' => (string) $seller->id,
                        'user_id' => (string) $seller->user_id,
                        'name' => $seller->user->name,
                        'email' => $seller->user->email,
                        'is_active' => $seller->is_active,
                        'menu_permissions' => $menus->forAssignment($seller),
                    ])->values()->all() : [],
                ];
            })
            ->values();

        return response()->json($shops);
    }

    public function updateSellerMenus(Request $request, Shop $shop, ShopSeller $seller, SellerMenuService $menus): JsonResponse
    {
        abort_unless($menus->canManage($shop, $request->user()), 403);
        abort_unless($seller->shop_id === $shop->id, 404);

        $validated = $request->validate([
            'menu_permissions' => ['present', 'array'],
            'menu_permissions.*' => [Rule::in($menus->assignableKeys())],
        ]);
        $seller->update(['menu_permissions' => $menus->normalize($validated['menu_permissions'])]);

        return response()->json([
            'menu_permissions' => $menus->forAssignment($seller->fresh()),
        ]);
    }

    public function storeSeller(Request $request, Shop $shop, SellerMenuService $menus, PlanLimitsService $limits): JsonResponse
    {
        abort_unless($menus->canManage($shop, $request->user()), 403);

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'commission_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'commission_value' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
        ]);
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
                'password' => Str::random(40),
            ]);
        }
        abort_if($seller->id === $shop->user_id, 422, 'El propietario no puede asignarse como vendedor.');

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
        }

        return response()->json([
            'message' => $wasInvited
                ? "Se creó la cuenta de {$seller->name} y se envió la invitación a {$seller->email}."
                : "{$seller->name} fue asignado como vendedor.",
            'seller' => [
                'id' => (string) $assignment->id,
                'user_id' => (string) $seller->id,
                'name' => $seller->name,
                'email' => $seller->email,
                'is_active' => $assignment->is_active,
                'menu_permissions' => $menus->forAssignment($assignment),
            ],
        ], $wasInvited ? 201 : 200);
    }
}
