<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    protected $attributes = [
        'credit_limit' => '0.00',
        'balance' => '0.00',
        'is_active' => true,
    ];

    protected $fillable = [
        'shop_id',
        'client_customer_uuid',
        'payload_sha256',
        'name',
        'first_name',
        'last_name',
        'document_type',
        'document_number',
        'phone',
        'whatsapp',
        'email',
        'address',
        'reference',
        'notes',
        'credit_limit',
        'balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function accountEntries(): HasMany
    {
        return $this->hasMany(CustomerAccountEntry::class)->latest('id');
    }
}
