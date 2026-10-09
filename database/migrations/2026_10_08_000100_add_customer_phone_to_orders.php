<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'customer_phone')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('customer_phone', 30)->nullable()->after('customer_name');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'customer_phone')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('customer_phone');
            });
        }
    }
};
