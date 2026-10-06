<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_quotes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('converted_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('quote_number', 40);
            $table->string('status', 20)->default('draft')->index();
            $table->string('customer_name', 120)->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->char('currency', 3)->default('DOP');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['shop_id', 'quote_number']);
            $table->index(['shop_id', 'created_at']);
        });

        Schema::create('commercial_quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_id')->constrained('commercial_quotes')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('product_code')->nullable();
            $table->string('sale_unit', 20)->default('unit');
            $table->unsignedInteger('volume_ml')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['shop_id', 'name']);
        });

        Schema::create('purchase_documents', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_number', 80);
            $table->string('type', 20)->index();
            $table->string('status', 20)->default('received')->index();
            $table->char('currency', 3)->default('DOP');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['shop_id', 'document_number']);
        });

        Schema::create('purchase_document_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        Schema::create('business_partners', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->decimal('ownership_percent', 7, 4)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['shop_id', 'name']);
        });

        Schema::create('partner_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('business_partners')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->index();
            $table->decimal('amount', 14, 2);
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_transactions');
        Schema::dropIfExists('business_partners');
        Schema::dropIfExists('purchase_document_items');
        Schema::dropIfExists('purchase_documents');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('commercial_quote_items');
        Schema::dropIfExists('commercial_quotes');
    }
};
