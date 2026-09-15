<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->decimal('unit_price', 12, 2)->nullable()->after('stock_after');
            $table->decimal('unit_cost', 12, 2)->nullable()->after('unit_price');
        });

        DB::table('inventory_movements')
            ->where('inventory_movements.type', 'sale')
            ->update([
                'unit_price' => DB::raw('(SELECT price FROM products WHERE products.id = inventory_movements.product_id)'),
                'unit_cost' => DB::raw('(SELECT cost_price FROM product_inventories WHERE product_inventories.product_id = inventory_movements.product_id)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'unit_cost']);
        });
    }
};
