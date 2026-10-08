<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table): void {
            $table->decimal('exchange_rate', 14, 6)->nullable()->after('currency');
            $table->string('carrier', 160)->nullable()->after('exchange_rate');
            $table->string('tracking_number', 120)->nullable()->after('carrier');
            $table->date('expected_at')->nullable()->after('tracking_number');
            $table->decimal('shipping_pounds', 14, 3)->nullable()->after('expected_at');
            $table->decimal('freight_amount', 14, 2)->default(0)->after('shipping_pounds');
            $table->decimal('customs_amount', 14, 2)->default(0)->after('freight_amount');
            $table->string('payment_status', 20)->default('pending')->after('customs_amount');
            $table->foreignId('parent_document_id')->nullable()->after('payment_status')
                ->constrained('purchase_documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table): void {
            $table->dropForeign(['parent_document_id']);
            $table->dropColumn([
                'exchange_rate',
                'carrier',
                'tracking_number',
                'expected_at',
                'shipping_pounds',
                'freight_amount',
                'customs_amount',
                'payment_status',
                'parent_document_id',
            ]);
        });
    }
};
