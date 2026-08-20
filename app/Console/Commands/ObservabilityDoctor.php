<?php

namespace App\Console\Commands;

use App\Services\Observability\ObservabilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Validates schema, scheduler heartbeat and observability pipeline readiness. */
class ObservabilityDoctor extends Command
{
    protected $signature='workintel:observability-doctor {--json}';
    protected $description='Validate WorkIntel observability, alerting and operational telemetry readiness.';

    /** Run non-destructive Block L observability readiness checks. */
    public function handle(ObservabilityService $service): int
    {
        $checks=[];$failed=false;
        try{$tables=['system_observability_events','system_observability_heartbeats','system_observability_alert_rules','system_observability_alerts'];$missing=array_values(array_filter($tables,fn($table)=>!Schema::hasTable($table)));$checks['schema']=['ok'=>$missing===[],'detail'=>$missing===[]?'Observability schema present.':'Missing: '.implode(', ',$missing)];if($missing)$failed=true;}catch(Throwable $e){$checks['schema']=['ok'=>false,'detail'=>$e->getMessage()];$failed=true;}
        try{$service->ensureDefaultRules();$rules=Schema::hasTable('system_observability_alert_rules')?\App\Models\SystemObservabilityAlertRule::query()->count():0;$checks['rules']=['ok'=>$rules>=7,'detail'=>"{$rules} observability alert rule(s) available."];if($rules<7)$failed=true;}catch(Throwable $e){$checks['rules']=['ok'=>false,'detail'=>$e->getMessage()];$failed=true;}
        try{$metrics=$service->metrics();$checks['metrics']=['ok'=>isset($metrics['scheduler_age_seconds']),'detail'=>'Metric collection completed.'];}catch(Throwable $e){$checks['metrics']=['ok'=>false,'detail'=>$e->getMessage()];$failed=true;}
        $result=['ok'=>!$failed,'checks'=>$checks];if($this->option('json'))$this->line((string)json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));else foreach($checks as $name=>$check)$this->line(sprintf('[%s] %s — %s',$check['ok']?'PASS':'FAIL',$name,$check['detail']));return $failed?self::FAILURE:self::SUCCESS;
    }
}
