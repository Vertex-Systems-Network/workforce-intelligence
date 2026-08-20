<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Billing\EntitlementService;
use App\Services\Access\WorkScopeService;
use App\Http\Requests\Projects\ProjectRequest;
use App\Models\Client;
use App\Models\Project;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Provides project controller behavior within the WorkIntel application. */ class ProjectController extends Controller
{
    /** Returns the requested resource collection. */ public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $viewer = $request->attributes->get('workspaceMember');
        $query = Project::query()
            ->with(['client:id,name,company_name', 'members.user:id,first_name,last_name'])
            ->withCount(['tasks', 'members'])
            ->where('workspace_id', $workspace->id)
            ->where('status', '!=', 'archived');

        $projects = app(WorkScopeService::class)
            ->scopeProjects($query, $viewer)
            ->orderBy('name')
            ->get();

        $canSeeFinancialFields = $viewer->hasPermission('projects.manage')
            || $viewer->hasPermission('projects.view_all')
            || $viewer->hasPermission('projects.view');

        if (! $canSeeFinancialFields) {
            $projects->each(function (Project $project) use ($workspace): void {
                $project->budget_type = 'none';
                $project->budget_amount = null;
                $project->estimated_minutes = null;
                $project->currency = $workspace->currency;
            });
        }

        return response()->json(['data' => $projects]);
    }

    /** Creates and persists the requested resource. */ public function store(ProjectRequest $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        app(EntitlementService::class)->assertWithinLimit($workspace, 'projects', $workspace->projects()->where('status', '!=', 'archived')->count());
        $data = $request->validated();

        $this->validateRelatedRecords($workspace->id, $data);
        $this->ensureUniqueCode($workspace->id, $data['code'] ?? null);

        $project = Project::create([
            'workspace_id' => $workspace->id,
            'created_by' => $request->user()->id,
            'completed_at' => ($data['status'] ?? 'active') === 'completed' ? now() : null,
            ...collect($data)->except('member_ids')->all(),
        ]);

        if (array_key_exists('member_ids', $data)) {
            $project->members()->sync($data['member_ids']);
        }

        return response()->json(['data' => $this->loadProject($project)], 201);
    }

    /** Updates update data for the requested resource. */ public function update(ProjectRequest $request, Project $project): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureWorkspaceProject($workspace->id, $project);
        $data = $request->validated();

        if ($project->status === 'archived' && ($data['status'] ?? null) !== 'archived') {
            app(EntitlementService::class)->assertWithinLimit($workspace, 'projects', $workspace->projects()->where('status', '!=', 'archived')->count());
        }

        $this->validateRelatedRecords($workspace->id, $data);
        $this->ensureUniqueCode($workspace->id, $data['code'] ?? null, $project->id);

        $update = collect($data)->except('member_ids')->all();
        if (($data['status'] ?? $project->status) === 'completed' && $project->status !== 'completed') {
            $update['completed_at'] = now();
        } elseif (($data['status'] ?? $project->status) !== 'completed') {
            $update['completed_at'] = null;
        }
        $project->update($update);

        if (array_key_exists('member_ids', $data)) {
            $project->members()->sync($data['member_ids']);
        }

        return response()->json(['data' => $this->loadProject($project->fresh())]);
    }

    /** Removes destroy data from the requested resource. */ public function destroy(Request $request, Project $project): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureWorkspaceProject($workspace->id, $project);

        $project->update(['status' => 'archived']);

        return response()->json(['message' => 'Project archived.']);
    }

    /** Validates validate related records input before it is processed. */ private function validateRelatedRecords(int $workspaceId, array $data): void
    {
        if (! empty($data['client_id'])) {
            Client::query()->where('workspace_id', $workspaceId)->findOrFail($data['client_id']);
        }

        if (! empty($data['member_ids'])) {
            $validCount = WorkspaceMember::query()
                ->where('workspace_id', $workspaceId)
                ->whereIn('id', $data['member_ids'])
                ->count();

            if ($validCount !== count(array_unique($data['member_ids']))) {
                throw ValidationException::withMessages(['member_ids' => ['One or more selected members do not belong to this workspace.']]);
            }
        }
    }

    /** Handles the ensure unique code operation for the current WorkIntel workflow. */ private function ensureUniqueCode(int $workspaceId, ?string $code, ?int $ignoreProjectId = null): void
    {
        if (! $code) return;

        $exists = Project::query()
            ->where('workspace_id', $workspaceId)
            ->where('code', $code)
            ->when($ignoreProjectId, fn ($query) => $query->where('id', '!=', $ignoreProjectId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['code' => ['This project code is already in use.']]);
        }
    }

    /** Handles the ensure workspace project operation for the current WorkIntel workflow. */ private function ensureWorkspaceProject(int $workspaceId, Project $project): void
    {
        abort_unless($project->workspace_id === $workspaceId, 404);
    }

    /** Handles the load project operation for the current WorkIntel workflow. */ private function loadProject(Project $project): Project
    {
        return $project->load(['client:id,name,company_name', 'members.user:id,first_name,last_name'])->loadCount(['tasks', 'members']);
    }
}
