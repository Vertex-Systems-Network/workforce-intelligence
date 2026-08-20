<?php

namespace App\Services\Billing;

use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/** Provides billing usage service behavior within the WorkIntel application. */ class BillingUsageService
{
    /** Handles the current operation for the current WorkIntel workflow. */ public function current(Workspace $workspace): array
    {
        return [
            'members' => $workspace->members()->where('status', 'active')->count(),
            'projects' => $workspace->projects()->where('status', '!=', 'archived')->count(),
            'clients' => $workspace->clients()->where('status', '!=', 'archived')->count(),
            'devices' => $workspace->devices()->where('status', 'active')->count(),
            'screenshot_storage_bytes' => (int) DB::table('screenshots')->where('workspace_id', $workspace->id)->whereNull('deleted_at')->sum('size_bytes'),
            'saved_reports' => (int) DB::table('saved_reports')->where('workspace_id', $workspace->id)->count(),
            'scheduled_reports' => (int) DB::table('report_schedules')->where('workspace_id', $workspace->id)->where('active', true)->count(),
        ];
    }

    /** Handles the with limits operation for the current WorkIntel workflow. */ public function withLimits(Workspace $workspace, EntitlementService $entitlements): array
    {
        $usage = $this->current($workspace);
        $result = [];
        foreach (['members','projects','clients','devices','saved_reports','scheduled_reports'] as $metric) {
            $limit = (int) $entitlements->value($workspace, 'limit.'.$metric, 0);
            $used = (int) ($usage[$metric] ?? 0);
            $result[$metric] = ['used' => $used, 'limit' => $limit, 'percent' => $limit > 0 ? min(100, round(($used / $limit) * 100, 1)) : ($limit < 0 ? null : 100)];
        }
        $result['screenshot_storage_bytes'] = ['used' => $usage['screenshot_storage_bytes'], 'limit' => null, 'percent' => null];
        return $result;
    }
}
