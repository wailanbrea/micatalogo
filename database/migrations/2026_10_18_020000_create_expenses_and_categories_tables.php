<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['shop_id', 'name']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 36)->unique();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_register_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 255);
            $table->decimal('amount', 12, 2);
            $table->bigInteger('amount_cents');
            $table->string('payment_status', 16)->default('paid'); // paid, partial, pending
            $table->string('payment_method', 32)->nullable(); // cash, card, bank_transfer, other
            $table->string('reference', 120)->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'occurred_at']);
            $table->index(['expense_category_id']);
            $table->index(['payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
