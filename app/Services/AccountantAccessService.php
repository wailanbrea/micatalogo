<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use App\Notifications\SellerInvitationNotification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountantAccessService
{
    /**
     * Grant read-only financial access without consuming a regular user seat.
     *
     * @return array{user: User, membership: ShopMember, was_invited: bool, already_active: bool}
     */
    public function grant(Shop $shop, string $email): array
    {
        $email = Str::lower(trim($email));
        $user = User::query()->where('email', $email)->first();

        if ($user?->id === $shop->user_id) {
            throw ValidationException::withMessages(['email' => 'El propietario ya tiene acceso completo.']);
        }

        if ($user && $shop->sellers()->where('user_id', $user->id)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['email' => 'Este usuario ya está asignado como vendedor.']);
        }

        $wasInvited = ! $user;
        if (! $user) {
            $user = User::create([
                'name' => Str::headline(Str::before($email, '@')),
                'email' => $email,
                'password' => Str::random(40),
            ]);
        }

        $membership = $shop->members()->firstOrNew(['user_id' => $user->id]);
        if ($membership->exists && $membership->is_active && $membership->role !== 'accountant') {
            throw ValidationException::withMessages(['email' => 'Este usuario ya tiene acceso administrativo a la tienda.']);
        }

        $alreadyActive = $membership->exists && $membership->is_active && $membership->role === 'accountant';
        $membership->fill(['role' => 'accountant', 'is_active' => true])->save();

        if (! $alreadyActive && ! $user->hasVerifiedEmail()) {
            $user->notify(new SellerInvitationNotification($shop, 'contador de solo lectura'));
        }

        return compact('user', 'membership', 'wasInvited', 'alreadyActive');
    }

    public function revoke(Shop $shop, ShopMember $membership): void
    {
        abort_unless($membership->shop_id === $shop->id && $membership->role === 'accountant', 404);
        $membership->update(['is_active' => false]);
    }
}
