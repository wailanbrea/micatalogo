<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attribute_definitions', 'is_active')) {
            Schema::table('attribute_definitions', function (Blueprint $table): void {
                $table->boolean('is_active')->default(true)->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attribute_definitions', 'is_active')) {
            Schema::table('attribute_definitions', function (Blueprint $table): void {
                $table->dropColumn('is_active');
            });
        }
    }
};
