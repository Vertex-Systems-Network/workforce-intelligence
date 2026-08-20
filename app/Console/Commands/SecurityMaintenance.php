<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\WebhookDelivery;
use App\Models\WorkspaceNotification;
use App\Models\EnterpriseSsoState;
use App\Models\ScimAccessToken;
use App\Models\MobileAccessToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Provides security maintenance behavior within the WorkIntel application. */ class SecurityMaintenance extends Command
{
    protected $signature = 'workintel:security-maintenance';
    protected $description = 'Prune security, audit, webhook and read-notification history when those subsystems are installed';

    /** Executes the command, job, or request handler. */ public function handle(): int
    {
        $ssoStates = Schema::hasTable('enterprise_sso_states')
            ? EnterpriseSsoState::where('expires_at', '<', now())->delete()
            : 0;
        $scimTokens = Schema::hasTable('scim_access_tokens')
            ? ScimAccessToken::where(function ($q) { $q->whereNotNull('revoked_at')->orWhere('expires_at', '<', now()); })->where('created_at', '<', now()->subDays(30))->delete()
            : 0;
        $mobileTokens = Schema::hasTable('mobile_access_tokens')
            ? MobileAccessToken::where(function ($q) { $q->whereNotNull('revoked_at')->orWhere('expires_at', '<', now()); })->where('created_at', '<', now()->subDays(30))->delete()
            : 0;
        $this->info("Pruned auth artifacts: oidc_states={$ssoStates}, scim_tokens={$scimTokens}, mobile_tokens={$mobileTokens}.");

        if (Schema::hasTable('data_governance_policies')) {
            $this->info('Phase 23 data governance is installed; historical retention pruning is delegated to workintel:retention-maintenance so legal holds and workspace policies are respected.');
            return self::SUCCESS;
        }
        $audit = Schema::hasTable('audit_logs')
            ? AuditLog::where('created_at', '<', now()->subDays(config('workintel_security.audit.retention_days', 365)))->delete()
            : 0;
        $webhooks = Schema::hasTable('webhook_deliveries')
            ? WebhookDelivery::where('created_at', '<', now()->subDays(config('workintel_security.webhooks.retention_days', 90)))->delete()
            : 0;
        $notifications = Schema::hasTable('workspace_notifications')
            ? WorkspaceNotification::where('created_at', '<', now()->subDays(180))->whereNotNull('read_at')->delete()
            : 0;
        $security = Schema::hasTable('security_events')
            ? SecurityEvent::where('created_at', '<', now()->subYears(2))->whereNotNull('resolved_at')->delete()
            : 0;

        $this->info("Pruned audit={$audit}, webhook={$webhooks}, notifications={$notifications}, security={$security}.");
        return self::SUCCESS;
    }
}
