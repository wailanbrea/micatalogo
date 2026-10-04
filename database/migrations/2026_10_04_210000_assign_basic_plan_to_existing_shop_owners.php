<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FULL_ACCESS_EMAIL = 'wailandkey@gmail.com';

    public function up(): void
    {
        $ownerIds = DB::table('shops')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('user_id');

        if ($ownerIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('id', $ownerIds)
                ->whereRaw('LOWER(email) <> ?', [self::FULL_ACCESS_EMAIL])
                ->update([
                    'plan' => 'premium',
                    'plan_expires_at' => null,
                    'updated_at' => now(),
                ]);
        }

        DB::table('users')
            ->whereRaw('LOWER(email) = ?', [self::FULL_ACCESS_EMAIL])
            ->update([
                'plan' => 'custom',
                'plan_expires_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // This official production plan assignment is intentionally not reversed.
    }
};
