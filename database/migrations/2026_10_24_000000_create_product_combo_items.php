<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_combo')->default(false)->after('sale_unit')->index();
        });

        Schema::create('product_combo_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
            $table->unique(['product_id', 'component_product_id']);
            $table->index('component_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_combo_items');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('is_combo');
        });
    }
};
