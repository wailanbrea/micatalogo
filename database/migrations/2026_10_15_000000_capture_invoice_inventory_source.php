<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoice_items', 'inventory_source_product_id')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->unsignedBigInteger('inventory_source_product_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('La fuente de una venta es un dato histórico; no se elimina al revertir código.');
    }
};
