<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'due_date')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->date('due_date')->nullable()->after('issued_at');
            });
        }

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 36)->unique();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method', 32); // cash, card, bank_transfer, credit, other
            $table->decimal('amount', 12, 2);
            $table->unsignedBigInteger('amount_cents');
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            $table->index(['shop_id', 'received_at']);
            $table->index(['invoice_id']);
            $table->index(['customer_id']);
            $table->index(['payment_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');

        if (Schema::hasColumn('invoices', 'due_date')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('due_date');
            });
        }
    }
};
