<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceModuleEvent;
use App\Services\Access\RoleAccessService;
use App\Services\Modules\WorkspaceModuleService;
use App\Support\ModuleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Provides module controller behavior within the WorkIntel application. */ class ModuleController extends Controller
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(
        private readonly WorkspaceModuleService $modules,
        private readonly RoleAccessService $roles,
    ) {}

    /** Returns the requested resource collection. */ public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $actor = $request->attributes->get('workspaceMember');

        return response()->json([
            'data' => $this->modules->catalog($workspace),
            'can_manage' => $this->isOwner($actor),
            'policy' => [
                'disable_preserves_data' => true,
                'dependency_mode' => 'safe',
                'kernel_modules' => ['authentication', 'settings', 'roles-access', 'billing', 'downloads'],
            ],
        ]);
    }

    /** Updates update data for the requested resource. */ public function update(Request $request, string $moduleKey): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $actor = $request->attributes->get('workspaceMember');
        $this->assertOwner($actor);
        abort_unless(ModuleCatalog::definition($moduleKey), 404, 'Unknown module.');

        $data = $request->validate([
            'is_enabled' => ['sometimes', 'boolean'],
            'navigation_visible' => ['sometimes', 'boolean'],
            'background_processing' => ['sometimes', 'boolean'],
            'label_override' => ['sometimes', 'nullable', 'string', 'max:80'],
            'settings' => ['sometimes', 'array', 'max:50'],
            'cascade_dependents' => ['sometimes', 'boolean'],
            'enable_dependencies' => ['sometimes', 'boolean'],
        ]);

        $row = $this->modules->update($workspace, $moduleKey, $data, $actor);
        return response()->json([
            'message' => 'Module configuration saved.',
            'data' => $row,
            'modules' => $this->modules->catalog($workspace),
        ]);
    }

    /** Handles the history operation for the current WorkIntel workflow. */ public function history(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $rows = WorkspaceModuleEvent::query()
            ->with('actor.user:id,first_name,last_name,email')
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return response()->json(['data' => $rows]);
    }

    /** Handles the reset operation for the current WorkIntel workflow. */ public function reset(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $actor = $request->attributes->get('workspaceMember');
        $this->assertOwner($actor);
        $request->validate(['confirm' => ['required', Rule::in(['RESET_MODULES'])]]);
        $this->modules->resetDefaults($workspace, $actor);
        return response()->json(['message' => 'Workspace modules reset to product defaults.', 'data' => $this->modules->catalog($workspace)]);
    }

    /** Determines whether the is owner condition is satisfied. */ private function isOwner($member): bool
    {
        return in_array('owner', $this->roles->effectiveRoles($member), true);
    }

    /** Handles the assert owner operation for the current WorkIntel workflow. */ private function assertOwner($member): void
    {
        abort_unless($this->isOwner($member), 403, 'Only the workspace Owner can enable or disable product modules.');
    }
}
