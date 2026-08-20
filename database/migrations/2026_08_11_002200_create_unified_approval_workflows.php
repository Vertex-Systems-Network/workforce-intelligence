<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        foreach ([
            ['Approvals', 'approvals.view_own'],
            ['Approvals', 'approvals.review'],
            ['Approvals', 'approvals.workflow_manage'],
            ['Approvals', 'approvals.audit'],
        ] as [$group, $slug]) {
            if (Schema::hasTable('permissions')) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $slug],
                    ['name' => ucwords(str_replace(['.', '_'], ' ', $slug)), 'group' => $group]
                );
            }
        }

        if (Schema::hasTable('roles') && Schema::hasTable('permissions') && Schema::hasTable('role_permissions')) {
            $permissionIds = DB::table('permissions')->pluck('id', 'slug');
            $hasTimestamps = Schema::hasColumn('role_permissions', 'created_at') && Schema::hasColumn('role_permissions', 'updated_at');
            $grant = function (string $roleSlug, array $slugs) use ($permissionIds, $hasTimestamps): void {
                $roles = DB::table('roles')->where('is_system', true)->where('slug', $roleSlug)->get(['id']);
                foreach ($roles as $role) {
                    foreach ($slugs as $slug) {
                        $permissionId = $permissionIds[$slug] ?? null;
                        if (! $permissionId) continue;
                        $row = ['role_id' => $role->id, 'permission_id' => $permissionId];
                        if ($hasTimestamps) $row += ['created_at' => now(), 'updated_at' => now()];
                        DB::table('role_permissions')->insertOrIgnore($row);
                    }
                }
            };
            foreach (['owner', 'admin'] as $role) $grant($role, ['approvals.view_own', 'approvals.review', 'approvals.workflow_manage', 'approvals.audit']);
            $grant('hr', ['approvals.view_own', 'approvals.review', 'approvals.audit']);
            $grant('manager', ['approvals.view_own', 'approvals.review']);
            $grant('team-lead', ['approvals.view_own', 'approvals.review']);
            $grant('payroll-manager', ['approvals.view_own', 'approvals.review', 'approvals.audit']);
            $grant('employee', ['approvals.view_own']);
        }

        if (! Schema::hasTable('approval_workflows')) {
            Schema::create('approval_workflows', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 140);
                $table->string('trigger_key', 80);
                $table->string('system_key', 80)->nullable();
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active');
                $table->unsignedSmallInteger('priority')->default(100);
                $table->json('conditions')->nullable();
                $table->unsignedSmallInteger('sla_hours')->default(24);
                $table->string('escalation_role_slug', 80)->nullable();
                $table->boolean('notify_requester')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'trigger_key', 'status'], 'aw_ws_trigger_status_idx');
                $table->unique(['workspace_id', 'system_key'], 'aw_ws_system_uq');
            });
        }

        if (! Schema::hasTable('approval_workflow_steps')) {
            Schema::create('approval_workflow_steps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('approval_workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
                $table->unsignedSmallInteger('position');
                $table->string('name', 120);
                $table->string('approver_type', 30)->default('manager');
                $table->string('approver_role_slug', 80)->nullable();
                $table->foreignId('approver_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->unsignedSmallInteger('required_approvals')->default(1);
                $table->boolean('allow_self_approval')->default(false);
                $table->timestamps();
                $table->unique(['approval_workflow_id', 'position'], 'aws_workflow_position_uq');
            });
        }

        if (! Schema::hasTable('approval_delegations')) {
            Schema::create('approval_delegations', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('delegator_member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('delegate_member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->string('status', 20)->default('active');
                $table->string('reason', 500)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'delegator_member_id', 'status'], 'ad_ws_delegator_status_idx');
            });
        }

        if (! Schema::hasTable('approval_requests')) {
            Schema::create('approval_requests', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('approval_workflow_id')->nullable()->constrained('approval_workflows')->nullOnDelete();
                $table->string('trigger_key', 80);
                $table->string('subject_type', 60);
                $table->unsignedBigInteger('subject_id');
                $table->foreignId('requester_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->string('title', 180);
                $table->text('summary')->nullable();
                $table->string('status', 20)->default('pending');
                $table->unsignedSmallInteger('current_step_position')->default(1);
                $table->decimal('amount', 14, 2)->nullable();
                $table->string('currency', 3)->nullable();
                $table->timestamp('submitted_at');
                $table->timestamp('due_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->json('context')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'status', 'due_at'], 'ar_ws_status_due_idx');
                $table->index(['workspace_id', 'trigger_key', 'status'], 'ar_ws_trigger_status_idx');
                $table->index(['subject_type', 'subject_id'], 'ar_subject_idx');
            });
        }

        if (! Schema::hasTable('approval_request_steps')) {
            Schema::create('approval_request_steps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
                $table->foreignId('workflow_step_id')->nullable()->constrained('approval_workflow_steps')->nullOnDelete();
                $table->unsignedSmallInteger('position');
                $table->string('name', 120);
                $table->string('approver_type', 30);
                $table->json('assigned_member_ids');
                $table->string('status', 20)->default('pending');
                $table->unsignedSmallInteger('required_approvals')->default(1);
                $table->unsignedSmallInteger('approved_count')->default(0);
                $table->boolean('allow_self_approval')->default(false);
                $table->timestamp('due_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['approval_request_id', 'position'], 'ars_request_position_uq');
                $table->index(['status', 'due_at'], 'ars_status_due_idx');
            });
        }

        if (! Schema::hasTable('approval_decisions')) {
            Schema::create('approval_decisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
                $table->foreignId('approval_request_step_id')->nullable()->constrained('approval_request_steps')->nullOnDelete();
                $table->foreignId('actor_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->string('decision', 30);
                $table->text('note')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('acted_at');
                $table->index(['approval_request_id', 'acted_at'], 'adec_request_acted_idx');
            });
        }

        if (Schema::hasTable('project_expenses')) {
            if (! Schema::hasColumn('project_expenses', 'approval_status')) Schema::table('project_expenses', fn (Blueprint $table) => $table->string('approval_status', 20)->default('approved')->after('note'));
            if (! Schema::hasColumn('project_expenses', 'reviewed_by')) Schema::table('project_expenses', fn (Blueprint $table) => $table->foreignId('reviewed_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete());
            if (! Schema::hasColumn('project_expenses', 'reviewed_at')) Schema::table('project_expenses', fn (Blueprint $table) => $table->timestamp('reviewed_at')->nullable()->after('reviewed_by'));
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('approval_decisions');
        Schema::dropIfExists('approval_request_steps');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_delegations');
        Schema::dropIfExists('approval_workflow_steps');
        Schema::dropIfExists('approval_workflows');
    }
};
