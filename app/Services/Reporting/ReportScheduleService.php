<?php

namespace App\Services\Reporting;

use App\Models\ReportSchedule;
use App\Models\WorkspaceMember;
use App\Models\Workspace;
use App\Services\Billing\EntitlementService;
use App\Services\Notifications\WorkspaceNotificationService;
use App\Services\Integrations\WebhookService;
use Carbon\CarbonImmutable;

/** Provides report schedule service behavior within the WorkIntel application. */ class ReportScheduleService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly ReportExecutionService $execution, private readonly ReportExportService $exports, private readonly EntitlementService $entitlements, private readonly WorkspaceNotificationService $notifications, private readonly WebhookService $webhooks) {}

    /** Handles the calculate next run operation for the current WorkIntel workflow. */ public function calculateNextRun(string $frequency, string $timeOfDay, string $timezone, ?int $dayOfWeek = null, ?int $dayOfMonth = null, ?CarbonImmutable $from = null): CarbonImmutable
    {
        $from = ($from ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        [$hour, $minute] = array_map('intval', explode(':', $timeOfDay));
        $candidate = $from->setTime($hour, $minute, 0);

        if ($frequency === 'daily') {
            if ($candidate->lte($from)) $candidate = $candidate->addDay();
        } elseif ($frequency === 'weekly') {
            $target = max(0, min(6, (int) ($dayOfWeek ?? 1)));
            $days = ($target - $candidate->dayOfWeek + 7) % 7;
            $candidate = $candidate->addDays($days);
            if ($candidate->lte($from)) $candidate = $candidate->addWeek();
        } else {
            $day = max(1, min(28, (int) ($dayOfMonth ?? 1)));
            $candidate = $candidate->setDay($day);
            if ($candidate->lte($from)) $candidate = $candidate->addMonth()->setDay($day);
        }
        return $candidate->utc();
    }

    /** Handles the run schedule operation for the current WorkIntel workflow. */ public function runSchedule(ReportSchedule $schedule): bool
    {
        $schedule->loadMissing(['savedReport', 'creator']);
        $workspace = Workspace::find($schedule->workspace_id);
        if (! $workspace || ! app(\App\Services\Modules\WorkspaceModuleService::class)->shouldProcessBackground($workspace, 'reports') || ! $this->entitlements->allows($workspace, 'feature.scheduled_reports')) {
            $schedule->update(['next_run_at' => $this->calculateNextRun($schedule->frequency, $schedule->time_of_day, $schedule->timezone, $schedule->day_of_week, $schedule->day_of_month)]);
            return false;
        }
        $viewer = WorkspaceMember::query()->with('roles.permissions')->where('workspace_id', $schedule->workspace_id)->where('user_id', $schedule->created_by)->first();
        if (! $viewer || ! $schedule->savedReport) {
            $schedule->update(['active' => false]);
            return false;
        }

        $run = $this->execution->runConfiguration($schedule->workspace_id, $viewer, $schedule->savedReport->name, $schedule->savedReport->configuration, $schedule->savedReport, $schedule);
        $this->exports->create($run, $schedule->export_format, $schedule->created_by);
        $schedule->update([
            'last_run_at' => now(),
            'next_run_at' => $this->calculateNextRun($schedule->frequency, $schedule->time_of_day, $schedule->timezone, $schedule->day_of_week, $schedule->day_of_month),
        ]);
        if ($schedule->creator) {
            $this->notifications->notify($workspace, $schedule->creator, 'reports', 'report.scheduled_completed', 'Scheduled report ready', $schedule->savedReport->name.' was generated and exported.', 'success', ['report_run_id' => $run->id, 'schedule_id' => $schedule->id]);
        }
        $this->webhooks->queueEvent($workspace, 'report.generated', ['report_run_id' => $run->id, 'schedule_id' => $schedule->id, 'scheduled' => true]);
        return true;
    }

    /** Handles the run due operation for the current WorkIntel workflow. */ public function runDue(): int
    {
        $count = 0;
        ReportSchedule::query()->where('active', true)->whereNotNull('next_run_at')->where('next_run_at', '<=', now())->orderBy('id')->chunkById(50, function ($schedules) use (&$count) {
            foreach ($schedules as $schedule) {
                try {
                    if ($this->runSchedule($schedule)) $count++;
                } catch (\Throwable $exception) {
                    report($exception);
                    $schedule->update([
                        'next_run_at' => $this->calculateNextRun($schedule->frequency, $schedule->time_of_day, $schedule->timezone, $schedule->day_of_week, $schedule->day_of_month),
                    ]);
                }
            }
        });
        return $count;
    }
}
