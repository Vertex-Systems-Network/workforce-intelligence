<?php
namespace App\Console\Commands;
use App\Services\Automation\AutomationEngine;
use Illuminate\Console\Command;
/** Provides automation maintenance behavior within the WorkIntel application. */ class AutomationMaintenance extends Command
{
    protected $signature='workintel:automation-maintenance {--limit=100}';
    protected $description='Queue scheduled automations and execute due workflow runs.';
    /** Executes the command, job, or request handler. */ public function handle(AutomationEngine $engine): int { $result=$engine->processDue(max(1,min(500,(int)$this->option('limit'))));$this->info(sprintf('Automation maintenance: %d scheduled, %d processed, %d failed.',$result['scheduled'],$result['processed'],$result['failed']));return self::SUCCESS; }
}
