<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_inventories', 'reserved_decant_ml')) {
            Schema::table('product_inventories', function (Blueprint $table): void {
                // NULL preserves legacy records until the owner reconciles their
                // historical available_ml meaning explicitly.
                $table->unsignedInteger('reserved_decant_ml')->nullable()->after('available_ml');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_inventories', 'reserved_decant_ml')) {
            Schema::table('product_inventories', function (Blueprint $table): void {
                $table->dropColumn('reserved_decant_ml');
            });
        }
    }
};
