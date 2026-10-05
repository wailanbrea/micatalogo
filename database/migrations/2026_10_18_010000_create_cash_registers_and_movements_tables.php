<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 36)->unique();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_amount', 12, 2)->default(0);
            $table->bigInteger('opening_amount_cents')->default(0);
            $table->decimal('expected_closing_amount', 12, 2)->nullable();
            $table->bigInteger('expected_closing_amount_cents')->nullable();
            $table->decimal('counted_closing_amount', 12, 2)->nullable();
            $table->bigInteger('counted_closing_amount_cents')->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->bigInteger('difference_cents')->nullable();
            $table->string('status', 16)->default('open'); // open, closed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['opened_at']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 36)->unique();
            $table->foreignId('cash_register_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32); // sale, customer_payment, expense, supplier_payment, cash_in, cash_out, owner_contribution, owner_withdrawal, adjustment
            $table->decimal('amount', 12, 2);
            $table->bigInteger('amount_cents');
            $table->string('reference_type', 64)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['cash_register_session_id']);
            $table->index(['shop_id', 'occurred_at']);
            $table->index(['type']);
        });

        if (Schema::hasTable('invoice_payments') && ! Schema::hasColumn('invoice_payments', 'cash_register_session_id')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                $table->foreignId('cash_register_session_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoice_payments', 'cash_register_session_id')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                $table->dropForeign(['cash_register_session_id']);
                $table->dropColumn('cash_register_session_id');
            });
        }

        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_register_sessions');
    }
};
