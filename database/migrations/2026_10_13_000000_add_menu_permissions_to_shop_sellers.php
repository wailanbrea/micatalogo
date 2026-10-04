<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_sellers', function (Blueprint $table): void {
            $table->json('menu_permissions')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('shop_sellers', function (Blueprint $table): void {
            $table->dropColumn('menu_permissions');
        });
    }
};
