<?php

namespace App\Models;

use App\Services\MediaStorageService;
use App\Services\PlanLimitsService;
use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'logo_object_key', 'cover_object_key',
        'primary_color', 'secondary_color', 'whatsapp_country_code', 'whatsapp_number',
        'offers_shipping', 'instagram', 'address', 'maps_url', 'status', 'discovery_enabled',
        'product_limit', 'inventory_import_mapping',
        'business_type', 'business_capability_overrides', 'business_profile_version', 'onboarding_completed_at',
    ];

    protected $attributes = [
        'discovery_enabled' => false,
    ];

    protected function casts(): array
    {
        return [
            'offers_shipping' => 'boolean',
            'discovery_enabled' => 'boolean',
            'product_limit' => 'integer',
            'inventory_import_mapping' => 'array',
            'business_capability_overrides' => 'array',
            'business_profile_version' => 'integer',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_object_key) {
            return null;
        }

        return app(MediaStorageService::class)->url($this->logo_object_key);
    }

    public function getCoverUrlAttribute(): ?string
    {
        if (! $this->cover_object_key) {
            return null;
        }

        return app(MediaStorageService::class)->url($this->cover_object_key);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ShopCategory::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function attributeDefinitions(): HasMany
    {
        return $this->hasMany(AttributeDefinition::class)->orderBy('display_order')->orderBy('name');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function sellers(): HasMany
    {
        return $this->hasMany(ShopSeller::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ShopMember::class);
    }

    public function importSessions(): HasMany
    {
        return $this->hasMany(InventoryImportSession::class)->latest();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function invoicePayments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function cashRegisterSessions(): HasMany
    {
        return $this->hasMany(CashRegisterSession::class)->latest('opened_at');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class)->latest('occurred_at');
    }

    public function currentCashSession(): ?CashRegisterSession
    {
        return $this->cashRegisterSessions()->where('status', 'open')->first();
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class)->latest('occurred_at');
    }

    public function expensePayments(): HasMany
    {
        return $this->hasMany(ExpensePayment::class);
    }

    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class)->where('is_active', true)->orderBy('name');
    }

    public function productLimit(): int
    {
        return app(PlanLimitsService::class)->productLimit($this);
    }

    public function imageLimit(): int
    {
        return app(PlanLimitsService::class)->imageLimit($this);
    }

    public function planLabel(): string
    {
        return $this->user->planLabel();
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(ShopDailyMetric::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }
}
