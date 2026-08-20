<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('chat_conversations')) {
            Schema::create('chat_conversations', function (Blueprint $table) {
                $table->id(); $table->uuid('uuid')->unique(); $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('type',24)->default('group'); $table->string('name',160)->nullable(); $table->text('description')->nullable();
                $table->string('direct_key',100)->nullable(); $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('created_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamp('archived_at')->nullable(); $table->timestamps();
                $table->unique(['workspace_id','direct_key'],'chat_ws_direct_unique'); $table->index(['workspace_id','type','archived_at'],'chat_ws_type_archive_idx');
            });
        }
        if (! Schema::hasTable('chat_conversation_members')) {
            Schema::create('chat_conversation_members', function (Blueprint $table) {
                $table->id(); $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete(); $table->string('role',20)->default('member');
                $table->boolean('is_muted')->default(false); $table->unsignedBigInteger('last_read_message_id')->nullable(); $table->timestamp('joined_at')->useCurrent();
                $table->unique(['conversation_id','member_id'],'chat_conv_member_unique'); $table->index(['member_id','conversation_id'],'chat_member_conv_idx');
            });
        }
        if (! Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
                $table->id(); $table->uuid('uuid')->unique(); $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete(); $table->foreignId('sender_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('chat_messages')->nullOnDelete(); $table->text('body')->nullable(); $table->json('mentions')->nullable();
                $table->timestamp('edited_at')->nullable(); $table->timestamp('deleted_at')->nullable(); $table->timestamps();
                $table->index(['conversation_id','id'],'chat_conv_message_idx'); $table->index(['workspace_id','created_at'],'chat_ws_created_idx');
            });
        }
        if (! Schema::hasTable('chat_message_attachments')) {
            Schema::create('chat_message_attachments', function (Blueprint $table) {
                $table->id(); $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete(); $table->string('disk',40)->default('local');
                $table->string('path',1000); $table->string('filename',255); $table->string('mime_type',160)->nullable(); $table->unsignedBigInteger('size_bytes')->default(0);
                $table->char('checksum_sha256',64); $table->timestamp('created_at')->useCurrent();
            });
        }
        if (! Schema::hasTable('chat_message_reactions')) {
            Schema::create('chat_message_reactions', function (Blueprint $table) {
                $table->id(); $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete(); $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->string('emoji',32); $table->timestamp('created_at')->useCurrent(); $table->unique(['message_id','member_id','emoji'],'chat_reaction_unique');
            });
        }
        if (! Schema::hasTable('chat_message_pins')) {
            Schema::create('chat_message_pins', function (Blueprint $table) {
                $table->id(); $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete(); $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
                $table->foreignId('pinned_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete(); $table->timestamp('created_at')->useCurrent();
                $table->unique(['conversation_id','message_id'],'chat_pin_unique');
            });
        }
        if (! Schema::hasTable('chat_presence')) {
            Schema::create('chat_presence', function (Blueprint $table) {
                $table->id(); $table->foreignId('workspace_id')->constrained()->cascadeOnDelete(); $table->foreignId('member_id')->unique()->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('conversation_id')->nullable()->constrained('chat_conversations')->nullOnDelete(); $table->boolean('is_typing')->default(false); $table->timestamp('last_seen_at')->nullable(); $table->timestamps();
                $table->index(['workspace_id','last_seen_at'],'chat_presence_ws_seen_idx');
            });
        }
    }
    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void { foreach (['chat_presence','chat_message_pins','chat_message_reactions','chat_message_attachments','chat_messages','chat_conversation_members','chat_conversations'] as $table) Schema::dropIfExists($table); }
};
