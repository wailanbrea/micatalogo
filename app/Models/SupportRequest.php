<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportRequest extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'user_id',
        'shop_id',
        'requester_type',
        'category',
        'requester_name',
        'requester_email',
        'subject',
        'message',
        'status',
        'resolution_notes',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
