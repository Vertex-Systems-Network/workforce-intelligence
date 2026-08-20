<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (Schema::hasTable('permissions')) {
            foreach ([
                ['Automations', 'automations.view'],
                ['Automations', 'automations.manage'],
                ['Automations', 'automations.runs.view'],
            ] as [$group, $slug]) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $slug],
                    ['name' => ucwords(str_replace(['.', '_'], ' ', $slug)), 'group' => $group]
                );
            }
        }

        if (Schema::hasTable('roles') && Schema::hasTable('role_permissions') && Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->whereIn('slug', ['automations.view','automations.manage','automations.runs.view'])->pluck('id', 'slug');
            $hasTimestamps = Schema::hasColumn('role_permissions', 'created_at') && Schema::hasColumn('role_permissions', 'updated_at');
            $grant = static function (string $roleSlug, array $slugs) use ($ids, $hasTimestamps): void {
                foreach (DB::table('roles')->where('is_system', true)->where('slug', $roleSlug)->get(['id']) as $role) {
                    foreach ($slugs as $slug) {
                        $permissionId = $ids[$slug] ?? null;
                        if (! $permissionId) continue;
                        $row = ['role_id' => $role->id, 'permission_id' => $permissionId];
                        if ($hasTimestamps) $row += ['created_at' => now(), 'updated_at' => now()];
                        DB::table('role_permissions')->insertOrIgnore($row);
                    }
                }
            };
            foreach (['owner','admin'] as $role) $grant($role, ['automations.view','automations.manage','automations.runs.view']);
        }

        if (Schema::hasTable('subscription_plans') && Schema::hasTable('plan_entitlements')) {
            $matrix = [
                'free' => ['feature.automations' => false, 'limit.automation_workflows' => 0],
                'silver' => ['feature.automations' => false, 'limit.automation_workflows' => 0],
                'gold' => ['feature.automations' => true, 'limit.automation_workflows' => 25],
                'platinum' => ['feature.automations' => true, 'limit.automation_workflows' => -1],
            ];
            foreach ($matrix as $slug => $items) {
                $planId = DB::table('subscription_plans')->where('slug', $slug)->value('id');
                if (! $planId) continue;
                foreach ($items as $key => $value) {
                    DB::table('plan_entitlements')->updateOrInsert(
                        ['subscription_plan_id' => $planId, 'key' => $key],
                        [
                            'value_type' => is_bool($value) ? 'boolean' : 'integer',
                            'value' => json_encode(['value' => $value]),
                            'label' => ucwords(str_replace(['feature.','limit.','_','.'], ['', '', ' ', ' '], $key)),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }

        if (! Schema::hasTable('automation_workflows')) {
            Schema::create('automation_workflows', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->string('status', 20)->default('draft');
                $table->string('trigger_type', 24)->default('event');
                $table->string('trigger_event', 120)->nullable();
                $table->json('trigger_config')->nullable();
                $table->json('conditions')->nullable();
                $table->string('condition_mode', 8)->default('all');
                $table->string('failure_policy', 20)->default('stop');
                $table->unsignedSmallInteger('max_run_seconds')->default(30);
                $table->timestamp('next_run_at')->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id','status','trigger_type'], 'auto_wf_ws_status_trigger_idx');
                $table->index(['workspace_id','trigger_event','status'], 'auto_wf_ws_event_status_idx');
                $table->index(['status','next_run_at'], 'auto_wf_status_next_idx');
            });
        }

        if (! Schema::hasTable('automation_actions')) {
            Schema::create('automation_actions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('automation_workflow_id')->constrained('automation_workflows')->cascadeOnDelete();
                $table->unsignedSmallInteger('position');
                $table->string('name', 140);
                $table->string('action_type', 30);
                $table->string('action_key', 100);
                $table->foreignId('integration_connection_id')->nullable()->constrained('integration_connections')->nullOnDelete();
                $table->json('config')->nullable();
                $table->boolean('continue_on_error')->default(false);
                $table->unsignedTinyInteger('retry_max')->default(2);
                $table->unsignedSmallInteger('timeout_seconds')->default(12);
                $table->timestamps();
                $table->unique(['automation_workflow_id','position'], 'auto_action_wf_position_uq');
            });
        }

        if (! Schema::hasTable('automation_events')) {
            Schema::create('automation_events', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('event_type', 120);
                $table->string('source', 40)->default('workspace');
                $table->string('idempotency_key', 180)->nullable();
                $table->json('payload');
                $table->timestamp('occurred_at');
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['workspace_id','event_type','created_at'], 'auto_evt_ws_type_created_idx');
                $table->unique(['workspace_id','source','idempotency_key'], 'auto_evt_ws_source_idem_uq');
            });
        }

        if (! Schema::hasTable('automation_runs')) {
            Schema::create('automation_runs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('automation_workflow_id')->constrained('automation_workflows')->cascadeOnDelete();
                $table->foreignId('automation_event_id')->nullable()->constrained('automation_events')->nullOnDelete();
                $table->string('trigger_event', 120)->nullable();
                $table->string('status', 20)->default('queued');
                $table->json('trigger_payload')->nullable();
                $table->json('context')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('next_attempt_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('error')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['workspace_id','status','created_at'], 'auto_run_ws_status_created_idx');
                $table->index(['status','next_attempt_at'], 'auto_run_status_next_idx');
            });
        }

        if (! Schema::hasTable('automation_run_steps')) {
            Schema::create('automation_run_steps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('automation_run_id')->constrained('automation_runs')->cascadeOnDelete();
                $table->foreignId('automation_action_id')->nullable()->constrained('automation_actions')->nullOnDelete();
                $table->unsignedSmallInteger('position');
                $table->string('name', 140);
                $table->string('status', 20)->default('pending');
                $table->json('input')->nullable();
                $table->json('output')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('error')->nullable();
                $table->index(['automation_run_id','position'], 'auto_step_run_position_idx');
            });
        }

        if (! Schema::hasTable('automation_incoming_hooks')) {
            Schema::create('automation_incoming_hooks', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('automation_workflow_id')->nullable()->constrained('automation_workflows')->nullOnDelete();
                $table->string('name', 140);
                $table->string('event_name', 120)->default('incoming.received');
                $table->string('token_prefix', 16);
                $table->char('token_hash', 64)->unique();
                $table->string('status', 20)->default('active');
                $table->unsignedSmallInteger('rate_limit_per_minute')->default(60);
                $table->timestamp('last_used_at')->nullable();
                $table->ipAddress('last_used_ip')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['workspace_id','status'], 'auto_hook_ws_status_idx');
            });
        }

        if (! Schema::hasTable('automation_dead_letters')) {
            Schema::create('automation_dead_letters', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('automation_run_id')->constrained('automation_runs')->cascadeOnDelete();
                $table->string('reason', 160);
                $table->json('payload')->nullable();
                $table->unsignedSmallInteger('retry_count')->default(0);
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->unique('automation_run_id', 'auto_dead_run_uq');
                $table->index(['workspace_id','resolved_at','created_at'], 'auto_dead_ws_resolved_created_idx');
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('automation_dead_letters');
        Schema::dropIfExists('automation_incoming_hooks');
        Schema::dropIfExists('automation_run_steps');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_events');
        Schema::dropIfExists('automation_actions');
        Schema::dropIfExists('automation_workflows');
    }
};
