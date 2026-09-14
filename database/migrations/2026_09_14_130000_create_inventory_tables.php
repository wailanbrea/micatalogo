<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete()->unique();
            $table->boolean('track_inventory')->default(true)->index();
            $table->decimal('cost_price', 12, 2)->nullable(); // Precio de compra unitario (privado del vendedor)
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('sold_quantity')->default(0);
            $table->unsignedSmallInteger('low_stock_threshold')->default(3);
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->index(); // 'sale', 'restock', 'adjustment'
            $table->integer('quantity');
            $table->unsignedInteger('stock_before');
            $table->unsignedInteger('stock_after');
            $table->string('notes', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['product_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('product_inventories');
    }
};
