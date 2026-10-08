<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = ['shop_id', 'name', 'invoice_currency', 'phone', 'email', 'address', 'notes', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function getRouteKeyName(): string { return 'public_id'; }
    public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
    public function purchaseDocuments(): HasMany { return $this->hasMany(PurchaseDocument::class); }
}
