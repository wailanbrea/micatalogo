<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_conversations')) {
            Schema::create('support_conversations', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
                $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('assigned_to_id')->constrained('users')->restrictOnDelete();
                $table->string('subject', 160)->default('Ayuda con MiCatalogo');
                $table->string('status', 20)->default('open')->index();
                $table->timestamp('last_message_at')->nullable()->index();
                $table->timestamps();

                $table->index(['assigned_to_id', 'status', 'last_message_at'], 'sc_assigned_status_last_idx');
                $table->index(['requester_id', 'status', 'last_message_at'], 'sc_requester_status_last_idx');
            });
        } else {
            $existingIndexes = Schema::getIndexListing('support_conversations');
            Schema::table('support_conversations', function (Blueprint $table) use ($existingIndexes): void {
                if (! in_array('sc_assigned_status_last_idx', $existingIndexes, true)) {
                    $table->index(['assigned_to_id', 'status', 'last_message_at'], 'sc_assigned_status_last_idx');
                }
                if (! in_array('sc_requester_status_last_idx', $existingIndexes, true)) {
                    $table->index(['requester_id', 'status', 'last_message_at'], 'sc_requester_status_last_idx');
                }
            });
        }

        if (! Schema::hasTable('support_messages')) {
            Schema::create('support_messages', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->text('body');
                $table->timestamp('read_at')->nullable()->index();
                $table->timestamps();

                $table->index(['conversation_id', 'created_at'], 'sm_conversation_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_conversations');
    }
};
