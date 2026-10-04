<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('additional_user_seats')->default(0)->after('plan_expires_at');
            $table->unsignedInteger('additional_seller_seats')->default(0)->after('additional_user_seats');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['additional_user_seats', 'additional_seller_seats']);
        });
    }
};
