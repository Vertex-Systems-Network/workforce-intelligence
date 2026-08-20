<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repair installations where the original operational security/integration migration was recorded/applied
     * incompletely. MySQL DDL is not fully transactional, so operational tables
     * may be missing even though later application code is already deployed.
     */
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('security_events')) {
            Schema::create('security_events', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('event_type', 100);
                $table->string('severity', 20)->default('info');
                $table->ipAddress('ip_address')->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['workspace_id', 'severity', 'created_at'], 'sec_evt_ws_sev_created_idx');
                $table->index(['user_id', 'created_at'], 'sec_evt_user_created_idx');
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->string('actor_type', 30)->default('user');
                $table->string('actor_id', 120)->nullable();
                $table->string('action', 120);
                $table->string('category', 40)->default('workspace');
                $table->string('method', 10)->nullable();
                $table->string('path', 500)->nullable();
                $table->string('route_name', 180)->nullable();
                $table->unsignedSmallInteger('status_code')->nullable();
                $table->ipAddress('ip_address')->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->string('subject_type', 120)->nullable();
                $table->string('subject_id', 120)->nullable();
                $table->json('metadata')->nullable();
                $table->string('risk_level', 20)->default('normal');
                $table->timestamp('created_at')->useCurrent();
                $table->index(['workspace_id', 'created_at'], 'audit_ws_created_idx');
                $table->index(['workspace_id', 'category', 'created_at'], 'audit_ws_cat_created_idx');
                $table->index(['workspace_id', 'user_id', 'created_at'], 'audit_ws_user_created_idx');
            });
        }

        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('category', 40);
                $table->boolean('in_app')->default(true);
                $table->boolean('email')->default(false);
                $table->string('digest', 20)->default('immediate');
                $table->timestamps();
                $table->unique(['workspace_id', 'user_id', 'category'], 'notif_pref_ws_user_cat_uq');
            });
        }

        if (! Schema::hasTable('workspace_notifications')) {
            Schema::create('workspace_notifications', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('category', 40);
                $table->string('type', 80);
                $table->string('severity', 20)->default('info');
                $table->string('title', 180);
                $table->text('body')->nullable();
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('email_sent_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['workspace_id', 'user_id', 'read_at', 'created_at'], 'notif_ws_user_read_created_idx');
            });
        }

        // The remaining integration tables are optional to core auth. They
        // are repaired here as well so Settings/Integration screens cannot land
        // in a half-schema state after a historical partial migration.
        if (! Schema::hasTable('integration_connections')) {
            Schema::create('integration_connections', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('provider', 40);
                $table->string('name', 120);
                $table->string('status', 24)->default('active');
                $table->text('config_encrypted')->nullable();
                $table->timestamp('last_tested_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'provider', 'status'], 'int_conn_ws_provider_status_idx');
            });
        }

        if (! Schema::hasTable('workspace_api_keys')) {
            Schema::create('workspace_api_keys', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 120);
                $table->string('prefix', 18);
                $table->char('token_hash', 64)->unique();
                $table->json('scopes');
                $table->timestamp('last_used_at')->nullable();
                $table->ipAddress('last_used_ip')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['workspace_id', 'revoked_at'], 'api_keys_ws_revoked_idx');
            });
        }

        if (! Schema::hasTable('webhook_endpoints')) {
            Schema::create('webhook_endpoints', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 120);
                $table->string('url', 1000);
                $table->text('secret_encrypted');
                $table->string('secret_preview', 12);
                $table->json('events');
                $table->string('status', 20)->default('active');
                $table->unsignedTinyInteger('max_attempts')->default(5);
                $table->timestamp('last_success_at')->nullable();
                $table->timestamp('last_failure_at')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'status'], 'webhook_ep_ws_status_idx');
            });
        }

        if (! Schema::hasTable('webhook_deliveries')) {
            Schema::create('webhook_deliveries', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
                $table->string('event_type', 100);
                $table->uuid('event_id');
                $table->json('payload');
                $table->string('status', 20)->default('pending');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->unsignedSmallInteger('last_status_code')->nullable();
                $table->string('last_response_excerpt', 1000)->nullable();
                $table->timestamp('next_attempt_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['workspace_id', 'status', 'next_attempt_at'], 'webhook_del_ws_status_next_idx');
                $table->unique(['webhook_endpoint_id', 'event_id'], 'webhook_del_ep_event_uq');
            });
        }

        if (Schema::hasTable('permissions')) {
            $permissionSlugs = [
                ['Notifications', 'notifications.manage'], ['Integrations', 'integrations.view'],
                ['Integrations', 'integrations.manage'], ['API', 'api.manage'],
                ['Security', 'security.audit.view'], ['Security', 'security.manage'],
            ];
            foreach ($permissionSlugs as [$group, $slug]) {
                DB::table('permissions')->updateOrInsert(['slug'=>$slug], ['name'=>ucwords(str_replace(['.','_'],' ',$slug)), 'group'=>$group]);
            }
            if (Schema::hasTable('roles') && Schema::hasTable('role_permissions')) {
                $pivotTimestamps = Schema::hasColumn('role_permissions','created_at') && Schema::hasColumn('role_permissions','updated_at');
                $attach = static function (int $roleId, int $permissionId) use ($pivotTimestamps): void {
                    $row=['role_id'=>$roleId,'permission_id'=>$permissionId];
                    if ($pivotTimestamps) { $row['created_at']=now(); $row['updated_at']=now(); }
                    DB::table('role_permissions')->insertOrIgnore($row);
                };
                $all = DB::table('permissions')->whereIn('slug', array_column($permissionSlugs,1))->pluck('id');
                foreach (DB::table('roles')->whereIn('slug',['owner','admin'])->pluck('id') as $roleId) foreach ($all as $permissionId) $attach((int)$roleId,(int)$permissionId);
                $view = DB::table('permissions')->whereIn('slug',['integrations.view','security.audit.view'])->pluck('id');
                foreach (DB::table('roles')->whereIn('slug',['hr','manager'])->pluck('id') as $roleId) foreach ($view as $permissionId) $attach((int)$roleId,(int)$permissionId);
            }
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        // Recovery migration is intentionally non-destructive on rollback.
        // These tables may contain production audit/security data.
    }
};
