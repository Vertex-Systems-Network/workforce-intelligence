<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Adds enterprise collaboration controls without deleting existing chat or identity data. */
    public function up(): void
    {
        if (Schema::hasTable('workspace_members')) {
            if (! Schema::hasColumn('workspace_members', 'collaboration_type')) {
                Schema::table('workspace_members', fn (Blueprint $table) => $table->string('collaboration_type', 24)->default('internal')->after('employment_type'));
            }
            if (! Schema::hasColumn('workspace_members', 'external_company')) {
                Schema::table('workspace_members', fn (Blueprint $table) => $table->string('external_company', 180)->nullable()->after('collaboration_type'));
            }
            if (! Schema::hasColumn('workspace_members', 'external_expires_at')) {
                Schema::table('workspace_members', fn (Blueprint $table) => $table->timestamp('external_expires_at')->nullable()->after('external_company'));
            }
            if (! Schema::hasColumn('workspace_members', 'external_scope')) {
                Schema::table('workspace_members', fn (Blueprint $table) => $table->json('external_scope')->nullable()->after('external_expires_at'));
            }
        }

        if (Schema::hasTable('workspace_invitations')) {
            if (! Schema::hasColumn('workspace_invitations', 'collaboration_type')) {
                Schema::table('workspace_invitations', fn (Blueprint $table) => $table->string('collaboration_type', 24)->default('internal')->after('employment_type'));
            }
            if (! Schema::hasColumn('workspace_invitations', 'external_company')) {
                Schema::table('workspace_invitations', fn (Blueprint $table) => $table->string('external_company', 180)->nullable()->after('collaboration_type'));
            }
            if (! Schema::hasColumn('workspace_invitations', 'external_expires_at')) {
                Schema::table('workspace_invitations', fn (Blueprint $table) => $table->timestamp('external_expires_at')->nullable()->after('external_company'));
            }
            if (! Schema::hasColumn('workspace_invitations', 'chat_conversation_id')) {
                Schema::table('workspace_invitations', fn (Blueprint $table) => $table->foreignId('chat_conversation_id')->nullable()->after('external_expires_at')->constrained('chat_conversations')->nullOnDelete());
            }
        }

        if (Schema::hasTable('chat_conversations')) {
            if (! Schema::hasColumn('chat_conversations', 'external_access')) {
                Schema::table('chat_conversations', fn (Blueprint $table) => $table->boolean('external_access')->default(false)->after('is_locked'));
            }
            if (! Schema::hasColumn('chat_conversations', 'retention_days')) {
                Schema::table('chat_conversations', fn (Blueprint $table) => $table->unsignedInteger('retention_days')->nullable()->after('external_access'));
            }
            if (! Schema::hasColumn('chat_conversations', 'legal_hold')) {
                Schema::table('chat_conversations', fn (Blueprint $table) => $table->boolean('legal_hold')->default(false)->after('retention_days'));
            }
            if (! Schema::hasColumn('chat_conversations', 'export_policy')) {
                Schema::table('chat_conversations', fn (Blueprint $table) => $table->string('export_policy', 24)->default('admins')->after('legal_hold'));
            }
            if (! Schema::hasColumn('chat_conversations', 'dlp_mode')) {
                Schema::table('chat_conversations', fn (Blueprint $table) => $table->string('dlp_mode', 24)->default('inherit')->after('export_policy'));
            }
        }

        if (Schema::hasTable('chat_conversation_members') && ! Schema::hasColumn('chat_conversation_members', 'guest_expires_at')) {
            Schema::table('chat_conversation_members', fn (Blueprint $table) => $table->timestamp('guest_expires_at')->nullable()->after('notifications_snoozed_until'));
        }

        if (Schema::hasTable('chat_message_attachments')) {
            if (! Schema::hasColumn('chat_message_attachments', 'security_status')) {
                Schema::table('chat_message_attachments', fn (Blueprint $table) => $table->string('security_status', 24)->default('clean')->after('checksum_sha256'));
            }
            if (! Schema::hasColumn('chat_message_attachments', 'security_reason')) {
                Schema::table('chat_message_attachments', fn (Blueprint $table) => $table->string('security_reason', 500)->nullable()->after('security_status'));
            }
        }

        if (! Schema::hasTable('chat_legal_holds')) {
            Schema::create('chat_legal_holds', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('conversation_id')->nullable()->constrained('chat_conversations')->cascadeOnDelete();
                $table->string('name', 180);
                $table->text('reason')->nullable();
                $table->string('status', 20)->default('active');
                $table->foreignId('created_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->foreignId('released_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('released_at')->nullable();
                $table->json('metadata')->nullable();
                $table->index(['workspace_id', 'status'], 'chat_hold_ws_status_idx');
                $table->index(['conversation_id', 'status'], 'chat_hold_conv_status_idx');
            });
        }

        if (! Schema::hasTable('chat_moderation_events')) {
            Schema::create('chat_moderation_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('conversation_id')->nullable()->constrained('chat_conversations')->nullOnDelete();
                $table->foreignId('message_id')->nullable()->constrained('chat_messages')->nullOnDelete();
                $table->foreignId('actor_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->foreignId('target_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->string('action', 80);
                $table->string('reason', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['workspace_id', 'created_at'], 'chat_mod_ws_created_idx');
                $table->index(['conversation_id', 'created_at'], 'chat_mod_conv_created_idx');
            });
        }

        if (! Schema::hasTable('chat_export_jobs')) {
            Schema::create('chat_export_jobs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('conversation_id')->nullable()->constrained('chat_conversations')->nullOnDelete();
                $table->foreignId('requested_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->string('format', 16)->default('json');
                $table->string('status', 20)->default('pending');
                $table->string('disk', 40)->nullable();
                $table->string('path', 1000)->nullable();
                $table->char('checksum_sha256', 64)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->json('filters')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->text('error')->nullable();
                $table->index(['workspace_id', 'created_at'], 'chat_export_ws_created_idx');
                $table->index(['workspace_id', 'status'], 'chat_export_ws_status_idx');
            });
        }

        if (! Schema::hasTable('chat_dlp_policies')) {
            Schema::create('chat_dlp_policies', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 160);
                $table->string('mode', 20)->default('monitor');
                $table->json('keywords')->nullable();
                $table->json('file_extensions')->nullable();
                $table->unsignedBigInteger('max_file_bytes')->nullable();
                $table->boolean('active')->default(true);
                $table->foreignId('created_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'active'], 'chat_dlp_ws_active_idx');
            });
        }

        if (! Schema::hasTable('chat_dlp_events')) {
            Schema::create('chat_dlp_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('conversation_id')->nullable()->constrained('chat_conversations')->nullOnDelete();
                $table->foreignId('message_id')->nullable()->constrained('chat_messages')->nullOnDelete();
                $table->foreignId('attachment_id')->nullable()->constrained('chat_message_attachments')->nullOnDelete();
                $table->foreignId('policy_id')->nullable()->constrained('chat_dlp_policies')->nullOnDelete();
                $table->foreignId('actor_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->string('action', 24)->default('detected');
                $table->json('matched_rules')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['workspace_id', 'created_at'], 'chat_dlp_event_ws_created_idx');
                $table->index(['conversation_id', 'created_at'], 'chat_dlp_event_conv_created_idx');
            });
        }

        if (Schema::hasTable('data_governance_policies')) {
            DB::table('data_governance_policies')->where('dataset', 'chat_messages')->whereNull('retention_days')->update(['retention_days' => 3650]);
        }
    }

    /** Removes only the Chat V2.4 schema additions and keeps historical base data intact. */
    public function down(): void
    {
        foreach (['chat_dlp_events', 'chat_dlp_policies', 'chat_export_jobs', 'chat_moderation_events', 'chat_legal_holds'] as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasTable('chat_message_attachments')) {
            Schema::table('chat_message_attachments', function (Blueprint $table) {
                foreach (['security_reason', 'security_status'] as $column) if (Schema::hasColumn('chat_message_attachments', $column)) $table->dropColumn($column);
            });
        }

        if (Schema::hasTable('chat_conversation_members') && Schema::hasColumn('chat_conversation_members', 'guest_expires_at')) {
            Schema::table('chat_conversation_members', fn (Blueprint $table) => $table->dropColumn('guest_expires_at'));
        }

        if (Schema::hasTable('chat_conversations')) {
            Schema::table('chat_conversations', function (Blueprint $table) {
                foreach (['dlp_mode', 'export_policy', 'legal_hold', 'retention_days', 'external_access'] as $column) if (Schema::hasColumn('chat_conversations', $column)) $table->dropColumn($column);
            });
        }

        if (Schema::hasTable('workspace_invitations')) {
            Schema::table('workspace_invitations', function (Blueprint $table) {
                if (Schema::hasColumn('workspace_invitations', 'chat_conversation_id')) $table->dropConstrainedForeignId('chat_conversation_id');
                foreach (['external_expires_at', 'external_company', 'collaboration_type'] as $column) if (Schema::hasColumn('workspace_invitations', $column)) $table->dropColumn($column);
            });
        }

        if (Schema::hasTable('workspace_members')) {
            Schema::table('workspace_members', function (Blueprint $table) {
                foreach (['external_scope', 'external_expires_at', 'external_company', 'collaboration_type'] as $column) if (Schema::hasColumn('workspace_members', $column)) $table->dropColumn($column);
            });
        }
    }
};
