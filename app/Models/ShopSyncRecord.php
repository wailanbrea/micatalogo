<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopSyncRecord extends Model
{
    protected $fillable = ['shop_id', 'kind', 'record_uuid', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
