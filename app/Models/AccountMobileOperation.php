<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountMobileOperation extends Model
{
    protected $fillable = ['user_id', 'client_operation_uuid', 'type', 'payload', 'payload_sha256', 'result'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'result' => 'array'];
    }
}
