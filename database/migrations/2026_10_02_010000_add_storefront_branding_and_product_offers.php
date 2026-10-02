<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('cover_object_key')->nullable()->after('logo_object_key');
            $table->string('primary_color', 7)->default('#1d4ed8')->after('cover_object_key');
            $table->string('secondary_color', 7)->default('#0f172a')->after('primary_color');
            $table->string('address', 255)->nullable()->after('instagram');
            $table->string('maps_url', 500)->nullable()->after('address');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('brand', 120)->nullable()->after('product_code')->index();
            $table->decimal('sale_price', 12, 2)->nullable()->after('price');
            $table->timestamp('sale_starts_at')->nullable()->after('sale_price');
            $table->timestamp('sale_ends_at')->nullable()->after('sale_starts_at');
            $table->index(['sale_price', 'sale_starts_at', 'sale_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['sale_price', 'sale_starts_at', 'sale_ends_at']);
            $table->dropColumn(['brand', 'sale_price', 'sale_starts_at', 'sale_ends_at']);
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['cover_object_key', 'primary_color', 'secondary_color', 'address', 'maps_url']);
        });
    }
};
