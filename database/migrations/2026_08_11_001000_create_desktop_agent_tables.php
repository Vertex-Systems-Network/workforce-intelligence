<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('agent_enrollments')) {
            Schema::create('agent_enrollments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->char('code_hash', 64)->unique();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'member_id', 'expires_at']);
            });
        }

        if (! Schema::hasTable('devices')) {
            Schema::create('devices', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->string('installation_id', 120);
                $table->string('name', 160);
                $table->string('platform', 32);
                $table->string('os_name', 80);
                $table->string('os_version', 80)->nullable();
                $table->string('architecture', 32)->nullable();
                $table->string('agent_version', 32)->nullable();
                $table->char('machine_fingerprint_hash', 64)->nullable();
                $table->string('status', 24)->default('active');
                $table->string('tracking_status', 24)->default('stopped');
                $table->boolean('is_idle')->default(false);
                $table->unsignedInteger('offline_queue_size')->default(0);
                $table->json('capabilities')->nullable();
                $table->json('metadata')->nullable();
                $table->ipAddress('last_ip')->nullable();
                $table->timestamp('enrolled_at');
                $table->timestamp('last_heartbeat_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
                $table->unique(['workspace_id', 'installation_id']);
                $table->index(['workspace_id', 'member_id', 'status']);
                $table->index(['workspace_id', 'last_heartbeat_at']);
            });
        }

        if (! Schema::hasTable('device_access_tokens')) {
            Schema::create('device_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('device_id')->constrained()->cascadeOnDelete();
                $table->string('name', 80)->default('desktop-agent');
                $table->char('token_hash', 64)->unique();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['device_id', 'revoked_at']);
            });
        }

        if (! Schema::hasTable('agent_sync_batches')) {
            Schema::create('agent_sync_batches', function (Blueprint $table) {
                $table->id();
                $table->uuid('batch_uuid');
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('device_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('event_count')->default(0);
                $table->unsignedInteger('accepted_count')->default(0);
                $table->unsignedInteger('duplicate_count')->default(0);
                $table->timestamp('client_created_at')->nullable();
                $table->timestamp('received_at')->useCurrent();
                $table->unique(['device_id', 'batch_uuid']);
                $table->index(['workspace_id', 'received_at']);
            });
        }

        if (! Schema::hasTable('agent_events')) {
            Schema::create('agent_events', function (Blueprint $table) {
                $table->id();
                $table->uuid('event_uuid');
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('device_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->string('event_type', 80);
                $table->timestamp('occurred_at');
                $table->json('payload')->nullable();
                $table->timestamp('received_at')->useCurrent();
                $table->unique(['device_id', 'event_uuid']);
                $table->index(['workspace_id', 'member_id', 'occurred_at']);
                $table->index(['device_id', 'event_type', 'occurred_at']);
            });
        }

        if (! Schema::hasTable('agent_commands')) {
            Schema::create('agent_commands', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('device_id')->constrained()->cascadeOnDelete();
                $table->foreignId('queued_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('command_type', 40);
                $table->json('payload')->nullable();
                $table->string('status', 24)->default('queued');
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('acknowledged_at')->nullable();
                $table->json('result')->nullable();
                $table->timestamps();
                $table->index(['device_id', 'status', 'created_at']);
            });
        }

        foreach ([
            ['Devices', 'devices.view'],
            ['Devices', 'devices.manage'],
        ] as [$group, $slug]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => ucwords(str_replace(['.', '_'], ' ', $slug)), 'group' => $group]
            );
        }

        $devicePermissions = DB::table('permissions')->whereIn('slug', ['devices.view', 'devices.manage'])->pluck('id');
        $viewPermissionId = DB::table('permissions')->where('slug', 'devices.view')->value('id');

        DB::table('roles')->whereIn('slug', ['owner', 'admin'])->orderBy('id')->get()->each(function ($role) use ($devicePermissions) {
            foreach ($devicePermissions as $permissionId) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        if ($viewPermissionId) {
            DB::table('roles')->whereIn('slug', ['manager', 'hr'])->orderBy('id')->get()->each(function ($role) use ($viewPermissionId) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $viewPermissionId, 'created_at' => now(), 'updated_at' => now()]);
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', ['devices.view', 'devices.manage'])->pluck('id');
        if ($permissionIds->isNotEmpty()) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }

        Schema::dropIfExists('agent_commands');
        Schema::dropIfExists('agent_events');
        Schema::dropIfExists('agent_sync_batches');
        Schema::dropIfExists('device_access_tokens');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('agent_enrollments');
    }
};
