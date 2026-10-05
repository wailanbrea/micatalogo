<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('inventory_lots')) {
            Schema::create('inventory_lots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->unsignedBigInteger('received_quantity');
                $table->unsignedBigInteger('remaining_quantity');
                $table->unsignedBigInteger('received_cost_cents')->nullable();
                $table->unsignedBigInteger('remaining_cost_cents')->nullable();
                $table->string('quantity_unit', 10);
                $table->string('origin', 30);
                $table->timestamp('received_at');
                $table->timestamps();
                $table->index(['product_id', 'received_at', 'id']);
            });
        }
        if (! Schema::hasTable('inventory_lot_allocations')) {
            Schema::create('inventory_lot_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_lot_id')->constrained()->restrictOnDelete();
                $table->foreignId('inventory_movement_id')->constrained()->restrictOnDelete();
                $table->unsignedBigInteger('quantity');
                $table->unsignedBigInteger('cost_cents')->nullable();
            });
        }
        if (Schema::hasTable('inventory_movements') && ! Schema::hasColumn('inventory_movements', 'total_cost_cents')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->unsignedBigInteger('total_cost_cents')->nullable();
            });
        }
        if (Schema::hasTable('invoice_items')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                if (! Schema::hasColumn('invoice_items', 'total_cost_cents')) {
                    $table->unsignedBigInteger('total_cost_cents')->nullable();
                }
                if (! Schema::hasColumn('invoice_items', 'discount')) {
                    $table->decimal('discount', 14, 2)->default(0);
                }
                if (! Schema::hasColumn('invoice_items', 'general_discount_cents')) {
                    $table->unsignedBigInteger('general_discount_cents')->default(0);
                }
                if (! Schema::hasColumn('invoice_items', 'tax')) {
                    $table->decimal('tax', 14, 2)->default(0);
                }
            });
        }
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('invoices', 'discount')) {
                    $table->decimal('discount', 14, 2)->default(0);
                }
                if (! Schema::hasColumn('invoices', 'tax')) {
                    $table->decimal('tax', 14, 2)->default(0);
                }
            });
        }
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'invoice_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            });
        }
        if (! Schema::hasTable('product_price_rules')) {
            Schema::create('product_price_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->unique()->constrained()->restrictOnDelete();
                $table->decimal('margin_percent', 5, 2);
                $table->unsignedInteger('round_step_cents')->default(100);
                $table->boolean('auto_increase')->default(false);
                $table->decimal('pending_price', 14, 2)->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('product_price_changes')) {
            Schema::create('product_price_changes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('old_price', 14, 2);
                $table->decimal('new_price', 14, 2);
                $table->string('reason', 30);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Deliberately irreversible: rolling back code must not destroy lot/cost history.
        throw new RuntimeException('Esta migración conserva costos e historial. Revertir código no elimina los datos.');
    }
};
