<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->string('type', 20)->default('text');
            $table->boolean('filterable')->default(true)->index();
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
            $table->unique(['shop_id', 'shop_category_id', 'slug'], 'attr_def_shop_category_slug_unique');
            $table->index(['shop_id', 'shop_category_id', 'filterable']);
        });

        Schema::create('product_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_definition_id')->constrained()->cascadeOnDelete();
            $table->string('value', 255);
            $table->timestamps();
            $table->unique(['product_id', 'attribute_definition_id'], 'prod_attr_value_unique');
            $table->index(['attribute_definition_id', 'value']);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('order_number', 40)->unique();
            $table->string('customer_name', 120)->nullable();
            $table->string('delivery_type', 20)->nullable();
            $table->text('notes')->nullable();
            $table->char('currency', 3)->default('DOP');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('status', 30)->default('pending')->index();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name', 255);
            $table->string('product_code', 100)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::table('shop_daily_metrics', function (Blueprint $table): void {
            $table->unsignedBigInteger('orders_sent')->default(0)->after('whatsapp_clicks');
        });
    }

    public function down(): void
    {
        Schema::table('shop_daily_metrics', function (Blueprint $table): void {
            $table->dropColumn('orders_sent');
        });
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('attribute_definitions');
    }
};
