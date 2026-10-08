<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizationRequest extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'shop_id',
        'requested_by',
        'decided_by',
        'action',
        'context',
        'status',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
