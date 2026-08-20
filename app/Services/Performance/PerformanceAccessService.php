<?php
namespace App\Services\Performance;

use App\Models\WorkspaceMember;
use App\Services\Access\WorkScopeService;

/** Provides performance access service behavior within the WorkIntel application. */ class PerformanceAccessService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly WorkScopeService $workScope) {}

    /** @return array<int,int> */
    /** Handles the visible member ids operation for the current WorkIntel workflow. */ public function visibleMemberIds(WorkspaceMember $actor): array
    {
        if ($actor->hasPermission('performance.view_all')) {
            return WorkspaceMember::query()->where('workspace_id',$actor->workspace_id)->where('status','active')->pluck('id')->map(fn($id)=>(int)$id)->all();
        }
        if ($actor->hasPermission('performance.view_team')) return $this->workScope->teamMemberIds($actor);
        return [(int)$actor->id];
    }

    /** Determines whether the can view condition is satisfied. */ public function canView(WorkspaceMember $actor, WorkspaceMember $target): bool
    {
        return (int)$actor->workspace_id===(int)$target->workspace_id && in_array((int)$target->id,$this->visibleMemberIds($actor),true);
    }

    /** Handles the assert can view operation for the current WorkIntel workflow. */ public function assertCanView(WorkspaceMember $actor, WorkspaceMember $target): void
    {
        abort_unless($this->canView($actor,$target),403,'This employee is outside your performance scope.');
    }

    /** Handles the assert can manage operation for the current WorkIntel workflow. */ public function assertCanManage(WorkspaceMember $actor, WorkspaceMember $target): void
    {
        if ((int)$actor->id===(int)$target->id && $actor->hasPermission('performance.view_own')) return;
        abort_unless($actor->hasPermission('performance.manage') && $this->canView($actor,$target),403,'You cannot manage this employee performance record.');
    }
}
