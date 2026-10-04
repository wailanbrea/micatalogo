<?php

namespace App\Http\Controllers;

use App\Enums\UserPlan;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $role = $request->query('role');

        $query = User::withCount('shops')->with('shops');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status && in_array($status, ['active', 'suspended'])) {
            $query->where('status', $status);
        }

        if ($role && in_array($role, ['seller', 'admin'])) {
            $query->where('role', $role);
        }

        $users = $query->latest('id')->paginate(15)->withQueryString();

        $stats = [
            'total' => User::count(),
            'active' => User::where('status', UserStatus::Active)->count(),
            'suspended' => User::where('status', UserStatus::Suspended)->count(),
            'admins' => User::where('role', UserRole::Admin)->count(),
        ];

        return view('admin.users.index', [
            'users' => $users,
            'stats' => $stats,
            'search' => $search,
            'status' => $status,
            'role' => $role,
        ]);
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes suspender tu propia cuenta de administrador.');
        }

        $newStatus = $user->status === UserStatus::Active
            ? UserStatus::Suspended
            : UserStatus::Active;

        DB::transaction(function () use ($newStatus, $user): void {
            $attributes = ['status' => $newStatus];

            if ($newStatus === UserStatus::Suspended) {
                // A suspension must invalidate every existing way to access the account.
                $attributes['remember_token'] = null;
                $user->tokens()->delete();

                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))
                        ->where('user_id', $user->getAuthIdentifier())
                        ->delete();
                }
            }

            $user->forceFill($attributes)->save();
        });

        $message = $newStatus === UserStatus::Suspended
            ? "El usuario «{$user->name}» ha sido suspendido y ya no podrá iniciar sesión."
            : "El usuario «{$user->name}» ha sido reactivado exitosamente.";

        return back()->with('status', $message);
    }

    public function toggleRole(Request $request, User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes modificar tu propio rol.');
        }

        $newRole = $user->role === UserRole::Admin
            ? UserRole::Seller
            : UserRole::Admin;

        $user->update(['role' => $newRole]);

        $roleName = $newRole === UserRole::Admin ? 'Administrador' : 'Vendedor';

        return back()->with('status', "El rol de «{$user->name}» se actualizó a {$roleName}.");
    }

    public function updatePlan(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate(['plan' => ['required', 'in:free,premium']]);
        $newPlan = UserPlan::from($validated['plan']);

        if ($newPlan === UserPlan::Free && $user->shops()->where('status', 'active')->get()->contains(
            fn ($shop): bool => $shop->products()->count() > (int) config('catalog.plans.free.max_products_per_shop', 100)
        )) {
            return back()->with('error', "No se puede cambiar a Gratis: {$user->name} tiene una tienda activa con más de 100 productos. No se eliminó ningún producto.");
        }

        $user->update([
            'plan' => $newPlan,
            'plan_expires_at' => null,
        ]);

        return back()->with('status', "El usuario «{$user->name}» ahora tiene el plan {$newPlan->label()}.");
    }
}
