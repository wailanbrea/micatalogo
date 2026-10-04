<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'first_name')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->string('first_name', 80)->nullable()->after('name');
                $table->string('last_name', 80)->nullable()->after('first_name');
                $table->string('document_type', 20)->nullable()->after('last_name');
                $table->string('document_number', 40)->nullable()->after('document_type');
                $table->string('whatsapp', 30)->nullable()->after('phone');
                $table->string('reference', 255)->nullable()->after('address');
            });
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->unique(['shop_id', 'document_type', 'document_number'], 'customers_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique('customers_identity_unique');
            $table->dropColumn([
                'first_name',
                'last_name',
                'document_type',
                'document_number',
                'whatsapp',
                'reference',
            ]);
        });
    }
};
