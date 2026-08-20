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
                ['Intelligence', 'intelligence.view_own'],
                ['Intelligence', 'intelligence.view_team'],
                ['Intelligence', 'intelligence.view_all'],
                ['Intelligence', 'intelligence.manage'],
                ['Intelligence', 'intelligence.rules_manage'],
            ] as [$group, $slug]) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $slug],
                    ['name' => ucwords(str_replace(['.', '_'], ' ', $slug)), 'group' => $group]
                );
            }
        }

        if (Schema::hasTable('roles') && Schema::hasTable('role_permissions') && Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->whereIn('slug', [
                'intelligence.view_own','intelligence.view_team','intelligence.view_all','intelligence.manage','intelligence.rules_manage',
            ])->pluck('id', 'slug');
            $timestamps = Schema::hasColumn('role_permissions', 'created_at') && Schema::hasColumn('role_permissions', 'updated_at');
            $grant = static function (string $roleSlug, array $slugs) use ($ids, $timestamps): void {
                foreach (DB::table('roles')->where('is_system', true)->where('slug', $roleSlug)->get(['id']) as $role) {
                    foreach ($slugs as $slug) {
                        $permissionId = $ids[$slug] ?? null;
                        if (! $permissionId) continue;
                        $row = ['role_id' => $role->id, 'permission_id' => $permissionId];
                        if ($timestamps) $row += ['created_at' => now(), 'updated_at' => now()];
                        DB::table('role_permissions')->insertOrIgnore($row);
                    }
                }
            };
            foreach (['owner','admin'] as $role) $grant($role, ['intelligence.view_own','intelligence.view_team','intelligence.view_all','intelligence.manage','intelligence.rules_manage']);
            $grant('hr', ['intelligence.view_own','intelligence.view_team','intelligence.view_all']);
            $grant('manager', ['intelligence.view_own','intelligence.view_team']);
            $grant('team-lead', ['intelligence.view_own','intelligence.view_team']);
            $grant('payroll-manager', ['intelligence.view_own','intelligence.view_all']);
            $grant('employee', ['intelligence.view_own']);
        }

        if (Schema::hasTable('subscription_plans') && Schema::hasTable('plan_entitlements')) {
            foreach (['free' => false, 'silver' => false, 'gold' => true, 'platinum' => true] as $slug => $enabled) {
                $planId = DB::table('subscription_plans')->where('slug', $slug)->value('id');
                if (! $planId) continue;
                DB::table('plan_entitlements')->updateOrInsert(
                    ['subscription_plan_id' => $planId, 'key' => 'feature.workforce_intelligence'],
                    [
                        'value_type' => 'boolean',
                        'value' => json_encode(['value' => $enabled]),
                        'label' => 'Workforce Intelligence',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        if (! Schema::hasTable('intelligence_settings')) {
            Schema::create('intelligence_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
                $table->boolean('enabled')->default(true);
                $table->unsignedSmallInteger('run_interval_minutes')->default(60);
                $table->unsignedSmallInteger('forecast_days')->default(14);
                $table->decimal('default_capacity_hours', 6, 2)->default(40);
                $table->boolean('automation_events_enabled')->default(true);
                $table->unsignedSmallInteger('snapshot_retention_days')->default(365);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('intelligence_rules')) {
            Schema::create('intelligence_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('rule_key', 100);
                $table->string('name', 160);
                $table->string('category', 40);
                $table->string('status', 20)->default('active');
                $table->string('severity', 20)->default('warning');
                $table->unsignedSmallInteger('window_days')->default(14);
                $table->decimal('threshold_value', 12, 3)->nullable();
                $table->decimal('threshold_secondary', 12, 3)->nullable();
                $table->json('config')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(100);
                $table->timestamps();
                $table->unique(['workspace_id', 'rule_key'], 'intel_rule_ws_key_uq');
                $table->index(['workspace_id', 'status', 'category'], 'intel_rule_ws_status_cat_idx');
            });
        }

        if (! Schema::hasTable('intelligence_runs')) {
            Schema::create('intelligence_runs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('trigger', 30)->default('scheduled');
                $table->string('status', 20)->default('running');
                $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->json('stats')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'status', 'started_at'], 'intel_run_ws_status_started_idx');
            });
        }

        if (! Schema::hasTable('intelligence_insights')) {
            Schema::create('intelligence_insights', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('intelligence_run_id')->nullable()->constrained('intelligence_runs')->nullOnDelete();
                $table->char('fingerprint', 64);
                $table->string('category', 40);
                $table->string('insight_type', 100);
                $table->string('scope_type', 30)->default('workspace');
                $table->unsignedBigInteger('scope_id')->nullable();
                $table->string('scope_label', 180)->nullable();
                $table->string('severity', 20)->default('warning');
                $table->string('title', 180);
                $table->text('summary');
                $table->text('explanation');
                $table->json('metrics')->nullable();
                $table->json('source_refs')->nullable();
                $table->json('recommendations')->nullable();
                $table->string('status', 20)->default('open');
                $table->boolean('auto_resolve')->default(true);
                $table->timestamp('detected_at');
                $table->timestamp('last_detected_at');
                $table->timestamp('acknowledged_at')->nullable();
                $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('resolution_note')->nullable();
                $table->timestamps();
                $table->unique(['workspace_id', 'fingerprint'], 'intel_insight_ws_fingerprint_uq');
                $table->index(['workspace_id', 'status', 'severity'], 'intel_insight_ws_status_sev_idx');
                $table->index(['workspace_id', 'category', 'last_detected_at'], 'intel_insight_ws_cat_seen_idx');
                $table->index(['workspace_id', 'scope_type', 'scope_id'], 'intel_insight_ws_scope_idx');
            });
        }

        if (! Schema::hasTable('intelligence_snapshots')) {
            Schema::create('intelligence_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->date('snapshot_date');
                $table->string('scope_key', 80);
                $table->string('scope_type', 30);
                $table->unsignedBigInteger('scope_id')->nullable();
                $table->string('metric_key', 100);
                $table->decimal('metric_value', 18, 4)->nullable();
                $table->string('unit', 30)->nullable();
                $table->json('dimensions')->nullable();
                $table->timestamp('generated_at');
                $table->unique(['workspace_id', 'snapshot_date', 'scope_key', 'metric_key'], 'intel_snap_ws_date_scope_metric_uq');
                $table->index(['workspace_id', 'metric_key', 'snapshot_date'], 'intel_snap_ws_metric_date_idx');
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('intelligence_snapshots');
        Schema::dropIfExists('intelligence_insights');
        Schema::dropIfExists('intelligence_runs');
        Schema::dropIfExists('intelligence_rules');
        Schema::dropIfExists('intelligence_settings');
    }
};
