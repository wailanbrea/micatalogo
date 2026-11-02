<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_account_entries')) {
            Schema::table('customer_account_entries', function (Blueprint $table): void {
                $table->index(['invoice_id', 'type'], 'customer_account_entries_invoice_id_type_index');
                $table->dropUnique('customer_account_entries_invoice_id_type_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_account_entries')) {
            Schema::table('customer_account_entries', function (Blueprint $table): void {
                $table->unique(['invoice_id', 'type'], 'customer_account_entries_invoice_id_type_unique');
                $table->dropIndex('customer_account_entries_invoice_id_type_index');
            });
        }
    }
};
