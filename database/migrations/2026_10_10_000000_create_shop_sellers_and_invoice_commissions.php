<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_sellers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('commission_type', 12);
            $table->decimal('commission_value', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['shop_id', 'user_id']);
            $table->index(['shop_id', 'is_active']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('salesperson_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('commission_type', 12)->nullable()->after('currency');
            $table->decimal('commission_value', 12, 2)->nullable()->after('commission_type');
            $table->decimal('commission_amount', 12, 2)->nullable()->after('commission_value');
            $table->index(['shop_id', 'salesperson_id']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex(['shop_id', 'salesperson_id']);
            $table->dropConstrainedForeignId('salesperson_id');
            $table->dropColumn(['commission_type', 'commission_value', 'commission_amount']);
        });

        Schema::dropIfExists('shop_sellers');
    }
};
