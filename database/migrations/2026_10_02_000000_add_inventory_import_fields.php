<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->unsignedInteger('product_limit')->nullable()->after('status');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('product_code', 100)->nullable()->after('name')->index();
            $table->string('source_category', 150)->nullable()->after('description');
            $table->text('notes')->nullable()->after('source_category');
            $table->timestamp('source_created_at')->nullable()->after('notes');
            $table->string('source_key', 180)->nullable()->after('source_created_at');
            $table->unique(['shop_id', 'source_key']);
        });

        Schema::create('product_import_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 100);
            $table->unsignedInteger('source_row');
            $table->string('source_key', 180);
            $table->string('data_status', 30);
            $table->string('image_status', 30)->default('not_found');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'source', 'source_row']);
            $table->index(['shop_id', 'data_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_import_records');

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'source_key']);
            $table->dropColumn(['product_code', 'source_category', 'notes', 'source_created_at', 'source_key']);
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('product_limit');
        });
    }
};
