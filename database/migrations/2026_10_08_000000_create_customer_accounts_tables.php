<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
                $table->uuid('client_customer_uuid')->nullable();
                $table->char('payload_sha256', 64)->nullable();
                $table->string('name', 120);
                $table->string('phone', 30)->nullable();
                $table->string('email')->nullable();
                $table->text('address')->nullable();
                $table->text('notes')->nullable();
                $table->decimal('credit_limit', 12, 2)->default(0);
                $table->decimal('balance', 12, 2)->default(0)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['shop_id', 'client_customer_uuid']);
                $table->index(['shop_id', 'name']);
            });
        }

        if (! Schema::hasColumn('invoices', 'customer_id')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->foreignId('customer_id')->nullable()->after('shop_id');
                $table->foreign('customer_id', 'invoices_customer_id_foreign')
                    ->references('id')
                    ->on('customers')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('customer_account_entries')) {
            Schema::create('customer_account_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->restrictOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->uuid('client_transaction_uuid')->nullable();
                $table->char('payload_sha256', 64)->nullable();
                $table->string('type', 20);
                $table->decimal('amount', 12, 2);
                $table->decimal('balance_after', 12, 2);
                $table->string('notes', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['shop_id', 'client_transaction_uuid']);
                $table->unique(['invoice_id', 'type']);
                $table->index(['customer_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_account_entries');

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::dropIfExists('customers');
    }
};
