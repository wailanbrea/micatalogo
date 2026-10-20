<?php

namespace App\Models;

use App\Traits\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class DailyClosureAdjustment extends Model
{
    use HasPublicId;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expected_cash_cents' => 'integer', 'counted_cash_cents' => 'integer', 'difference_cents' => 'integer',
            'snapshot' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Los ajustes de conciliación son inmutables. Registra otro ajuste.'));
        static::deleting(fn () => throw new \LogicException('No se elimina el historial de conciliación.'));
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function previous(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_adjustment_id');
    }
}
