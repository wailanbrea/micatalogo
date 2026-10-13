<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('decant_vials', 'sale_price')) {
            Schema::table('decant_vials', function (Blueprint $table): void {
                $table->decimal('sale_price', 12, 2)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('decant_vials', 'sale_price')) {
            Schema::table('decant_vials', function (Blueprint $table): void {
                $table->dropColumn('sale_price');
            });
        }
    }
};
