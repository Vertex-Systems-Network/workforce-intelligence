<?php

namespace App\Services\HRIS;

use App\Models\WorkspaceMember;

/** Provides hris access service behavior within the WorkIntel application. */ class HrisAccessService
{
    /** Determines whether the can view member condition is satisfied. */ public function canViewMember(WorkspaceMember $actor, WorkspaceMember $target): bool
    {
        if ((int) $actor->workspace_id !== (int) $target->workspace_id) return false;
        if ((int) $actor->id === (int) $target->id) return $actor->hasPermission('hris.view_own') || $actor->hasPermission('hris.view_all') || $actor->hasPermission('hris.manage');
        if ($actor->hasPermission('hris.view_all') || $actor->hasPermission('hris.manage')) return true;
        if (! $actor->hasPermission('hris.view_team')) return false;

        return (int) $target->manager_id === (int) $actor->id;
    }

    /** Determines whether the can view sensitive condition is satisfied. */ public function canViewSensitive(WorkspaceMember $actor, WorkspaceMember $target): bool
    {
        if ((int) $actor->workspace_id !== (int) $target->workspace_id) return false;
        if ((int) $actor->id === (int) $target->id) return $actor->hasPermission('hris.view_own') || $actor->hasPermission('hris.view_all') || $actor->hasPermission('hris.manage');
        return $actor->hasPermission('hris.view_all') || $actor->hasPermission('hris.manage');
    }

    /** Handles the assert can view operation for the current WorkIntel workflow. */ public function assertCanView(WorkspaceMember $actor, WorkspaceMember $target): void
    {
        abort_unless($this->canViewMember($actor, $target), 403, 'You do not have access to this employee HR profile.');
    }

    /** Handles the assert can view sensitive operation for the current WorkIntel workflow. */ public function assertCanViewSensitive(WorkspaceMember $actor, WorkspaceMember $target): void
    {
        abort_unless($this->canViewSensitive($actor, $target), 403, 'This HR information is restricted.');
    }
}
