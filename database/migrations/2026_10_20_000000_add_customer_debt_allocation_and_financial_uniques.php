<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Link invoice_payments to customer_account_entries for debt payment allocations
        if (Schema::hasTable('invoice_payments') && ! Schema::hasColumn('invoice_payments', 'customer_account_entry_id')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                $table->foreignId('customer_account_entry_id')
                    ->nullable()
                    ->after('customer_id')
                    ->constrained('customer_account_entries')
                    ->nullOnDelete();
            });
        }

        // 2. Add composite UNIQUE constraint [shop_id, client_operation_uuid] on expenses
        if (Schema::hasTable('expenses') && ! Schema::hasIndex('expenses', 'unique_expense_shop_client_uuid')) {
            Schema::table('expenses', function (Blueprint $table) {
                // Drop non-unique index if it was created standalone
                $table->unique(['shop_id', 'client_operation_uuid'], 'unique_expense_shop_client_uuid');
            });
        }

        // 3. Add composite UNIQUE constraint [shop_id, client_operation_uuid] on expense_payments
        if (Schema::hasTable('expense_payments') && ! Schema::hasIndex('expense_payments', 'unique_expense_payment_shop_client_uuid')) {
            Schema::table('expense_payments', function (Blueprint $table) {
                $table->unique(['shop_id', 'client_operation_uuid'], 'unique_expense_payment_shop_client_uuid');
            });
        }

        // 4. Add composite UNIQUE constraint [shop_id, client_operation_uuid] on cash_movements
        if (Schema::hasTable('cash_movements') && ! Schema::hasIndex('cash_movements', 'unique_cash_movement_shop_client_uuid')) {
            Schema::table('cash_movements', function (Blueprint $table) {
                $table->unique(['shop_id', 'client_operation_uuid'], 'unique_cash_movement_shop_client_uuid');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cash_movements') && Schema::hasIndex('cash_movements', 'unique_cash_movement_shop_client_uuid')) {
            Schema::table('cash_movements', function (Blueprint $table) {
                $table->dropUnique('unique_cash_movement_shop_client_uuid');
            });
        }

        if (Schema::hasTable('expense_payments') && Schema::hasIndex('expense_payments', 'unique_expense_payment_shop_client_uuid')) {
            Schema::table('expense_payments', function (Blueprint $table) {
                $table->dropUnique('unique_expense_payment_shop_client_uuid');
            });
        }

        if (Schema::hasTable('expenses') && Schema::hasIndex('expenses', 'unique_expense_shop_client_uuid')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropUnique('unique_expense_shop_client_uuid');
            });
        }

        if (Schema::hasTable('invoice_payments') && Schema::hasColumn('invoice_payments', 'customer_account_entry_id')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                $table->dropForeign(['customer_account_entry_id']);
                $table->dropColumn('customer_account_entry_id');
            });
        }
    }
};
