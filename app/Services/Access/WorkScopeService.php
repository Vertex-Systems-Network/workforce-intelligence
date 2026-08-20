<?php

namespace App\Services\Access;

use App\Models\Project;
use App\Models\Task;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Builder;

/** Provides work scope service behavior within the WorkIntel application. */ class WorkScopeService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly RoleScopeService $roleScopes) {}

    /** @return array<int, int> */
    /** Handles the team member ids operation for the current WorkIntel workflow. */ public function teamMemberIds(WorkspaceMember $member, string $resource = 'people'): array
    {
        $explicit = $this->roleScopes->visibleMemberIds($member, $resource);
        if ($explicit !== null) return $explicit;

        $teamIds = $member->teams()->pluck('teams.id');

        return WorkspaceMember::query()
            ->where('workspace_id', $member->workspace_id)
            ->where('status', 'active')
            ->where(function (Builder $query) use ($member, $teamIds) {
                $query->whereKey($member->id)
                    ->orWhere('manager_id', $member->id)
                    ->when($teamIds->isNotEmpty(), fn (Builder $builder) => $builder->orWhereHas(
                        'teams',
                        fn (Builder $team) => $team->whereIn('teams.id', $teamIds)
                    ));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** Handles the scope people operation for the current WorkIntel workflow. */ public function scopePeople(Builder $query, WorkspaceMember $viewer): Builder
    {
        $explicit = $this->roleScopes->visibleMemberIds($viewer, 'people');
        if ($explicit !== null) return $query->whereIn('workspace_members.id', $explicit);

        if ($this->hasAny($viewer, ['people.manage', 'people.view_all', 'people.view'])) return $query;
        if ($viewer->hasPermission('people.view_team')) return $query->whereIn('workspace_members.id', $this->teamMemberIds($viewer, 'people'));
        return $query->whereKey($viewer->id);
    }

    /** Handles the scope projects operation for the current WorkIntel workflow. */ public function scopeProjects(Builder $query, WorkspaceMember $viewer): Builder
    {
        if ($this->hasAny($viewer, ['projects.manage', 'projects.view_all', 'projects.view'])) return $query;
        $explicit = $this->roleScopes->visibleMemberIds($viewer, 'projects');
        if ($explicit !== null) {
            if (in_array((int)$viewer->id,$explicit,true) && count($explicit)===1) {
                return $query->whereHas('members', fn (Builder $members) => $members->where('workspace_members.id', $viewer->id));
            }
            return $query->whereHas('members', fn (Builder $members) => $members->whereIn('workspace_members.id', $explicit));
        }

        if ($viewer->hasPermission('projects.view_assigned')) return $query->whereHas('members', fn (Builder $members) => $members->where('workspace_members.id', $viewer->id));
        return $query->whereRaw('1 = 0');
    }

    /** Handles the scope tasks operation for the current WorkIntel workflow. */ public function scopeTasks(Builder $query, WorkspaceMember $viewer): Builder
    {
        if ($this->hasAny($viewer, ['tasks.manage', 'tasks.view_all', 'tasks.view'])) return $query;
        $explicit = $this->roleScopes->visibleMemberIds($viewer, 'tasks');
        if ($explicit !== null) return $this->scopeTasksForMemberIds($query, $explicit);
        if ($this->hasAny($viewer, ['tasks.manage_team', 'tasks.view_team'])) {
            return $this->scopeTasksForMemberIds($query, $this->teamMemberIds($viewer, 'tasks'));
        }
        if ($viewer->hasPermission('tasks.view_own')) return $this->scopeTasksForMemberIds($query, [$viewer->id]);
        return $query->whereRaw('1 = 0');
    }

    /** @param array<int,int> $memberIds */
    /** Handles the scope tasks for member ids operation for the current WorkIntel workflow. */ private function scopeTasksForMemberIds(Builder $query, array $memberIds): Builder
    {
        if ($memberIds === []) return $query->whereRaw('1 = 0');
        return $query->where(function (Builder $scope) use ($memberIds) {
            $scope->whereIn('owner_member_id', $memberIds)
                ->orWhereHas('assignees', fn (Builder $assignees) => $assignees->whereIn('workspace_members.id', $memberIds))
                ->orWhereHas('observers', fn (Builder $observers) => $observers->whereIn('workspace_members.id', $memberIds));
        });
    }

    /** Determines whether the can view project condition is satisfied. */ public function canViewProject(WorkspaceMember $viewer, Project $project): bool
    {
        if ((int) $project->workspace_id !== (int) $viewer->workspace_id) return false;
        if ($this->hasAny($viewer, ['projects.manage', 'projects.view_all', 'projects.view'])) return true;
        $explicit = $this->roleScopes->visibleMemberIds($viewer, 'projects');
        if ($explicit !== null) return $project->members()->whereIn('workspace_members.id',$explicit)->exists();
        return $viewer->hasPermission('projects.view_assigned') && $project->members()->where('workspace_members.id', $viewer->id)->exists();
    }

    /** Determines whether the can view task condition is satisfied. */ public function canViewTask(WorkspaceMember $viewer, Task $task): bool
    {
        if ((int) $task->workspace_id !== (int) $viewer->workspace_id) return false;
        if ($this->hasAny($viewer, ['tasks.manage', 'tasks.view_all', 'tasks.view'])) return true;
        $explicit = $this->roleScopes->visibleMemberIds($viewer, 'tasks');
        if ($explicit !== null) return $this->taskTouchesMembers($task, $explicit);
        if ($this->hasAny($viewer, ['tasks.manage_team', 'tasks.view_team'])) return $this->taskTouchesMembers($task, $this->teamMemberIds($viewer,'tasks'));
        return $viewer->hasPermission('tasks.view_own') && $this->taskTouchesMembers($task, [$viewer->id]);
    }

    /** Determines whether the can manage task condition is satisfied. */ public function canManageTask(WorkspaceMember $viewer, Task $task): bool
    {
        if ((int) $task->workspace_id !== (int) $viewer->workspace_id) return false;
        if ($viewer->hasPermission('tasks.manage')) return true;
        if (! $viewer->hasPermission('tasks.manage_team')) return false;

        $explicit = $this->roleScopes->visibleMemberIds($viewer, 'tasks');
        $allowed = $explicit ?? $this->teamMemberIds($viewer, 'tasks');
        if ($this->taskOwnedOrAssignedToMembers($task, $allowed)) return true;

        // An unassigned task in a project visible to the team manager remains
        // manageable so it can be triaged and assigned instead of becoming an
        // unreachable orphan.
        if (! $task->owner_member_id && ! $task->assignees()->exists()) {
            $project = $task->relationLoaded('project') ? $task->project : $task->project()->first();
            return $project ? $this->canViewProject($viewer, $project) : false;
        }
        return false;
    }

    /** @param array<int,int> $memberIds */
    /** Handles the task touches members operation for the current WorkIntel workflow. */ private function taskTouchesMembers(Task $task, array $memberIds): bool
    {
        if ($memberIds === []) return false;
        if ($task->owner_member_id && in_array((int) $task->owner_member_id, $memberIds, true)) return true;
        if ($task->assignees()->whereIn('workspace_members.id', $memberIds)->exists()) return true;
        return $task->observers()->whereIn('workspace_members.id', $memberIds)->exists();
    }

    /** @param array<int,int> $memberIds */
    /** Handles the task owned or assigned to members operation for the current WorkIntel workflow. */ private function taskOwnedOrAssignedToMembers(Task $task, array $memberIds): bool
    {
        if ($memberIds === []) return false;
        if ($task->owner_member_id && in_array((int) $task->owner_member_id, $memberIds, true)) return true;
        return $task->assignees()->whereIn('workspace_members.id', $memberIds)->exists();
    }

    /** @param array<int, string> $permissions */
    /** Determines whether the has any condition is satisfied. */ private function hasAny(WorkspaceMember $member, array $permissions): bool
    {
        foreach ($permissions as $permission) if ($member->hasPermission($permission)) return true;
        return false;
    }
}
