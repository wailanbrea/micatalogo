<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_inventories', 'opened_bottles')) {
            Schema::table('product_inventories', function (Blueprint $table): void {
                $table->unsignedInteger('opened_bottles')->default(0)->after('available_ml');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_inventories', 'opened_bottles')) {
            Schema::table('product_inventories', function (Blueprint $table): void {
                $table->dropColumn('opened_bottles');
            });
        }
    }
};
