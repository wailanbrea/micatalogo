<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SHOP_PUBLIC_ID = '01M3XPRA8P8AQ9H21QWS58A168';

    public function up(): void
    {
        $ownerIds = DB::table('shops')
            ->where('public_id', self::SHOP_PUBLIC_ID)
            ->pluck('user_id');

        if ($ownerIds->isEmpty()) {
            return;
        }

        DB::table('users')
            ->whereIn('id', $ownerIds)
            ->update([
                'plan' => 'premium',
                'plan_expires_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // This official production promotion is intentionally not reversed.
    }
};
