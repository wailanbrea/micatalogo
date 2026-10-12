<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_sync_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('record_uuid', 36);
            $table->json('payload');
            $table->timestamps();
            $table->unique(['shop_id', 'kind', 'record_uuid'], 'shop_sync_record_identity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_sync_records');
    }
};
