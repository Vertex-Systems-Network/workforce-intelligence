<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Adds professional messaging persistence while keeping existing chat data intact. */
    public function up(): void
    {
        if (! Schema::hasColumn('chat_messages', 'forwarded_from_message_id')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->foreignId('forwarded_from_message_id')->nullable()->after('parent_id')->constrained('chat_messages')->nullOnDelete();
            });
        }
        if (Schema::hasColumn('chat_messages', 'forwarded_from_message_id') && ! Schema::hasIndex('chat_messages', 'chat_conv_forward_idx')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->index(['conversation_id', 'forwarded_from_message_id'], 'chat_conv_forward_idx');
            });
        }

        if (! Schema::hasTable('chat_message_edit_history')) {
            Schema::create('chat_message_edit_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
                $table->foreignId('edited_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->longText('body')->nullable();
                $table->json('mentions')->nullable();
                $table->timestamp('edited_at')->useCurrent();
                $table->index(['message_id', 'id'], 'chat_edit_message_idx');
            });
        }

        if (! Schema::hasTable('chat_saved_messages')) {
            Schema::create('chat_saved_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
                $table->string('note', 500)->nullable();
                $table->timestamps();
                $table->unique(['member_id', 'message_id'], 'chat_saved_member_message_uq');
                $table->index(['workspace_id', 'member_id'], 'chat_saved_ws_member_idx');
            });
        }

        if (! Schema::hasTable('chat_drafts')) {
            Schema::create('chat_drafts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('chat_messages')->nullOnDelete();
                $table->longText('body')->nullable();
                $table->timestamps();
                $table->unique(['conversation_id', 'member_id'], 'chat_draft_conv_member_uq');
                $table->index(['workspace_id', 'member_id'], 'chat_draft_ws_member_idx');
            });
        }

        if (! Schema::hasTable('chat_polls')) {
            Schema::create('chat_polls', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->unique()->constrained('chat_messages')->cascadeOnDelete();
                $table->boolean('allows_multiple')->default(false);
                $table->timestamp('closes_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chat_poll_options')) {
            Schema::create('chat_poll_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('poll_id')->constrained('chat_polls')->cascadeOnDelete();
                $table->string('label', 255);
                $table->unsignedSmallInteger('position')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->index(['poll_id', 'position'], 'chat_poll_option_pos_idx');
            });
        }

        if (! Schema::hasTable('chat_poll_votes')) {
            Schema::create('chat_poll_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('poll_id')->constrained('chat_polls')->cascadeOnDelete();
                $table->foreignId('option_id')->constrained('chat_poll_options')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['option_id', 'member_id'], 'chat_poll_option_member_uq');
                $table->index(['poll_id', 'member_id'], 'chat_poll_member_idx');
            });
        }

        if (! Schema::hasTable('chat_thread_follows')) {
            Schema::create('chat_thread_follows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('root_message_id')->constrained('chat_messages')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->unsignedBigInteger('last_read_reply_id')->nullable();
                $table->boolean('is_following')->default(true);
                $table->timestamps();
                $table->unique(['root_message_id', 'member_id'], 'chat_thread_member_uq');
                $table->index(['workspace_id', 'member_id'], 'chat_thread_ws_member_idx');
            });
        }
    }

    /** Removes only the Chat V2.2 persistence added by this migration. */
    public function down(): void
    {
        foreach (['chat_thread_follows', 'chat_poll_votes', 'chat_poll_options', 'chat_polls', 'chat_drafts', 'chat_saved_messages', 'chat_message_edit_history'] as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasColumn('chat_messages', 'forwarded_from_message_id')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->dropIndex('chat_conv_forward_idx');
                $table->dropConstrainedForeignId('forwarded_from_message_id');
            });
        }
    }
};
