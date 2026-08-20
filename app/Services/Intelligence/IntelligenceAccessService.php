<?php

namespace App\Services\Intelligence;

use App\Models\IntelligenceInsight;
use App\Models\Team;
use App\Models\WorkspaceMember;
use App\Services\Access\WorkScopeService;
use Illuminate\Database\Eloquent\Builder;

/** Provides intelligence access service behavior within the WorkIntel application. */ class IntelligenceAccessService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly WorkScopeService $workScope) {}

    /** Handles the scope insights operation for the current WorkIntel workflow. */ public function scopeInsights(Builder $query, WorkspaceMember $actor): Builder
    {
        if ($actor->hasPermission('intelligence.view_all') || $actor->hasPermission('intelligence.manage')) {
            return $this->applyCategoryRestrictions($query, $actor);
        }

        if ($actor->hasPermission('intelligence.view_team')) {
            $memberIds = $this->workScope->teamMemberIds($actor);
            $projectIds = $this->workScope->scopeProjects(
                \App\Models\Project::query()->where('workspace_id', $actor->workspace_id),
                $actor
            )->pluck('id')->map(fn ($id) => (int) $id)->all();
            $teamIds = $this->visibleTeamIds($actor);
            return $this->applyCategoryRestrictions($query->where(function (Builder $scope) use ($memberIds, $projectIds, $teamIds) {
                $scope->where(fn (Builder $q) => $q->where('scope_type', 'member')->whereIn('scope_id', $memberIds))
                    ->orWhere(fn (Builder $q) => $q->where('scope_type', 'project')->whereIn('scope_id', $projectIds));
                if ($teamIds !== []) {
                    $scope->orWhere(fn (Builder $q) => $q->where('scope_type', 'team')->whereIn('scope_id', $teamIds));
                }
            }), $actor);
        }

        return $this->applyCategoryRestrictions(
            $query->where('scope_type', 'member')->where('scope_id', $actor->id),
            $actor
        );
    }

    /** Handles the visible member ids operation for the current WorkIntel workflow. */ public function visibleMemberIds(WorkspaceMember $actor): array
    {
        if ($actor->hasPermission('intelligence.view_all') || $actor->hasPermission('intelligence.manage')) {
            return WorkspaceMember::query()->where('workspace_id', $actor->workspace_id)->where('status', 'active')->pluck('id')->map(fn ($id)=>(int)$id)->all();
        }
        if ($actor->hasPermission('intelligence.view_team')) return $this->workScope->teamMemberIds($actor);
        return [(int) $actor->id];
    }

    /** Handles the visible team ids operation for the current WorkIntel workflow. */ public function visibleTeamIds(WorkspaceMember $actor): array
    {
        if ($actor->hasPermission('intelligence.view_all') || $actor->hasPermission('intelligence.manage')) {
            return Team::query()->where('workspace_id', $actor->workspace_id)->where('status', 'active')
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        if (! $actor->hasPermission('intelligence.view_team')) return [];

        $memberIds = $this->workScope->teamMemberIds($actor);
        return Team::query()
            ->where('workspace_id', $actor->workspace_id)
            ->where('status', 'active')
            ->where(function (Builder $query) use ($actor, $memberIds) {
                $query->where('lead_id', $actor->id)
                    ->orWhereHas('members', fn (Builder $members) => $members->whereIn('workspace_members.id', $memberIds));
            })
            ->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** Handles the visible project ids operation for the current WorkIntel workflow. */ public function visibleProjectIds(WorkspaceMember $actor): array
    {
        if (! $actor->hasPermission('job_costing.view') && ! $actor->hasPermission('job_costing.manage') && ! $actor->hasPermission('projects.manage')) return [];
        if ($actor->hasPermission('intelligence.view_all') || $actor->hasPermission('intelligence.manage')) {
            return \App\Models\Project::query()->where('workspace_id', $actor->workspace_id)
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        if ($actor->hasPermission('intelligence.view_team')) {
            $memberIds = $this->visibleMemberIds($actor);
            return \App\Models\Project::query()->where('workspace_id', $actor->workspace_id)
                ->whereHas('members', fn (Builder $members) => $members->whereIn('workspace_members.id', $memberIds))
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        return [];
    }

    /** Determines whether the can view member condition is satisfied. */ public function canViewMember(WorkspaceMember $actor, int $memberId): bool { return in_array($memberId, $this->visibleMemberIds($actor), true); }
    /** Determines whether the can view project condition is satisfied. */ public function canViewProject(WorkspaceMember $actor, int $projectId): bool { return in_array($projectId, $this->visibleProjectIds($actor), true); }

    /** Determines whether the can view condition is satisfied. */ public function canView(IntelligenceInsight $insight, WorkspaceMember $actor): bool
    {
        return $this->scopeInsights(
            IntelligenceInsight::query()->where('workspace_id', $actor->workspace_id)->whereKey($insight->id),
            $actor
        )->exists();
    }

    /** Determines whether the can manage condition is satisfied. */ public function canManage(WorkspaceMember $actor): bool
    {
        return $actor->hasPermission('intelligence.manage');
    }

    /** Handles the apply category restrictions operation for the current WorkIntel workflow. */ private function applyCategoryRestrictions(Builder $query, WorkspaceMember $actor): Builder
    {
        if (! $actor->hasPermission('payroll.view_all') && ! $actor->hasPermission('payroll.manage')) {
            $query->where('category', '!=', 'payroll');
        }
        if (! $actor->hasPermission('job_costing.view') && ! $actor->hasPermission('job_costing.manage') && ! $actor->hasPermission('projects.manage')) {
            $query->where('category', '!=', 'project');
        }
        return $query;
    }
}
