<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('task_statuses')) {
            Schema::create('task_statuses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 80);
                $table->string('slug', 64);
                $table->string('color', 16)->default('#64748b');
                $table->string('group', 24)->default('todo');
                $table->unsignedInteger('sort_order')->default(1000);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_completed')->default(false);
                $table->boolean('is_archived')->default(false);
                $table->timestamps();
                $table->unique(['workspace_id', 'slug'], 'task_status_ws_slug_uq');
                $table->index(['workspace_id', 'is_archived', 'sort_order'], 'task_status_ws_sort_idx');
            });
        }

        if (! Schema::hasTable('task_status_transitions')) {
            Schema::create('task_status_transitions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('from_status_id')->constrained('task_statuses')->cascadeOnDelete();
                $table->foreignId('to_status_id')->constrained('task_statuses')->cascadeOnDelete();
                $table->boolean('require_comment')->default(false);
                $table->timestamps();
                $table->unique(['workspace_id', 'from_status_id', 'to_status_id'], 'task_status_transition_uq');
                $table->index(['workspace_id', 'from_status_id'], 'task_status_from_idx');
            });
        }

        if (! Schema::hasTable('task_tags')) {
            Schema::create('task_tags', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 60);
                $table->string('slug', 64);
                $table->string('color', 16)->default('#64748b');
                $table->boolean('is_archived')->default(false);
                $table->timestamps();
                $table->unique(['workspace_id', 'slug'], 'task_tag_ws_slug_uq');
                $table->index(['workspace_id', 'is_archived', 'name'], 'task_tag_ws_name_idx');
            });
        }

        if (! Schema::hasTable('task_tag_assignments')) {
            Schema::create('task_tag_assignments', function (Blueprint $table) {
                $table->foreignId('task_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tag_id')->constrained('task_tags')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['task_id', 'tag_id']);
            });
        }

        if (! Schema::hasTable('task_observers')) {
            Schema::create('task_observers', function (Blueprint $table) {
                $table->foreignId('task_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['task_id', 'member_id']);
            });
        }

        if (! Schema::hasTable('task_checklist_items')) {
            Schema::create('task_checklist_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('task_id')->constrained()->cascadeOnDelete();
                $table->string('title', 300);
                $table->unsignedInteger('sort_order')->default(1000);
                $table->boolean('is_completed')->default(false);
                $table->foreignId('assignee_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamp('due_at')->nullable();
                $table->foreignId('completed_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'task_id', 'sort_order'], 'task_checklist_task_sort_idx');
            });
        }

        if (! Schema::hasTable('task_relations')) {
            Schema::create('task_relations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('source_task_id')->constrained('tasks')->cascadeOnDelete();
                $table->foreignId('target_task_id')->constrained('tasks')->cascadeOnDelete();
                $table->string('type', 24)->default('related');
                $table->timestamps();
                $table->unique(['workspace_id', 'source_task_id', 'target_task_id', 'type'], 'task_relation_unique');
                $table->index(['workspace_id', 'source_task_id'], 'task_relation_source_idx');
            });
        }

        if (! Schema::hasTable('task_activities')) {
            Schema::create('task_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('task_id')->constrained()->cascadeOnDelete();
                $table->foreignId('actor_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->string('action', 64);
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['workspace_id', 'task_id', 'created_at'], 'task_activity_task_time_idx');
                $table->index(['workspace_id', 'action', 'created_at'], 'task_activity_action_idx');
            });
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => 'tasks.workflow_manage'],
                ['name' => 'Tasks Workflow Manage', 'group' => 'Tasks']
            );
        }

        if (Schema::hasTable('tasks')) {
            if (! Schema::hasColumn('tasks', 'task_status_id')) {
                Schema::table('tasks', fn (Blueprint $table) => $table->unsignedBigInteger('task_status_id')->nullable()->after('status'));
            }
            if (! Schema::hasColumn('tasks', 'owner_member_id')) {
                Schema::table('tasks', fn (Blueprint $table) => $table->unsignedBigInteger('owner_member_id')->nullable()->after('task_status_id'));
            }
            if (! Schema::hasColumn('tasks', 'description_html')) {
                Schema::table('tasks', fn (Blueprint $table) => $table->longText('description_html')->nullable()->after('description'));
            }
            if (! Schema::hasColumn('tasks', 'start_at')) {
                Schema::table('tasks', fn (Blueprint $table) => $table->timestamp('start_at')->nullable()->after('estimated_minutes'));
            }
            if (! Schema::hasColumn('tasks', 'position')) {
                Schema::table('tasks', fn (Blueprint $table) => $table->unsignedBigInteger('position')->default(1000)->after('due_at'));
            }
        }

        $this->seedStatusesAndMapTasks();
    }

    /** Handles the seed statuses and map tasks operation for the current WorkIntel workflow. */ private function seedStatusesAndMapTasks(): void
    {
        if (! Schema::hasTable('workspaces') || ! Schema::hasTable('task_statuses')) return;

        $defaults = [
            ['name' => 'Todo',        'slug' => 'todo',        'color' => '#64748b', 'group' => 'todo',    'sort_order' => 1000, 'is_default' => true,  'is_completed' => false],
            ['name' => 'In Progress', 'slug' => 'in_progress', 'color' => '#2563eb', 'group' => 'active',  'sort_order' => 2000, 'is_default' => false, 'is_completed' => false],
            ['name' => 'Review',      'slug' => 'review',      'color' => '#d97706', 'group' => 'review',  'sort_order' => 3000, 'is_default' => false, 'is_completed' => false],
            ['name' => 'Blocked',     'slug' => 'blocked',     'color' => '#dc2626', 'group' => 'blocked', 'sort_order' => 4000, 'is_default' => false, 'is_completed' => false],
            ['name' => 'Done',        'slug' => 'done',        'color' => '#16a34a', 'group' => 'done',    'sort_order' => 5000, 'is_default' => false, 'is_completed' => true],
        ];

        foreach (DB::table('workspaces')->pluck('id') as $workspaceId) {
            foreach ($defaults as $row) {
                DB::table('task_statuses')->updateOrInsert(
                    ['workspace_id' => $workspaceId, 'slug' => $row['slug']],
                    [...$row, 'workspace_id' => $workspaceId, 'created_at' => now(), 'updated_at' => now()]
                );
            }

            if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'task_status_id')) {
                $statusIds = DB::table('task_statuses')->where('workspace_id', $workspaceId)->pluck('id', 'slug');
                foreach ($statusIds as $slug => $statusId) {
                    DB::table('tasks')
                        ->where('workspace_id', $workspaceId)
                        ->whereNull('task_status_id')
                        ->where('status', $slug)
                        ->update(['task_status_id' => $statusId]);
                }
                if (isset($statusIds['todo'])) {
                    DB::table('tasks')->where('workspace_id', $workspaceId)->whereNull('task_status_id')->update(['task_status_id' => $statusIds['todo']]);
                }
            }
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('task_activities');
        Schema::dropIfExists('task_relations');
        Schema::dropIfExists('task_checklist_items');
        Schema::dropIfExists('task_observers');
        Schema::dropIfExists('task_tag_assignments');
        Schema::dropIfExists('task_tags');
        Schema::dropIfExists('task_status_transitions');

        if (Schema::hasTable('tasks')) {
            foreach (['task_status_id', 'owner_member_id', 'description_html', 'start_at', 'position'] as $column) {
                if (Schema::hasColumn('tasks', $column)) Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }

        Schema::dropIfExists('task_statuses');
    }
};
