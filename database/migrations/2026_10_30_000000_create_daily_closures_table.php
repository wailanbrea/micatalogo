<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_closures', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 36)->unique();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->bigInteger('expected_cash_cents')->default(0);
            $table->bigInteger('counted_cash_cents')->nullable();
            $table->bigInteger('difference_cents')->nullable();
            $table->bigInteger('sales_cash_cents')->default(0);
            $table->bigInteger('debt_collections_cash_cents')->default(0);
            $table->bigInteger('other_inflows_cash_cents')->default(0);
            $table->bigInteger('expenses_cash_cents')->default(0);
            $table->bigInteger('cash_out_cents')->default(0);
            $table->string('status', 24)->default('closed');
            $table->text('notes')->nullable();
            $table->timestamp('closed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['shop_id', 'business_date']);
            $table->index(['shop_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closures');
    }
};
