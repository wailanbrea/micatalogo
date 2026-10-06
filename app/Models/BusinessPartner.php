<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessPartner extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = ['shop_id', 'name', 'email', 'phone', 'ownership_percent', 'is_active'];
    protected function casts(): array { return ['ownership_percent' => 'decimal:4', 'is_active' => 'boolean']; }
    public function getRouteKeyName(): string { return 'public_id'; }
    public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
    public function transactions(): HasMany { return $this->hasMany(PartnerTransaction::class, 'partner_id'); }
}
