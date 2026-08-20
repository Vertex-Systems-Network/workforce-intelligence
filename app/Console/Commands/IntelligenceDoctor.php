<?php

namespace App\Console\Commands;

use App\Services\Intelligence\IntelligenceRuleCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Provides intelligence doctor behavior within the WorkIntel application. */ class IntelligenceDoctor extends Command
{
    protected $signature = 'workintel:intelligence-doctor {--json}';
    protected $description = 'Validate Phase 25 Workforce Intelligence schema, permissions, rules and plan entitlements.';

    /** Executes the command, job, or request handler. */ public function handle(IntelligenceRuleCatalog $catalog): int
    {
        $checks = [];
        $ok = true;
        foreach (['intelligence_settings','intelligence_rules','intelligence_runs','intelligence_insights','intelligence_snapshots'] as $table) {
            $present = Schema::hasTable($table);
            $checks[] = ['check'=>'table.'.$table,'ok'=>$present,'detail'=>$present?'present':'missing'];
            $ok = $ok && $present;
        }
        foreach (['intelligence.view_own','intelligence.view_team','intelligence.view_all','intelligence.manage','intelligence.rules_manage'] as $slug) {
            $present = Schema::hasTable('permissions') && DB::table('permissions')->where('slug',$slug)->exists();
            $checks[] = ['check'=>'permission.'.$slug,'ok'=>$present,'detail'=>$present?'present':'missing'];
            $ok = $ok && $present;
        }
        foreach (['gold','platinum'] as $plan) {
            $planId = Schema::hasTable('subscription_plans') ? DB::table('subscription_plans')->where('slug',$plan)->value('id') : null;
            $raw = $planId ? DB::table('plan_entitlements')->where('subscription_plan_id',$planId)->where('key','feature.workforce_intelligence')->value('value') : null;
            $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);
            $present = (bool) data_get($decoded, 'value', false);
            $checks[] = ['check'=>'entitlement.'.$plan,'ok'=>(bool)$present,'detail'=>$present?'enabled':'missing/disabled'];
            $ok = $ok && (bool)$present;
        }
        $ruleCount = count(IntelligenceRuleCatalog::DEFINITIONS);
        $checks[] = ['check'=>'rule_catalog','ok'=>$ruleCount >= 12,'detail'=>$ruleCount.' default explainable rules'];
        $ok = $ok && $ruleCount >= 12;

        if ($this->option('json')) {
            $this->line(json_encode(['ok'=>$ok,'checks'=>$checks], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
            return $ok ? self::SUCCESS : self::FAILURE;
        }
        foreach ($checks as $check) $this->line(sprintf('[%s] %-48s %s', $check['ok']?'OK':'FAIL', $check['check'], $check['detail']));
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
