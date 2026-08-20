<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Adds idempotent delivery cursors and indexes required by Chat V2.5 performance hardening. */
    public function up(): void
    {
        if (Schema::hasTable('chat_messages') && ! Schema::hasColumn('chat_messages', 'client_message_id')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->string('client_message_id', 64)->nullable()->after('uuid');
                $table->timestamp('client_sent_at')->nullable()->after('client_message_id');
            });
        }

        if (Schema::hasTable('chat_messages') && Schema::hasColumn('chat_messages', 'client_message_id') && ! Schema::hasIndex('chat_messages', 'chat_conv_sender_client_uq')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->unique(['conversation_id', 'sender_member_id', 'client_message_id'], 'chat_conv_sender_client_uq');
            });
        }

        if (Schema::hasTable('chat_messages') && ! Schema::hasIndex('chat_messages', 'chat_conv_parent_message_idx')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->index(['conversation_id', 'parent_id', 'id'], 'chat_conv_parent_message_idx');
            });
        }

        if (Schema::hasTable('chat_conversation_members') && ! Schema::hasColumn('chat_conversation_members', 'last_delivered_message_id')) {
            Schema::table('chat_conversation_members', function (Blueprint $table) {
                $table->unsignedBigInteger('last_delivered_message_id')->nullable()->after('last_read_message_id');
            });
        }
    }

    /** Reverses the additive Chat V2.5 delivery fields and indexes without touching chat history. */
    public function down(): void
    {
        if (Schema::hasTable('chat_conversation_members') && Schema::hasColumn('chat_conversation_members', 'last_delivered_message_id')) {
            Schema::table('chat_conversation_members', function (Blueprint $table) {
                $table->dropColumn('last_delivered_message_id');
            });
        }

        if (Schema::hasTable('chat_messages') && Schema::hasIndex('chat_messages', 'chat_conv_parent_message_idx')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->dropIndex('chat_conv_parent_message_idx');
            });
        }

        if (Schema::hasTable('chat_messages') && Schema::hasIndex('chat_messages', 'chat_conv_sender_client_uq')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->dropUnique('chat_conv_sender_client_uq');
            });
        }

        if (Schema::hasTable('chat_messages')) {
            $drop = [];
            foreach (['client_sent_at', 'client_message_id'] as $column) {
                if (Schema::hasColumn('chat_messages', $column)) $drop[] = $column;
            }
            if ($drop) {
                Schema::table('chat_messages', function (Blueprint $table) use ($drop) {
                    $table->dropColumn($drop);
                });
            }
        }
    }
};
