<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sale_uploads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_sale_uuid');
            $table->char('payload_sha256', 64);
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['shop_id', 'client_sale_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sale_uploads');
    }
};
