<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const AROMAS_PRIVITY_PUBLIC_ID = '01M3YFQQQR875G7D3ZM0F3W40S';

    private const LEGACY_TARGET_PUBLIC_ID = '01M3XPRA8P8AQ9H21QWS58A168';

    public function up(): void
    {
        $legacyOwnerIds = DB::table('shops')
            ->where('public_id', self::LEGACY_TARGET_PUBLIC_ID)
            ->pluck('user_id');

        if ($legacyOwnerIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('id', $legacyOwnerIds)
                ->update([
                    'plan' => 'free',
                    'plan_expires_at' => null,
                    'updated_at' => now(),
                ]);
        }

        $aromasOwnerIds = DB::table('shops')
            ->where('public_id', self::AROMAS_PRIVITY_PUBLIC_ID)
            ->pluck('user_id');

        if ($aromasOwnerIds->isEmpty()) {
            return;
        }

        DB::table('users')
            ->whereIn('id', $aromasOwnerIds)
            ->update([
                'plan' => 'premium',
                'plan_expires_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // This production data correction is intentionally not reversed.
    }
};
