<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customer_account_entries', 'reverses_entry_id')) {
            Schema::table('customer_account_entries', function (Blueprint $table) {
                $table->foreignId('reverses_entry_id')->nullable()->unique()->constrained('customer_account_entries')->restrictOnDelete();
            });
        }
        if (! Schema::hasColumn('customer_account_entries', 'payment_method')) {
            Schema::table('customer_account_entries', fn (Blueprint $table) => $table->string('payment_method', 32)->nullable());
        }
        if (! Schema::hasColumn('customer_account_entries', 'reversed_at')) {
            Schema::table('customer_account_entries', fn (Blueprint $table) => $table->timestamp('reversed_at')->nullable()->index());
        }
        if (! Schema::hasColumn('invoice_payments', 'reversed_at')) {
            Schema::table('invoice_payments', fn (Blueprint $table) => $table->timestamp('reversed_at')->nullable()->index());
        }
    }

    public function down(): void
    {
        throw new LogicException('Conserva la auditoría de abonos; revierte el código sin eliminar sus datos.');
    }
};
