<?php
namespace App\Console\Commands;

use App\Models\SubscriptionPlan;
use App\Support\PermissionCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Provides platform doctor behavior within the WorkIntel application. */ class PlatformDoctor extends Command
{
    protected $signature = 'workintel:platform-doctor {--json}';
    protected $description = 'Validate Phase 26 commercial platform schema, permissions and plan entitlements.';

    /** Executes the command, job, or request handler. */ public function handle(): int
    {
        $tables = [
            'workspace_brandings', 'workspace_domains', 'partner_accounts', 'partner_account_members',
            'partner_workspaces', 'partner_api_keys', 'platform_addons', 'workspace_addons',
            'addon_usage_events', 'industry_templates', 'industry_template_installations',
            'data_import_jobs', 'data_import_items',
        ];

        $checks = [];
        foreach ($tables as $table) {
            $checks[] = ['name' => 'table '.$table, 'ok' => Schema::hasTable($table)];
        }

        foreach (['workspace_type', 'parent_workspace_id', 'sandbox_expires_at'] as $column) {
            $checks[] = [
                'name' => 'workspaces.'.$column,
                'ok' => Schema::hasTable('workspaces') && Schema::hasColumn('workspaces', $column),
            ];
        }

        $permissionTableReady = Schema::hasTable('permissions');
        foreach (collect(PermissionCatalog::ITEMS)->filter(fn ($item) => $item[0] === 'Platform')->pluck(1) as $slug) {
            $checks[] = [
                'name' => 'permission '.$slug,
                'ok' => $permissionTableReady && \App\Models\Permission::query()->where('slug', $slug)->exists(),
            ];
        }

        $billingReady = Schema::hasTable('subscription_plans') && Schema::hasTable('plan_entitlements');
        foreach (['free', 'silver', 'gold', 'platinum'] as $planSlug) {
            $plan = $billingReady
                ? SubscriptionPlan::query()->with('entitlements')->where('slug', $planSlug)->first()
                : null;

            $checks[] = [
                'name' => 'plan '.$planSlug.' phase26 entitlements',
                'ok' => (bool) ($plan
                    && $plan->entitlements->contains('key', 'feature.addon_marketplace')
                    && $plan->entitlements->contains('key', 'limit.sandbox_workspaces')),
            ];
        }

        $ok = collect($checks)->every('ok');

        if ($this->option('json')) {
            $this->line(json_encode(['ok' => $ok, 'checks' => $checks], JSON_PRETTY_PRINT));
        } else {
            foreach ($checks as $check) {
                $this->line(($check['ok'] ? '<info>OK</info>' : '<error>MISSING</error>').' '.$check['name']);
            }
        }

        $ok
            ? $this->info('Phase 26 platform doctor passed.')
            : $this->error('Phase 26 platform doctor found blocking issues.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
