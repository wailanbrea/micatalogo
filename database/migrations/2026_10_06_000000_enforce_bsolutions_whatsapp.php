<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('shops')
            ->where('slug', 'bsolutions-dev')
            ->update([
                'whatsapp_country_code' => '1',
                'whatsapp_number' => '8298144525',
            ]);
    }

    public function down(): void
    {
        // Keep the rollback deterministic without touching any other shop.
        DB::table('shops')
            ->where('slug', 'bsolutions-dev')
            ->update([
                'whatsapp_country_code' => '1',
                'whatsapp_number' => '8095550100',
            ]);
    }
};
