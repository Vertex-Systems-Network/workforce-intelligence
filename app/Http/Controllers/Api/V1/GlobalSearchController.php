<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\Task;
use App\Models\WorkspaceMember;
use App\Services\Access\WorkScopeService;
use App\Services\Modules\WorkspaceModuleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Provides permission-aware cross-module entity discovery for the application shell. */
class GlobalSearchController extends Controller
{
    /** Search only entities that the current workspace member is already authorized to view. */
    public function __invoke(Request $request, WorkScopeService $scope, WorkspaceModuleService $modules): JsonResponse
    {
        $data = $request->validate([
            'q' => 'required|string|min:2|max:120',
            'limit' => 'nullable|integer|min:1|max:8',
        ]);
        $workspace = $request->attributes->get('workspace');
        /** @var WorkspaceMember $viewer */
        $viewer = $request->attributes->get('workspaceMember');
        $query = trim((string) $data['q']);
        $limit = (int) ($data['limit'] ?? 5);
        $results = [];

        if ($modules->isEnabled($workspace, 'people') && $this->hasAny($viewer, ['people.view', 'people.view_team', 'people.view_all', 'people.manage'])) {
            $people = WorkspaceMember::query()
                ->with(['user:id,first_name,last_name,email', 'department:id,name'])
                ->where('workspace_id', $workspace->id)
                ->where('status', '!=', 'archived')
                ->where(function (Builder $builder) use ($query): void {
                    $builder->where('employee_code', 'like', '%'.$query.'%')
                        ->orWhere('job_title', 'like', '%'.$query.'%')
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->where('first_name', 'like', '%'.$query.'%')
                            ->orWhere('last_name', 'like', '%'.$query.'%')
                            ->orWhere('email', 'like', '%'.$query.'%'));
                });
            $scope->scopePeople($people, $viewer)->limit($limit)->get()->each(function (WorkspaceMember $member) use (&$results): void {
                $name = trim((string) $member->user?->first_name.' '.(string) $member->user?->last_name);
                $results[] = [
                    'kind' => 'person', 'id' => $member->id, 'page' => 'people', 'title' => $name ?: 'Employee',
                    'subtitle' => collect([$member->job_title, $member->department?->name, $member->user?->email])->filter()->implode(' · '),
                ];
            });
        }

        if ($modules->isEnabled($workspace, 'projects') && $this->hasAny($viewer, ['projects.view', 'projects.view_assigned', 'projects.view_all', 'projects.manage'])) {
            $projects = Project::query()->with('client:id,name,company_name')->where('workspace_id', $workspace->id)->where('status', '!=', 'archived')
                ->where(fn (Builder $builder) => $builder->where('name', 'like', '%'.$query.'%')->orWhere('code', 'like', '%'.$query.'%'));
            $scope->scopeProjects($projects, $viewer)->limit($limit)->get()->each(function (Project $project) use (&$results): void {
                $results[] = [
                    'kind' => 'project', 'id' => $project->id, 'page' => 'projects', 'title' => $project->name,
                    'subtitle' => collect([$project->code, $project->client?->company_name ?: $project->client?->name, $project->status])->filter()->implode(' · '),
                ];
            });
        }

        if ($modules->isEnabled($workspace, 'tasks') && $this->hasAny($viewer, ['tasks.view', 'tasks.view_own', 'tasks.view_team', 'tasks.view_all', 'tasks.manage_team', 'tasks.manage'])) {
            $tasks = Task::query()->with(['project:id,name,code', 'workflowStatus:id,name'])->where('workspace_id', $workspace->id)
                ->where('title', 'like', '%'.$query.'%');
            $scope->scopeTasks($tasks, $viewer)->latest('updated_at')->limit($limit)->get()->each(function (Task $task) use (&$results): void {
                $results[] = [
                    'kind' => 'task', 'id' => $task->id, 'page' => 'tasks', 'title' => $task->title,
                    'subtitle' => collect([$task->project?->name, $task->workflowStatus?->name ?: $task->status, $task->priority])->filter()->implode(' · '),
                ];
            });
        }

        if ($modules->isEnabled($workspace, 'clients') && $this->hasAny($viewer, ['clients.view', 'clients.manage'])) {
            Client::query()->where('workspace_id', $workspace->id)->where('status', '!=', 'archived')
                ->where(fn (Builder $builder) => $builder->where('name', 'like', '%'.$query.'%')->orWhere('company_name', 'like', '%'.$query.'%')->orWhere('email', 'like', '%'.$query.'%'))
                ->orderBy('name')->limit($limit)->get()->each(function (Client $client) use (&$results): void {
                    $results[] = [
                        'kind' => 'client', 'id' => $client->id, 'page' => 'clients', 'title' => $client->company_name ?: $client->name,
                        'subtitle' => collect([$client->company_name ? $client->name : null, $client->email, $client->status])->filter()->implode(' · '),
                    ];
                });
        }

        if ($this->hasAny($viewer, ['media.view', 'media.manage'])) {
            MediaAsset::query()->where('workspace_id', $workspace->id)->where('status', 'ready')
                ->where(fn (Builder $builder) => $builder->where('name', 'like', '%'.$query.'%')->orWhere('original_name', 'like', '%'.$query.'%')->orWhere('alt_text', 'like', '%'.$query.'%'))
                ->latest('updated_at')->limit($limit)->get(['id', 'name', 'original_name', 'mime_type', 'size_bytes'])->each(function (MediaAsset $asset) use (&$results): void {
                    $results[] = [
                        'kind' => 'media', 'id' => $asset->id, 'page' => 'media', 'title' => $asset->name ?: $asset->original_name,
                        'subtitle' => collect([$asset->mime_type, $asset->original_name !== $asset->name ? $asset->original_name : null])->filter()->implode(' · '),
                    ];
                });
        }

        return response()->json(['data' => array_values($results), 'query' => $query]);
    }

    /** Return true when the member owns at least one listed permission. */
    private function hasAny(WorkspaceMember $member, array $permissions): bool
    {
        foreach ($permissions as $permission) if ($member->hasPermission($permission)) return true;
        return false;
    }
}
