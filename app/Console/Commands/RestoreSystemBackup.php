<?php

namespace App\Console\Commands;

use App\Services\Operations\SystemOperationsService;
use Illuminate\Console\Command;

/** Executes an explicitly authorized disaster restore from a verified backup. */
class RestoreSystemBackup extends Command
{
    protected $signature='workintel:restore-backup {token} {--confirm=}';
    protected $description='Restore a verified backup using a short-lived hash-only authorization token.';

    /** Perform a destructive restore only after environment and confirmation safety checks pass. */
    public function handle(SystemOperationsService $service): int
    {
        if($this->option('confirm')!=='RESTORE'){$this->error('Pass --confirm=RESTORE to execute a destructive restore.');return self::INVALID;}
        if(app()->environment('production')&&!filter_var(env('WORKINTEL_ALLOW_DISASTER_RESTORE',false),FILTER_VALIDATE_BOOL)){$this->error('Production restore is disabled. Set WORKINTEL_ALLOW_DISASTER_RESTORE=true for the maintenance window.');return self::FAILURE;}
        $request=$service->restoreRequestForToken((string)$this->argument('token'));
        if(!$this->confirm("Restore backup {$request->backup->uuid} ({$request->restore_scope}) now?",false))return self::FAILURE;
        $service->restore($request);$this->info('Restore completed. Run php artisan workintel:production-doctor and application smoke tests before leaving maintenance mode.');return self::SUCCESS;
    }
}
