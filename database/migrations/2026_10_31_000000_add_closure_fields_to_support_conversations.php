<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_conversations')) {
            return;
        }

        Schema::table('support_conversations', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_conversations', 'closed_by_id')) {
                $table->foreignId('closed_by_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('support_conversations', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('closed_by_id')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('support_conversations')) {
            return;
        }

        Schema::table('support_conversations', function (Blueprint $table): void {
            if (Schema::hasColumn('support_conversations', 'closed_by_id')) {
                $table->dropForeign(['closed_by_id']);
                $table->dropColumn('closed_by_id');
            }
            if (Schema::hasColumn('support_conversations', 'closed_at')) {
                $table->dropColumn('closed_at');
            }
        });
    }
};
