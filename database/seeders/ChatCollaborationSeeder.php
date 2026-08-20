<?php

namespace Database\Seeders;

use App\Models\DataGovernancePolicy;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Workspace;
use App\Services\Chat\ChatWorkspaceCollaborationService;
use App\Services\Modules\WorkspaceModuleService;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Seeds chat permissions, module defaults and built-in collaboration bot identities. */
class ChatCollaborationSeeder extends Seeder
{
    /** Applies idempotent chat permission/module/bot defaults to every existing workspace. */
    public function run(): void
    {
        PermissionCatalog::sync();
        $adminPermissionIds = Permission::whereIn('slug', ['chat.view', 'chat.create', 'chat.manage', 'chat.moderate', 'chat.guests_manage', 'chat.retention_manage', 'chat.export', 'chat.legal_hold_manage', 'chat.dlp_manage'])->pluck('id');
        Role::whereIn('slug', ['owner', 'admin'])->get()->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($adminPermissionIds));

        $basicPermissionIds = Permission::whereIn('slug', ['chat.view', 'chat.create'])->pluck('id');
        Role::whereIn('slug', ['hr', 'manager', 'team-lead', 'payroll-manager', 'employee'])->get()->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($basicPermissionIds));

        $moduleService = app(WorkspaceModuleService::class);
        $collaboration = Schema::hasTable('chat_bots') ? app(ChatWorkspaceCollaborationService::class) : null;
        Workspace::query()->orderBy('id')->chunkById(100, function ($rows) use ($moduleService, $collaboration, $basicPermissionIds) {
            $rows->each(function (Workspace $workspace) use ($moduleService, $collaboration, $basicPermissionIds) {
                $moduleService->initializeWorkspace($workspace);
                if ($collaboration) $collaboration->ensureBots($workspace);
                $externalRole = Role::firstOrCreate(
                    ['workspace_id' => $workspace->id, 'slug' => 'external-collaborator'],
                    ['name' => 'External Collaborator', 'description' => 'Chat-only guest/client/vendor access.', 'is_system' => true, 'status' => 'active'],
                );
                $externalRole->permissions()->sync($basicPermissionIds);
                if (Schema::hasTable('data_governance_policies')) {
                    DataGovernancePolicy::firstOrCreate(
                        ['workspace_id' => $workspace->id, 'dataset' => 'chat_messages'],
                        [
                            'uuid' => (string) Str::uuid(),
                            'retention_days' => 3650,
                            'storage_class' => 'standard',
                            'deletion_mode' => 'hard_purge',
                            'legal_hold' => false,
                            'settings' => ['source' => 'chat_collaboration'],
                        ],
                    );
                }
            });
        });
    }
}
