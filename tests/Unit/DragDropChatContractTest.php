<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Provides p10 drag drop and chat contract test behavior within the WorkIntel application. */ class DragDropChatContractTest extends TestCase
{
    /** Handles the test drag drop uses gridstack for dashboard and dnd kit for sortable flows operation for the current WorkIntel workflow. */ public function test_drag_drop_uses_gridstack_for_dashboard_and_dnd_kit_for_sortable_flows(): void
    {
        $package=json_decode(file_get_contents(base_path('package.json')),true);
        $this->assertSame('^13.1.2',$package['dependencies']['gridstack']??null);
        $dashboard=file_get_contents(base_path('resources/js/components/DashboardGrid.tsx'));
        $this->assertStringContainsString("from 'gridstack'",$dashboard);
        $this->assertStringContainsString('GridStack.init',$dashboard);
        foreach(['resources/js/components/TaskBoard.tsx','resources/js/pages/Documents.tsx','resources/js/pages/Scheduling.tsx'] as $file){
            $source=file_get_contents(base_path($file));
            $this->assertStringContainsString('@dnd-kit',$source,$file.' must use dnd-kit for sortable drag/drop.');
            $this->assertStringNotContainsString('dataTransfer',$source,$file.' must not fall back to native HTML5 drag/drop.');
        }
    }

    /** Handles the test chat has private channels module permissions and project task threads operation for the current WorkIntel workflow. */ public function test_chat_has_private_channels_module_permissions_and_project_task_threads(): void
    {
        $routes=file_get_contents(base_path('routes/chat.php'));
        $channels=file_get_contents(base_path('routes/channels.php'));
        $catalog=file_get_contents(base_path('app/Support/ModuleCatalog.php'));
        foreach(['chat.view','chat.create','chat.manage','chat.moderate'] as $permission)$this->assertStringContainsString($permission,file_get_contents(base_path('app/Support/PermissionCatalog.php')));
        $this->assertStringContainsString("workspace.module:chat",$routes);
        $this->assertStringContainsString("'/options'",$routes);
        $this->assertStringContainsString('workspace.{workspaceId}.chat.{conversationId}',$channels);
        $this->assertStringContainsString("'chat' => [",$catalog);
        $page=file_get_contents(base_path('resources/js/pages/Chat.tsx'));
        foreach(['Project thread','Task thread','/api/v1/chat/options','Read by','attachments[]'] as $needle)$this->assertStringContainsString($needle,$page);
    }
}
