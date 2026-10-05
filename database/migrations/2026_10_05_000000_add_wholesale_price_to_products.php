<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'wholesale_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('wholesale_price', 12, 2)->nullable()->after('price');
            });
        }

        if (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'sale_mode')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('sale_mode', 20)->default('retail')->after('channel');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'wholesale_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('wholesale_price');
            });
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'sale_mode')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('sale_mode');
            });
        }
    }
};
