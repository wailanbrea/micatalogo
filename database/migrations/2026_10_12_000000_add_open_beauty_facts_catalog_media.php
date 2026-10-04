<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_products', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->default('open_beauty_facts');
            $table->string('barcode', 32);
            $table->string('provider_product_id', 100)->nullable();
            $table->string('name')->nullable();
            $table->string('generic_name')->nullable();
            $table->string('brands')->nullable();
            $table->text('categories')->nullable();
            $table->string('quantity')->nullable();
            $table->json('provider_payload')->nullable();
            $table->string('lookup_status', 30)->default('pending')->index();
            $table->timestamp('last_lookup_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'barcode']);
            $table->index('barcode');
        });

        Schema::create('catalog_product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_product_id')->constrained()->cascadeOnDelete();
            $table->string('provider_image_id', 180)->nullable();
            $table->text('source_url');
            $table->char('source_url_hash', 64);
            $table->string('object_key')->nullable();
            $table->string('thumbnail_object_key')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('checksum_sha256', 64)->nullable();
            $table->string('license', 180)->nullable();
            $table->text('attribution')->nullable();
            $table->string('processing_status', 20)->default('pending')->index();
            $table->boolean('is_primary')->default(false);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['catalog_product_id', 'source_url_hash']);
            $table->index(['catalog_product_id', 'is_primary']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('barcode', 32)->nullable()->after('product_code')->index();
            $table->foreignId('catalog_product_id')->nullable()->after('barcode')->constrained()->nullOnDelete();
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('product_id')->index();
            $table->foreignId('catalog_product_image_id')->nullable()->after('source')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropForeign(['catalog_product_image_id']);
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'catalog_product_image_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['catalog_product_id']);
            $table->dropIndex(['barcode']);
            $table->dropColumn(['barcode', 'catalog_product_id']);
        });

        Schema::dropIfExists('catalog_product_images');
        Schema::dropIfExists('catalog_products');
    }
};
