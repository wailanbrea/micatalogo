<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shops', 'operational_settings')) {
            Schema::table('shops', function (Blueprint $table): void {
                $table->json('operational_settings')->nullable()->after('business_hours');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shops', 'operational_settings')) {
            Schema::table('shops', function (Blueprint $table): void {
                $table->dropColumn('operational_settings');
            });
        }
    }
};
