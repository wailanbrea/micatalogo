<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo_object_key')->nullable();
            $table->string('whatsapp_country_code', 5);
            $table->string('whatsapp_number', 20);
            $table->string('instagram')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('global_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('global_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 20)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('shop_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->unique(['shop_id', 'slug']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('shop_id')->constrained();
            $table->foreignId('global_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shop_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->char('currency', 3)->default('DOP');
            $table->string('availability_status', 20)->default('available')->index();
            $table->string('moderation_status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['shop_id', 'slug']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('object_key');
            $table->string('thumbnail_object_key')->nullable();
            $table->string('mime_type', 100);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('processing_status', 20)->default('pending')->index();
            $table->timestamps();
            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->morphs('reportable');
            $table->string('reason', 50);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('shop_daily_metrics', function (Blueprint $table) {
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('page_views')->default(0);
            $table->unsignedBigInteger('whatsapp_clicks')->default(0);
            $table->unique(['shop_id', 'date']);
        });

        Schema::create('product_daily_metrics', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('page_views')->default(0);
            $table->unsignedBigInteger('whatsapp_clicks')->default(0);
            $table->unique(['product_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_daily_metrics');
        Schema::dropIfExists('shop_daily_metrics');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('shop_categories');
        Schema::dropIfExists('global_categories');
        Schema::dropIfExists('shops');
    }
};
