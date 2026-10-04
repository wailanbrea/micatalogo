<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('catalog_product_images', 'source_url_hash')) {
            Schema::table('catalog_product_images', function (Blueprint $table) {
                $table->char('source_url_hash', 64)->nullable()->after('source_url');
            });

            DB::table('catalog_product_images')
                ->select(['id', 'source_url'])
                ->orderBy('id')
                ->chunkById(100, function ($images): void {
                    foreach ($images as $image) {
                        DB::table('catalog_product_images')
                            ->where('id', $image->id)
                            ->update(['source_url_hash' => hash('sha256', (string) $image->source_url)]);
                    }
                });

            Schema::table('catalog_product_images', function (Blueprint $table) {
                $table->dropUnique('catalog_product_images_catalog_product_id_source_url_unique');
                $table->unique(['catalog_product_id', 'source_url_hash']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('catalog_product_images', 'source_url_hash')) {
            Schema::table('catalog_product_images', function (Blueprint $table) {
                $table->dropUnique('catalog_product_images_catalog_product_id_source_url_hash_unique');
                $table->dropColumn('source_url_hash');
            });
        }
    }
};
