<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Expense Payments table
        if (! Schema::hasTable('expense_payments')) {
            Schema::create('expense_payments', function (Blueprint $table) {
                $table->id();
                $table->string('public_id', 36)->unique();
                $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
                $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('cash_register_session_id')->nullable()->constrained()->nullOnDelete();
                $table->string('payment_method', 32); // cash, card, bank_transfer, other
                $table->decimal('amount', 12, 2);
                $table->unsignedBigInteger('amount_cents');
                $table->string('reference', 120)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('paid_at')->useCurrent();
                $table->uuid('client_operation_uuid')->nullable()->index();
                $table->string('payload_sha256', 64)->nullable();
                $table->timestamps();

                $table->index(['shop_id', 'paid_at']);
                $table->index(['expense_id']);
                $table->index(['payment_method']);
            });
        }

        // 2. Enhance expenses with payments tracking & idempotency
        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                if (! Schema::hasColumn('expenses', 'amount_paid')) {
                    $table->decimal('amount_paid', 12, 2)->default(0)->after('amount');
                }
                if (! Schema::hasColumn('expenses', 'amount_paid_cents')) {
                    $table->unsignedBigInteger('amount_paid_cents')->default(0)->after('amount_cents');
                }
                if (! Schema::hasColumn('expenses', 'client_operation_uuid')) {
                    $table->uuid('client_operation_uuid')->nullable()->index()->after('notes');
                }
                if (! Schema::hasColumn('expenses', 'payload_sha256')) {
                    $table->string('payload_sha256', 64)->nullable()->after('client_operation_uuid');
                }
            });

            // Backfill existing expenses: if payment_status == 'paid', amount_paid_cents = amount_cents
            DB::table('expenses')->where('payment_status', 'paid')->update([
                'amount_paid' => DB::raw('amount'),
                'amount_paid_cents' => DB::raw('amount_cents'),
            ]);
        }

        // 3. Enhance invoice_payments with idempotency
        if (Schema::hasTable('invoice_payments')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                if (! Schema::hasColumn('invoice_payments', 'client_operation_uuid')) {
                    $table->uuid('client_operation_uuid')->nullable()->index()->after('notes');
                }
                if (! Schema::hasColumn('invoice_payments', 'payload_sha256')) {
                    $table->string('payload_sha256', 64)->nullable()->after('client_operation_uuid');
                }
            });
        }

        // 4. Enhance cash_movements with idempotency
        if (Schema::hasTable('cash_movements')) {
            Schema::table('cash_movements', function (Blueprint $table) {
                if (! Schema::hasColumn('cash_movements', 'client_operation_uuid')) {
                    $table->uuid('client_operation_uuid')->nullable()->index()->after('notes');
                }
                if (! Schema::hasColumn('cash_movements', 'payload_sha256')) {
                    $table->string('payload_sha256', 64)->nullable()->after('client_operation_uuid');
                }
            });
        }

        // 5. Enhance cash_register_sessions with idempotency and concurrent opening guard
        if (Schema::hasTable('cash_register_sessions')) {
            Schema::table('cash_register_sessions', function (Blueprint $table) {
                if (! Schema::hasColumn('cash_register_sessions', 'client_operation_uuid')) {
                    $table->uuid('client_operation_uuid')->nullable()->index()->after('notes');
                }
                if (! Schema::hasColumn('cash_register_sessions', 'payload_sha256')) {
                    $table->string('payload_sha256', 64)->nullable()->after('client_operation_uuid');
                }
                if (! Schema::hasColumn('cash_register_sessions', 'is_open_flag')) {
                    $table->unsignedTinyInteger('is_open_flag')->nullable()->after('status');
                }
            });

            // Set is_open_flag = 1 for open sessions, null for closed sessions
            DB::table('cash_register_sessions')->where('status', 'open')->update(['is_open_flag' => 1]);
            DB::table('cash_register_sessions')->where('status', '!=', 'open')->update(['is_open_flag' => null]);

            // Add unique index on [shop_id, user_id, is_open_flag] to guard concurrency
            // In MySQL, multiple nulls are allowed in a unique index
            Schema::table('cash_register_sessions', function (Blueprint $table) {
                $table->unique(['shop_id', 'user_id', 'is_open_flag'], 'unique_open_cash_session_per_user');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cash_register_sessions')) {
            Schema::table('cash_register_sessions', function (Blueprint $table) {
                $table->dropUnique('unique_open_cash_session_per_user');
                $table->dropColumn(['client_operation_uuid', 'payload_sha256', 'is_open_flag']);
            });
        }

        if (Schema::hasTable('cash_movements')) {
            Schema::table('cash_movements', function (Blueprint $table) {
                $table->dropColumn(['client_operation_uuid', 'payload_sha256']);
            });
        }

        if (Schema::hasTable('invoice_payments')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                $table->dropColumn(['client_operation_uuid', 'payload_sha256']);
            });
        }

        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropColumn(['amount_paid', 'amount_paid_cents', 'client_operation_uuid', 'payload_sha256']);
            });
        }

        Schema::dropIfExists('expense_payments');
    }
};
