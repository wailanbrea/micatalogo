<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decant_vials', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('shop_id')->constrained();
            $table->unsignedInteger('volume_ml');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['shop_id', 'volume_ml']);
        });
        Schema::create('decant_vial_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vial_id')->constrained('decant_vials');
            $table->unsignedInteger('received_quantity');
            $table->unsignedInteger('remaining_quantity');
            $table->unsignedBigInteger('received_cost_cents');
            $table->unsignedBigInteger('remaining_cost_cents');
            $table->timestamp('received_at');
            $table->timestamps();
        });
        Schema::create('decant_openings', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('movement_id')->constrained('inventory_movements');
            $table->unsignedInteger('initial_ml');
            $table->unsignedInteger('remaining_ml');
            $table->unsignedBigInteger('initial_cost_cents')->nullable();
            $table->unsignedBigInteger('remaining_cost_cents')->nullable();
            $table->unsignedInteger('lost_ml')->default(0);
            $table->string('status')->default('open');
            $table->timestamps();
        });
        Schema::create('decant_batches', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('opening_id')->nullable()->constrained('decant_openings');
            $table->foreignId('movement_id')->constrained('inventory_movements');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('remaining_quantity');
            $table->unsignedBigInteger('cost_cents')->nullable();
            $table->unsignedBigInteger('remaining_cost_cents')->nullable();
            $table->unsignedBigInteger('price_cents');
            $table->timestamps();
        });
        Schema::create('decant_batch_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('decant_batches');
            $table->foreignId('movement_id')->constrained('inventory_movements');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('cost_cents')->nullable();
        });
        Schema::create('decant_vial_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lot_id')->constrained('decant_vial_lots');
            $table->foreignId('batch_id')->constrained('decant_batches');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('cost_cents');
        });
        Schema::create('decant_presentation_settings', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained();
            $table->boolean('on_demand')->default(true);
            $table->boolean('offered')->default(true);
            $table->boolean('price_locked')->default(false);
        });
        Schema::create('decant_remainder_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opening_id')->constrained('decant_openings');
            $table->foreignId('movement_id')->unique()->constrained('inventory_movements');
            $table->unsignedInteger('volume_ml');
            $table->unsignedBigInteger('cost_cents')->nullable();
        });
    }

    public function down(): void
    {
        // Rollback is intentionally not automatic: these tables hold inventory history.
        throw new RuntimeException('Conserva y concilia el historial de Decants antes de retirar su esquema.');
    }
};
