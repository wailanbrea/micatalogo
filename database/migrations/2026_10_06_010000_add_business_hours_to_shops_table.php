<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shops', 'business_hours')) {
            Schema::table('shops', function (Blueprint $table): void {
                $table->json('business_hours')->nullable()->after('maps_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shops', 'business_hours')) {
            Schema::table('shops', function (Blueprint $table): void {
                $table->dropColumn('business_hours');
            });
        }
    }
};
