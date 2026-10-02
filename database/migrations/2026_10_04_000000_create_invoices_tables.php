<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number', 40)->unique();
            $table->string('status', 20)->default('paid')->index();
            $table->string('channel', 30)->default('whatsapp');
            $table->char('currency', 3)->default('DOP');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamp('issued_at')->index();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('product_code')->nullable();
            $table->string('sale_unit', 20)->default('unit');
            $table->unsignedInteger('volume_ml')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->foreignId('invoice_id')->nullable()->after('product_id')->constrained()->nullOnDelete()->index();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('invoice_id');
        });
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
