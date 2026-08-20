<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('activity_tracking_settings')) {
            Schema::create('activity_tracking_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
                $table->boolean('application_tracking_enabled')->default(true);
                $table->boolean('website_tracking_enabled')->default(true);
                $table->boolean('capture_window_titles')->default(false);
                $table->boolean('capture_page_titles')->default(false);
                $table->boolean('store_full_urls')->default(false);
                $table->unsignedInteger('minimum_session_seconds')->default(5);
                $table->unsignedInteger('idle_threshold_seconds')->default(300);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('browser_enrollments')) {
            Schema::create('browser_enrollments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->char('code_hash', 64)->unique();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('browser_connections')) {
            Schema::create('browser_connections', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
                $table->string('installation_id', 120);
                $table->string('browser_name', 80);
                $table->string('browser_version', 40)->nullable();
                $table->string('extension_version', 40);
                $table->string('status', 24)->default('active');
                $table->timestamp('enrolled_at');
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('last_sync_at')->nullable();
                $table->ipAddress('last_ip')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
                $table->unique(['workspace_id', 'installation_id']);
                $table->index(['workspace_id', 'member_id', 'status']);
            });
        }

        if (! Schema::hasTable('browser_access_tokens')) {
            Schema::create('browser_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('browser_connection_id')->constrained()->cascadeOnDelete();
                $table->char('token_hash', 64)->unique();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('application_sessions')) {
            Schema::create('application_sessions', function (Blueprint $table) {
                $table->id();
                $table->uuid('session_uuid');
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
                $table->string('app_key', 180);
                $table->string('app_name', 180);
                $table->string('process_name', 180)->nullable();
                $table->string('window_title', 500)->nullable();
                $table->timestamp('started_at');
                $table->timestamp('ended_at');
                $table->unsignedInteger('duration_seconds');
                $table->unsignedInteger('active_seconds')->default(0);
                $table->unsignedInteger('idle_seconds')->default(0);
                $table->string('source', 32)->default('desktop_agent');
                $table->timestamps();
                $table->unique(['workspace_id', 'session_uuid']);
                $table->index(['workspace_id', 'member_id', 'started_at']);
                $table->index(['workspace_id', 'app_key', 'started_at']);
                $table->index(['device_id', 'started_at']);
            });
        }

        if (! Schema::hasTable('website_sessions')) {
            Schema::create('website_sessions', function (Blueprint $table) {
                $table->id();
                $table->uuid('session_uuid');
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('browser_connection_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
                $table->string('domain', 253);
                $table->string('browser_name', 120)->nullable();
                $table->string('page_title', 500)->nullable();
                $table->timestamp('started_at');
                $table->timestamp('ended_at');
                $table->unsignedInteger('duration_seconds');
                $table->unsignedInteger('active_seconds')->default(0);
                $table->unsignedInteger('idle_seconds')->default(0);
                $table->string('source', 32)->default('browser_extension');
                $table->timestamps();
                $table->unique(['workspace_id', 'session_uuid']);
                $table->index(['workspace_id', 'member_id', 'started_at']);
                $table->index(['workspace_id', 'domain', 'started_at']);
            });
        }

        if (! Schema::hasTable('productivity_rules')) {
            Schema::create('productivity_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('scope_type', 24)->default('workspace');
                $table->unsignedBigInteger('scope_id')->nullable();
                $table->string('target_type', 24);
                $table->string('target', 253);
                $table->string('classification', 24);
                $table->string('category', 80)->nullable();
                $table->boolean('active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'scope_type', 'scope_id']);
                $table->index(['workspace_id', 'target_type', 'target']);
            });
        }

        if (! Schema::hasTable('tracking_exclusions')) {
            Schema::create('tracking_exclusions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('scope_type', 24)->default('workspace');
                $table->unsignedBigInteger('scope_id')->nullable();
                $table->string('target_type', 24);
                $table->string('pattern', 253);
                $table->string('reason', 255)->nullable();
                $table->boolean('active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'scope_type', 'scope_id']);
                $table->index(['workspace_id', 'target_type', 'pattern']);
            });
        }

        foreach ([['Activity', 'activity.manage'], ['Activity', 'activity.rules_manage']] as [$group, $slug]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => ucwords(str_replace(['.', '_'], ' ', $slug)), 'group' => $group]
            );
        }

        $manageIds = DB::table('permissions')->whereIn('slug', ['activity.manage', 'activity.rules_manage'])->pluck('id');
        DB::table('roles')->whereIn('slug', ['owner', 'admin'])->get()->each(function ($role) use ($manageIds) {
            foreach ($manageIds as $permissionId) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', ['activity.manage', 'activity.rules_manage'])->pluck('id');
        if ($permissionIds->isNotEmpty()) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }

        Schema::dropIfExists('tracking_exclusions');
        Schema::dropIfExists('productivity_rules');
        Schema::dropIfExists('website_sessions');
        Schema::dropIfExists('application_sessions');
        Schema::dropIfExists('browser_access_tokens');
        Schema::dropIfExists('browser_connections');
        Schema::dropIfExists('browser_enrollments');
        Schema::dropIfExists('activity_tracking_settings');
    }
};
