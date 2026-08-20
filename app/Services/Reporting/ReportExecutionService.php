<?php

namespace App\Services\Reporting;

use App\Models\ReportRun;
use App\Models\ReportSchedule;
use App\Models\SavedReport;
use App\Models\WorkspaceMember;
use Illuminate\Support\Str;
use Throwable;

/** Provides report execution service behavior within the WorkIntel application. */ class ReportExecutionService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly ReportQueryService $queryService) {}

    /** Handles the run configuration operation for the current WorkIntel workflow. */ public function runConfiguration(int $workspaceId, WorkspaceMember $viewer, string $name, array $configuration, ?SavedReport $savedReport = null, ?ReportSchedule $schedule = null): ReportRun
    {
        $run = ReportRun::create([
            'uuid' => (string) Str::uuid(), 'workspace_id' => $workspaceId, 'saved_report_id' => $savedReport?->id,
            'report_schedule_id' => $schedule?->id, 'requested_by' => $viewer->user_id, 'name' => $name,
            'dataset' => (string) ($configuration['dataset'] ?? 'time_entries'), 'configuration' => $configuration,
            'status' => 'running', 'started_at' => now(),
        ]);

        try {
            $result = $this->queryService->execute($workspaceId, $viewer, $configuration);
            $run->update([
                'dataset' => $result['dataset'], 'configuration' => $result['configuration'], 'status' => 'completed',
                'row_count' => $result['row_count'], 'columns' => $result['columns'],
                'result_rows' => json_encode($result['rows'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'summary' => $result['summary'], 'completed_at' => now(), 'error_message' => null,
            ]);
            if ($savedReport) $savedReport->update(['last_run_at' => now()]);
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'completed_at' => now(), 'error_message' => mb_substr($exception->getMessage(), 0, 5000)]);
            throw $exception;
        }

        return $run->fresh(['exports']);
    }
}
