<?php

namespace App\Console\Commands;

use App\Models\SystemBackupRun;
use App\Services\Operations\SystemOperationsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Runs the configured platform backup only when the persisted policy is due. */
class RunDueSystemBackup extends Command
{
    protected $signature='workintel:backup-if-due';
    protected $description='Evaluate the backup policy and create a verified full backup when due.';

    /** Evaluate frequency/time without creating duplicate backups in the same scheduled window. */
    public function handle(SystemOperationsService $service): int
    {
        $policy=$service->policy();if(!$policy->enabled){$this->line('Backup policy is disabled.');return self::SUCCESS;}
        [$hour,$minute]=array_map('intval',explode(':',$policy->run_time));$now=CarbonImmutable::now();$scheduled=$now->setTime($hour,$minute);
        if($now->lessThan($scheduled)||$now->greaterThan($scheduled->addMinutes(29))){$this->line('Backup policy is not due in this window.');return self::SUCCESS;}
        if($policy->frequency==='weekly'&&$now->dayOfWeekIso!==1){$this->line('Weekly backup is due on Monday only.');return self::SUCCESS;}
        $window=$now->startOfDay();$existing=SystemBackupRun::query()->where('created_at','>=',$window)->whereIn('status',['queued','running','completed','verified'])->exists();
        if($existing){$this->line('A backup already exists for this scheduled window.');return self::SUCCESS;}
        $run=$service->run('full');$this->line("Scheduled backup {$run->uuid}: {$run->status}");return $run->status==='verified'?self::SUCCESS:self::FAILURE;
    }
}
