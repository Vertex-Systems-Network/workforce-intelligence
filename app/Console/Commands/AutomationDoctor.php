<?php
namespace App\Console\Commands;
use App\Services\Automation\ConnectorRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Provides automation doctor behavior within the WorkIntel application. */ class AutomationDoctor extends Command
{
    protected $signature='workintel:automation-doctor {--json}';protected $description='Validate Phase 24 automation schema, permissions, entitlements and connector registry.';
    /** Executes the command, job, or request handler. */ public function handle(ConnectorRegistry $registry): int
    {
        $tables=['automation_workflows','automation_actions','automation_events','automation_runs','automation_run_steps','automation_incoming_hooks','automation_dead_letters','integration_connections'];$checks=[];$ok=true;
        foreach($tables as $table){$present=Schema::hasTable($table);$checks[]=['check'=>'table.'.$table,'ok'=>$present,'detail'=>$present?'present':'missing'];$ok=$ok&&$present;}
        foreach(['automations.view','automations.manage','automations.runs.view'] as $slug){$present=Schema::hasTable('permissions')&&DB::table('permissions')->where('slug',$slug)->exists();$checks[]=['check'=>'permission.'.$slug,'ok'=>$present,'detail'=>$present?'present':'missing'];$ok=$ok&&$present;}
        foreach(['gold','platinum'] as $plan){$planId=Schema::hasTable('subscription_plans')?DB::table('subscription_plans')->where('slug',$plan)->value('id'):null;$present=$planId&&DB::table('plan_entitlements')->where('subscription_plan_id',$planId)->where('key','feature.automations')->exists();$checks[]=['check'=>'entitlement.'.$plan,'ok'=>(bool)$present,'detail'=>$present?'present':'missing'];$ok=$ok&&(bool)$present;}
        $providers=$registry->providerIds();$checks[]=['check'=>'connectors','ok'=>count($providers)>=13,'detail'=>implode(', ',$providers)];$ok=$ok&&count($providers)>=13;
        if($this->option('json')){$this->line(json_encode(['ok'=>$ok,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));return $ok?self::SUCCESS:self::FAILURE;}
        foreach($checks as $c)$this->line(sprintf('[%s] %-44s %s',$c['ok']?'OK':'FAIL',$c['check'],$c['detail']));return $ok?self::SUCCESS:self::FAILURE;
    }
}
