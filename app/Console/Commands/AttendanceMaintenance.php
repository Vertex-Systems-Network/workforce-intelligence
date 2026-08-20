<?php

namespace App\Console\Commands;

use App\Models\AttendancePolicy;
use App\Models\AttendanceRecord;
use App\Services\Attendance\AttendancePolicyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Provides attendance maintenance behavior within the WorkIntel application. */ class AttendanceMaintenance extends Command
{
    protected $signature = 'workintel:attendance-maintenance';
    protected $description = 'Flag stale open attendance records as missing clock-out without modifying worked time';

    /** Executes the command, job, or request handler. */ public function handle(AttendancePolicyService $policies): int
    {
        if (! Schema::hasTable('attendance_policies') || ! Schema::hasColumn('attendance_records', 'flag_type')) {
            $this->warn('Attendance 2.0 schema is not installed; skipping maintenance.');
            return self::SUCCESS;
        }

        $flagged = 0;
        AttendancePolicy::query()->where('auto_flag_missed_clock_out', true)->chunkById(100, function ($rows) use (&$flagged) {
            foreach ($rows as $policy) {
                $workspace = \App\Models\Workspace::find($policy->workspace_id);
                if (! $workspace || ! app(\App\Services\Modules\WorkspaceModuleService::class)->shouldProcessBackground($workspace, 'attendance')) continue;
                $cutoff = now()->subHours(max(4, (int) $policy->missed_clock_out_hours));
                $records = AttendanceRecord::query()
                    ->where('workspace_id', $policy->workspace_id)
                    ->whereNotNull('clock_in_at')
                    ->whereNull('clock_out_at')
                    ->whereNull('flag_type')
                    ->where('clock_in_at', '<=', $cutoff)
                    ->limit(500)
                    ->get();

                foreach ($records as $record) {
                    $record->forceFill([
                        'flag_type' => 'missing_clock_out',
                        'flagged_at' => now(),
                        'status' => 'missing_clock_out',
                    ])->save();
                    $flagged++;
                }
            }
        });

        $this->info("Flagged {$flagged} missed clock-out record(s).");
        return self::SUCCESS;
    }
}
