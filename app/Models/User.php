<?php

namespace App\Models;

use App\Enums\UserPlan;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Notifications\VerifyEmailCodeNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'status',
        'role',
        'password',
        'email_verified_at',
        'plan',
        'plan_expires_at',
        'additional_user_seats',
        'additional_seller_seats',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'status' => UserStatus::class,
            'role' => UserRole::class,
            'password' => 'hashed',
            'plan' => UserPlan::class,
            'plan_expires_at' => 'datetime',
            'additional_user_seats' => 'integer',
            'additional_seller_seats' => 'integer',
        ];
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function shopSellerAssignments(): HasMany
    {
        return $this->hasMany(ShopSeller::class);
    }

    public function shopMemberships(): HasMany
    {
        return $this->hasMany(ShopMember::class);
    }

    public function cashRegisterSessions(): HasMany
    {
        return $this->hasMany(CashRegisterSession::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function ownsShop(Shop $shop): bool
    {
        return $this->id === $shop->user_id;
    }

    public function isActiveShopMember(Shop $shop): bool
    {
        return $this->shopMemberships()
            ->where('shop_id', $shop->id)
            ->where('is_active', true)
            ->exists();
    }

    public function isActiveShopManager(Shop $shop): bool
    {
        return $this->shopMemberships()
            ->where('shop_id', $shop->id)
            ->where('role', 'manager')
            ->where('is_active', true)
            ->exists();
    }

    public function isActiveShopAccountant(Shop $shop): bool
    {
        return $this->shopMemberships()
            ->where('shop_id', $shop->id)
            ->where('role', 'accountant')
            ->where('is_active', true)
            ->exists();
    }

    public function ownsAnyShop(): bool
    {
        return $this->shops()->exists();
    }

    public function hasActiveShopAssignment(): bool
    {
        return $this->shopSellerAssignments()
            ->where('is_active', true)
            ->exists();
    }

    public function hasActiveShopMembership(): bool
    {
        return $this->shopMemberships()
            ->where('is_active', true)
            ->exists();
    }

    public function isAssignedSellerOnly(): bool
    {
        return ! $this->isAdmin() && ! $this->ownsAnyShop() && $this->hasActiveShopAssignment();
    }

    public function canSellAtShop(Shop $shop): bool
    {
        return $this->ownsShop($shop) || $this->isActiveShopManager($shop) || $this->shopSellerAssignments()
            ->where('shop_id', $shop->id)
            ->where('is_active', true)
            ->exists();
    }

    public function isPremium(): bool
    {
        return $this->plan === UserPlan::Premium && (! $this->plan_expires_at || $this->plan_expires_at->isFuture());
    }

    public function planLabel(): string
    {
        return ($this->plan instanceof UserPlan ? $this->plan : UserPlan::Free)->label();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailCodeNotification);
    }
}
