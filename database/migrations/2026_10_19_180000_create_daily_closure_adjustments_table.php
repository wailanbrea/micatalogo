<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_closure_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->foreignId('daily_closure_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('previous_adjustment_id')->nullable()->constrained('daily_closure_adjustments')->restrictOnDelete();
            $table->uuid('client_operation_uuid');
            $table->char('payload_sha256', 64);
            $table->string('reason', 1000);
            $table->bigInteger('expected_cash_cents');
            $table->bigInteger('counted_cash_cents')->nullable();
            $table->bigInteger('difference_cents')->nullable();
            $table->json('snapshot');
            $table->timestamp('created_at');
            $table->unique(['shop_id', 'client_operation_uuid'], 'daily_adjustments_shop_uuid_unique');
            $table->index(['daily_closure_id', 'id']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('daily_closure_adjustments') && \Illuminate\Support\Facades\DB::table('daily_closure_adjustments')->exists()) {
            throw new \LogicException('Conserva el historial auditado: revierte el código sin eliminar esta tabla con datos.');
        }
        Schema::dropIfExists('daily_closure_adjustments');
    }
};
