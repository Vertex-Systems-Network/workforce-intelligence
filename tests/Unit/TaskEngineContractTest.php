<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Provides p5 task engine contract test behavior within the WorkIntel application. */ class TaskEngineContractTest extends TestCase
{
    /** Handles the test frontend uses real drag drop rich text and task engine contracts operation for the current WorkIntel workflow. */ public function test_frontend_uses_real_drag_drop_rich_text_and_task_engine_contracts(): void
    {
        $root = dirname(__DIR__, 2);
        $board = file_get_contents($root.'/resources/js/components/TaskBoard.tsx');
        $editor = file_get_contents($root.'/resources/js/components/RichTextEditor.tsx');
        $tasks = file_get_contents($root.'/resources/js/pages/Tasks.tsx');
        $workflow = file_get_contents($root.'/resources/js/pages/tasks/WorkflowManager.tsx');
        $package = file_get_contents($root.'/package.json');

        $this->assertStringContainsString('@dnd-kit/core', $package);
        $this->assertStringContainsString('@dnd-kit/sortable', $package);
        $this->assertStringContainsString('@tiptap/react', $package);
        $this->assertStringContainsString('DndContext', $board);
        $this->assertStringContainsString('useSortable', $board);
        $this->assertStringContainsString('useEditor', $editor);
        $this->assertStringContainsString('assignee_ids', $tasks);
        $this->assertStringContainsString('observer_ids', $tasks);
        $this->assertStringContainsString('checklist', $tasks);
        $this->assertStringContainsString('Task workflow', $workflow);
    }

    /** Handles the test backend contract contains workflow statuses tags observers checklists and activity operation for the current WorkIntel workflow. */ public function test_backend_contract_contains_workflow_statuses_tags_observers_checklists_and_activity(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = file_get_contents($root.'/database/migrations/2026_08_12_000600_create_task_engine_v2.php');
        $scope = file_get_contents($root.'/app/Services/Access/WorkScopeService.php');
        $routes = file_get_contents($root.'/routes/api.php');

        foreach (['task_statuses','task_status_transitions','task_tags','task_observers','task_checklist_items','task_relations','task_activities'] as $table) {
            $this->assertStringContainsString($table, $migration);
        }
        $this->assertStringContainsString("orWhereHas('observers'", $scope);
        $this->assertStringContainsString("/tasks/{task}/move", $routes);
        $this->assertStringContainsString("/task-workflow/statuses/{status}/transitions", $routes);
    }
}
