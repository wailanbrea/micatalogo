<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLot extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'received_quantity' => 'integer',
            'remaining_quantity' => 'integer', 'received_cost_cents' => 'integer',
            'remaining_cost_cents' => 'integer'];
    }
}
