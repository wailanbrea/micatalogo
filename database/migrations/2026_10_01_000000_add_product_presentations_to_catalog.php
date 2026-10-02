<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sale_unit', 20)->default('unit')->after('price')->index();
            $table->unsignedInteger('volume_ml')->nullable()->after('sale_unit');
            $table->foreignId('inventory_source_product_id')
                ->nullable()
                ->after('volume_ml')
                ->constrained('products')
                ->nullOnDelete();
        });

        Schema::table('product_inventories', function (Blueprint $table) {
            $table->unsignedInteger('available_ml')->nullable()->after('stock_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('product_inventories', function (Blueprint $table) {
            $table->dropColumn('available_ml');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['inventory_source_product_id']);
            $table->dropColumn(['sale_unit', 'volume_ml', 'inventory_source_product_id']);
        });
    }
};
