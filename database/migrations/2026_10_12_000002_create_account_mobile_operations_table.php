<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_mobile_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_operation_uuid');
            $table->string('type', 40);
            $table->json('payload');
            $table->string('payload_sha256', 64);
            $table->json('result');
            $table->timestamps();
            $table->unique(['user_id', 'client_operation_uuid'], 'account_mobile_operation_identity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_mobile_operations');
    }
};
