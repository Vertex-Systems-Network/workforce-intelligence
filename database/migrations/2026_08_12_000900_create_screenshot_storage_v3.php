<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('screenshot_storage_providers')) {
            Schema::create('screenshot_storage_providers', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('provider_type', 32);
                $table->boolean('enabled')->default(true);
                $table->boolean('is_primary')->default(false);
                $table->boolean('fallback_to_local')->default(true);
                $table->boolean('delete_local_after_sync')->default(false);
                $table->string('root_path', 500)->nullable();
                $table->text('encrypted_config')->nullable();
                $table->string('health_status', 24)->default('unknown');
                $table->unsignedSmallInteger('consecutive_failures')->default(0);
                $table->timestamp('last_tested_at')->nullable();
                $table->timestamp('last_success_at')->nullable();
                $table->timestamp('last_failure_at')->nullable();
                $table->text('last_error')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['workspace_id', 'name'], 'ssp_ws_name_uq');
                $table->index(['workspace_id', 'enabled', 'is_primary'], 'ssp_ws_enabled_primary_idx');
            });
        }

        if (! Schema::hasTable('screenshot_storage_jobs')) {
            Schema::create('screenshot_storage_jobs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('screenshot_id')->constrained('screenshots')->cascadeOnDelete();
                $table->foreignId('storage_provider_id')->constrained('screenshot_storage_providers')->cascadeOnDelete();
                $table->string('status', 20)->default('pending');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->unsignedSmallInteger('max_attempts')->default(8);
                $table->timestamp('next_attempt_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('remote_key', 1024)->nullable();
                $table->string('remote_object_id', 500)->nullable();
                $table->char('checksum_sha256', 64)->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();
                $table->unique(['screenshot_id', 'storage_provider_id'], 'ssj_screenshot_provider_uq');
                $table->index(['status', 'next_attempt_at'], 'ssj_status_next_idx');
                $table->index(['workspace_id', 'status'], 'ssj_ws_status_idx');
            });
        }

        if (Schema::hasTable('screenshot_settings')) {
            foreach ([
                'capture_notification_mode' => fn (Blueprint $t) => $t->string('capture_notification_mode', 24)->default('always'),
                'notify_on_upload_failure' => fn (Blueprint $t) => $t->boolean('notify_on_upload_failure')->default(true),
            ] as $column => $definition) {
                if (! Schema::hasColumn('screenshot_settings', $column)) Schema::table('screenshot_settings', $definition);
            }
        }

        if (Schema::hasTable('screenshots')) {
            $columns = [
                'storage_provider_id' => fn (Blueprint $t) => $t->foreignId('storage_provider_id')->nullable()->constrained('screenshot_storage_providers')->nullOnDelete(),
                'storage_status' => fn (Blueprint $t) => $t->string('storage_status', 24)->default('local'),
                'checksum_sha256' => fn (Blueprint $t) => $t->char('checksum_sha256', 64)->nullable(),
                'remote_key' => fn (Blueprint $t) => $t->string('remote_key', 1024)->nullable(),
                'remote_object_id' => fn (Blueprint $t) => $t->string('remote_object_id', 500)->nullable(),
                'storage_verified_at' => fn (Blueprint $t) => $t->timestamp('storage_verified_at')->nullable(),
                'storage_error' => fn (Blueprint $t) => $t->text('storage_error')->nullable(),
            ];
            foreach ($columns as $column => $definition) if (! Schema::hasColumn('screenshots', $column)) Schema::table('screenshots', $definition);
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => 'screenshots.storage_manage'],
                ['name' => 'Screenshots Storage Manage', 'group' => 'Screenshots']
            );
            if (Schema::hasTable('roles') && Schema::hasTable('role_permissions')) {
                $permissionId = DB::table('permissions')->where('slug', 'screenshots.storage_manage')->value('id');
                foreach (DB::table('roles')->whereIn('slug', ['owner', 'admin'])->pluck('id') as $roleId) {
                    DB::table('role_permissions')->insertOrIgnore([
                        'role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }

        if (Schema::hasTable('subscription_plans') && Schema::hasTable('plan_entitlements')) {
            foreach (['free'=>false,'silver'=>false,'gold'=>true,'platinum'=>true] as $slug => $enabled) {
                $planId = DB::table('subscription_plans')->where('slug', $slug)->value('id');
                if (! $planId) continue;
                DB::table('plan_entitlements')->updateOrInsert(
                    ['subscription_plan_id'=>$planId,'key'=>'feature.external_screenshot_storage'],
                    ['value_type'=>'boolean','value'=>json_encode(['value'=>$enabled]),'label'=>'External Screenshot Storage','created_at'=>now(),'updated_at'=>now()]
                );
            }
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('screenshot_storage_jobs');
        if (Schema::hasTable('screenshots') && Schema::hasColumn('screenshots', 'storage_provider_id')) {
            Schema::table('screenshots', function (Blueprint $table) {
                $table->dropForeign(['storage_provider_id']);
                $table->dropColumn(['storage_provider_id','storage_status','checksum_sha256','remote_key','remote_object_id','storage_verified_at','storage_error']);
            });
        }
        if (Schema::hasTable('screenshot_settings') && Schema::hasColumn('screenshot_settings', 'capture_notification_mode')) {
            Schema::table('screenshot_settings', fn (Blueprint $table) => $table->dropColumn(['capture_notification_mode','notify_on_upload_failure']));
        }
        Schema::dropIfExists('screenshot_storage_providers');
    }
};
