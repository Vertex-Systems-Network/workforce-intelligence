<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/** Provides explicit CLI-only maintenance-mode controls for disaster-recovery windows. */
class OperationsMaintenanceMode extends Command
{
    protected $signature='workintel:operations-maintenance {action : status, down or up} {--confirm=}';
    protected $description='Inspect or change Laravel maintenance mode for an approved operations window.';

    /** Change maintenance mode only after explicit confirmation for mutating actions. */
    public function handle(): int
    {
        $action=(string)$this->argument('action');
        if($action==='status'){$this->line(app()->isDownForMaintenance()?'Maintenance mode is ACTIVE.':'Maintenance mode is OFF.');return self::SUCCESS;}
        if(!in_array($action,['down','up'],true)){$this->error('Action must be status, down or up.');return self::INVALID;}
        if($this->option('confirm')!=='MAINTENANCE'){$this->error('Pass --confirm=MAINTENANCE for a mutating maintenance action.');return self::INVALID;}
        if($action==='up'){Artisan::call('up');$this->info('Maintenance mode disabled.');return self::SUCCESS;}
        $secret=Str::random(40);Artisan::call('down',['--secret'=>$secret,'--refresh'=>60]);$this->warn('Maintenance mode enabled.');$this->line('Bypass URL: '.rtrim((string)config('app.url'),'/').'/'.$secret);return self::SUCCESS;
    }
}
