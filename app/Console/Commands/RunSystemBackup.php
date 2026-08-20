<?php

namespace App\Console\Commands;

use App\Services\Operations\SystemOperationsService;
use Illuminate\Console\Command;

/** Runs one scheduled or manually-invoked platform backup. */
class RunSystemBackup extends Command
{
    protected $signature='workintel:backup {--type=full : database or full}';
    protected $description='Create and verify a production database/private-storage backup.';

    /** Execute the backup and return failure when verification did not complete. */
    public function handle(SystemOperationsService $service): int
    {
        $type=(string)$this->option('type');if(!in_array($type,['database','full'],true)){$this->error('Backup type must be database or full.');return self::INVALID;}
        $run=$service->run($type);$this->line("Backup {$run->uuid}: {$run->status}");if($run->failure_message)$this->error($run->failure_message);return $run->status==='verified'?self::SUCCESS:self::FAILURE;
    }
}
