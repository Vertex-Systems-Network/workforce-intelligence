<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Adds channel governance, notification preferences, bots and linked resources without deleting existing chat data. */
    public function up(): void
    {
        if (Schema::hasTable('chat_conversations')) {
            if (! Schema::hasColumn('chat_conversations', 'visibility')) {
                Schema::table('chat_conversations', fn (Blueprint $table) => $table->string('visibility', 20)->default('private')->after('type'));
            }
            if (! Schema::hasColumn('chat_conversations', 'channel_mode')) {
                Schema::table('chat_conversations', fn (Blueprint $table) => $table->string('channel_mode', 24)->default('standard')->after('visibility'));
            }
            if (! Schema::hasColumn('chat_conversations', 'posting_policy')) {
                Schema::table('chat_conversations', fn (Blueprint $table) => $table->string('posting_policy', 24)->default('members')->after('channel_mode'));
            }
            if (! Schema::hasColumn('chat_conversations', 'is_locked')) {
                Schema::table('chat_conversations', fn (Blueprint $table) => $table->boolean('is_locked')->default(false)->after('posting_policy'));
            }
        }

        if (Schema::hasTable('chat_conversation_members')) {
            if (! Schema::hasColumn('chat_conversation_members', 'notification_mode')) {
                Schema::table('chat_conversation_members', fn (Blueprint $table) => $table->string('notification_mode', 20)->default('all')->after('is_muted'));
            }
            if (! Schema::hasColumn('chat_conversation_members', 'notifications_snoozed_until')) {
                Schema::table('chat_conversation_members', fn (Blueprint $table) => $table->timestamp('notifications_snoozed_until')->nullable()->after('notification_mode'));
            }
            // Preserve the intent of legacy muted conversations when the richer delivery mode is introduced.
            DB::table('chat_conversation_members')->where('is_muted', true)->update(['notification_mode' => 'nothing']);
        }

        if (! Schema::hasTable('chat_bots')) {
            Schema::create('chat_bots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('slug', 60);
                $table->string('name', 120);
                $table->string('kind', 30)->default('system');
                $table->string('avatar_key', 60)->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['workspace_id', 'slug'], 'chat_bot_ws_slug_uq');
                $table->index(['workspace_id', 'is_active'], 'chat_bot_ws_active_idx');
            });
        }

        if (Schema::hasTable('chat_messages')) {
            if (! Schema::hasColumn('chat_messages', 'sender_bot_id')) {
                Schema::table('chat_messages', fn (Blueprint $table) => $table->foreignId('sender_bot_id')->nullable()->after('sender_member_id')->constrained('chat_bots')->nullOnDelete());
            }
            if (! Schema::hasColumn('chat_messages', 'message_type')) {
                Schema::table('chat_messages', fn (Blueprint $table) => $table->string('message_type', 32)->default('message')->after('forwarded_from_message_id'));
            }
            if (! Schema::hasColumn('chat_messages', 'metadata')) {
                Schema::table('chat_messages', fn (Blueprint $table) => $table->json('metadata')->nullable()->after('mentions'));
            }
        }

        if (! Schema::hasTable('chat_channel_resources')) {
            Schema::create('chat_channel_resources', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
                $table->string('kind', 30)->default('link');
                $table->string('label', 160);
                $table->string('url', 1000)->nullable();
                $table->string('resource_type', 60)->nullable();
                $table->unsignedBigInteger('resource_id')->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(1000);
                $table->foreignId('created_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamps();
                $table->index(['conversation_id', 'sort_order'], 'chat_resource_conv_sort_idx');
            });
        }
    }

    /** Removes only the Chat V2.3 persistence additions. */
    public function down(): void
    {
        Schema::dropIfExists('chat_channel_resources');

        if (Schema::hasTable('chat_messages')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                if (Schema::hasColumn('chat_messages', 'sender_bot_id')) $table->dropConstrainedForeignId('sender_bot_id');
                if (Schema::hasColumn('chat_messages', 'message_type')) $table->dropColumn('message_type');
                if (Schema::hasColumn('chat_messages', 'metadata')) $table->dropColumn('metadata');
            });
        }

        Schema::dropIfExists('chat_bots');

        if (Schema::hasTable('chat_conversation_members')) {
            Schema::table('chat_conversation_members', function (Blueprint $table) {
                if (Schema::hasColumn('chat_conversation_members', 'notification_mode')) $table->dropColumn('notification_mode');
                if (Schema::hasColumn('chat_conversation_members', 'notifications_snoozed_until')) $table->dropColumn('notifications_snoozed_until');
            });
        }

        if (Schema::hasTable('chat_conversations')) {
            Schema::table('chat_conversations', function (Blueprint $table) {
                foreach (['visibility', 'channel_mode', 'posting_policy', 'is_locked'] as $column) {
                    if (Schema::hasColumn('chat_conversations', $column)) $table->dropColumn($column);
                }
            });
        }
    }
};
