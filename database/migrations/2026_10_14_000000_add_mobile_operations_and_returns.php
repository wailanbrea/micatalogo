<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mobile_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->uuid('client_operation_uuid');
            $table->string('type', 30);
            $table->string('payload_sha256', 64);
            $table->json('result');
            $table->timestamps();
            $table->unique(['shop_id', 'client_operation_uuid']);
        });
        Schema::create('invoice_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('mobile_operation_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('total', 14, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('invoice_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_return_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('refund', 14, 2);
            $table->decimal('tax_refund', 14, 2)->default(0);
            $table->unsignedBigInteger('total_cost_cents')->nullable();
            $table->boolean('restock');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Los movimientos sincronizados y las devoluciones son históricos; no se purgan al revertir código.');
    }
};
