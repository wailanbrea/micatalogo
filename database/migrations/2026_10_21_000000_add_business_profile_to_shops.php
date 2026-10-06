<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->string('business_type', 60)->default('general_retail')->after('name')->index();
            $table->json('business_capability_overrides')->nullable()->after('business_type');
            $table->unsignedSmallInteger('business_profile_version')->nullable()->after('business_capability_overrides');
            $table->timestamp('onboarding_completed_at')->nullable()->after('business_profile_version');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropColumn(['business_type', 'business_capability_overrides', 'business_profile_version', 'onboarding_completed_at']);
        });
    }
};
