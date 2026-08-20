<?php

use App\Services\RecurringTaskService;
use App\Services\Reporting\ReportScheduleService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('workintel:about', function () {
    $this->info('Workforce Intelligence');
    $this->line('Laravel + React single application');
})->purpose('Display Workforce Intelligence application information');


Artisan::command('workintel:generate-recurring-tasks', function (RecurringTaskService $service) {
    $count = $service->generateDueTasks();
    $this->info("Generated {$count} recurring task instance(s).");
})->purpose('Generate task instances whose recurring schedule is due');

Schedule::command('workintel:generate-recurring-tasks')->everyFifteenMinutes()->withoutOverlapping();

Artisan::command('workintel:prune-screenshots', function () {
    $deleted = 0;
    \App\Models\ScreenshotSetting::query()->with('workspace')->chunkById(100, function ($settingsRows) use (&$deleted) {
        foreach ($settingsRows as $settings) {
            if (! $settings->workspace || ! app(\App\Services\Modules\WorkspaceModuleService::class)->shouldProcessBackground($settings->workspace, 'screenshots')) continue;
            $cutoff = now()->subDays(max(1, (int) $settings->retention_days));
            \App\Models\Screenshot::query()
                ->where('workspace_id', $settings->workspace_id)
                ->whereNull('deleted_at')
                ->where('captured_at', '<', $cutoff)
                ->orderBy('id')
                ->chunkById(config('workintel.screenshots.prune_chunk', 500), function ($rows) use (&$deleted) {
                    foreach ($rows as $screenshot) {
                        try {
                            app(\App\Services\ScreenshotStorage\ScreenshotStorageService::class)->deleteBinary($screenshot->load('storageProvider'));
                            $screenshot->update(['deleted_at' => now()]);
                            $deleted++;
                        } catch (\Throwable $e) {
                            $screenshot->update(['storage_error' => \Illuminate\Support\Str::limit($e->getMessage(), 4000, '')]);
                        }
                    }
                });
        }
    });
    $this->info("Pruned {$deleted} expired screenshot(s).");
})->purpose('Delete screenshot files that exceeded workspace retention policies');

Schedule::command('workintel:prune-screenshots')->dailyAt('02:30')->withoutOverlapping();

Artisan::command('workintel:sync-screenshot-storage {--workspace=} {--limit=100}', function (\App\Services\ScreenshotStorage\ScreenshotStorageService $service) {
    $workspace = $this->option('workspace');
    $result = $service->processDue($workspace ? (int) $workspace : null, (int) $this->option('limit'));
    $this->info(sprintf('Screenshot storage sync: %d processed, %d completed, %d failed.', $result['processed'], $result['completed'], $result['failed']));
})->purpose('Upload queued screenshots to the configured external storage provider');

Schedule::command('workintel:sync-screenshot-storage --limit=250')->everyMinute()->withoutOverlapping();


Artisan::command('workintel:run-scheduled-reports', function (ReportScheduleService $service) {
    $count = $service->runDue();
    $this->info("Generated {$count} scheduled report(s).");
})->purpose('Generate due scheduled reports and their configured exports');

Schedule::command('workintel:run-scheduled-reports')->everyMinute()->withoutOverlapping();

Artisan::command('workintel:prune-report-exports', function () {
    $cutoff = now()->subDays(max(1, (int) config('workintel.reports.retention_days', 90)));
    $deleted = 0;
    \App\Models\ReportExport::query()->where('created_at', '<', $cutoff)->where('status', 'completed')->chunkById(100, function ($exports) use (&$deleted) {
        foreach ($exports as $export) {
            if ($export->path) \Illuminate\Support\Facades\Storage::disk($export->disk)->delete($export->path);
            $export->delete();
            $deleted++;
        }
    });
    $this->info("Pruned {$deleted} expired report export(s).");
})->purpose('Delete generated report files after the configured retention period');

Schedule::command('workintel:prune-report-exports')->dailyAt('03:00')->withoutOverlapping();


Artisan::command('workintel:billing-maintenance', function (SubscriptionService $service) {
    $result = $service->maintenance();
    $this->info(sprintf('Billing maintenance: %d renewed, %d downgraded, %d past due, %d expired.', $result['renewed'], $result['downgraded'], $result['pastDue'], $result['expired']));
})->purpose('Renew due subscriptions, apply scheduled cancellations and flag overdue manual invoices');

Schedule::command('workintel:billing-maintenance')->hourly()->withoutOverlapping();


Artisan::command('workintel:client-invoice-maintenance', function (\App\Services\ClientPortal\ClientInvoiceService $service) {
    $count = $service->markOverdue();
    $this->info("Marked {$count} client invoice(s) overdue.");
})->purpose('Mark sent or partially paid client invoices overdue after their due date');

Schedule::command('workintel:client-invoice-maintenance')->hourly()->withoutOverlapping();

Artisan::command('workintel:generate-client-invoices {--limit=100}', function (\App\Services\ClientPortal\RecurringClientInvoiceService $service) {
    $count = $service->runDue((int) $this->option('limit'));
    $this->info("Generated {$count} recurring client invoice(s).");
})->purpose('Generate due recurring client invoices and optionally send them');
Schedule::command('workintel:generate-client-invoices --limit=250')->everyFifteenMinutes()->withoutOverlapping();

Artisan::command('workintel:reconcile-client-payments {--limit=100}', function (\App\Services\ClientPortal\ClientPaymentGatewayService $service) {
    $count = $service->reconcilePending((int) $this->option('limit'));
    $this->info("Reconciled {$count} client payment checkout(s).");
})->purpose('Reconcile pending hosted client payment checkouts with their providers');
Schedule::command('workintel:reconcile-client-payments --limit=250')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('workintel:prune-client-portal-auth', function () {
    $cutoff = now()->subDays(7);
    $tokens = \App\Models\ClientPortalToken::query()
        ->where(function ($query) {
            $query->whereNotNull('revoked_at')
                ->orWhere('expires_at', '<', now());
        })
        ->where(function ($query) use ($cutoff) {
            $query->where('created_at', '<', $cutoff)
                ->orWhere('revoked_at', '<', $cutoff)
                ->orWhere('expires_at', '<', $cutoff);
        })
        ->delete();

    $invites = \App\Models\ClientPortalInvite::query()
        ->where(function ($query) {
            $query->whereNotNull('accepted_at')
                ->orWhere('expires_at', '<', now());
        })
        ->where('created_at', '<', $cutoff)
        ->delete();

    $this->info("Pruned {$tokens} client portal token(s) and {$invites} invite(s).");
})->purpose('Prune expired/revoked client portal tokens and old activation invites');

Schedule::command('workintel:prune-client-portal-auth')->dailyAt('03:30')->withoutOverlapping();

Schedule::command('workintel:deliver-webhooks')->everyMinute()->withoutOverlapping();
Schedule::command('workintel:send-notification-digests daily')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('workintel:send-notification-digests weekly')->weeklyOn(1, '08:00')->withoutOverlapping();
Schedule::command('workintel:security-maintenance')->dailyAt('04:00')->withoutOverlapping();

Artisan::command('workintel:retention-maintenance {--workspace=}', function (\App\Services\Enterprise\DataRetentionService $service) {
    $result = $service->run($this->option('workspace') ? (int) $this->option('workspace') : null);
    $this->info('Retention maintenance: '.json_encode($result, JSON_UNESCAPED_SLASHES));
})->purpose('Apply Phase 23 workspace data retention policies while respecting legal holds');
Schedule::command('workintel:retention-maintenance')->dailyAt('04:10')->withoutOverlapping();
Schedule::command('workintel:attendance-maintenance')->everyFifteenMinutes()->withoutOverlapping();

Artisan::command('workintel:approval-maintenance', function (\App\Services\Approvals\ApprovalEngine $service) {
    $count = $service->escalateDue();
    $this->info("Escalated {$count} overdue approval step(s).");
})->purpose('Escalate overdue approval steps according to workflow SLA rules');

Schedule::command('workintel:approval-maintenance')->everyFifteenMinutes()->withoutOverlapping();

Artisan::command('workintel:scheduler-heartbeat', function (\App\Services\Observability\ObservabilityService $observability) {
    \Illuminate\Support\Facades\Cache::put('workintel:scheduler-heartbeat', now()->toIso8601String(), now()->addMinutes(10));
    $observability->heartbeat('scheduler', 60, ['host'=>gethostname() ?: 'unknown', 'pid'=>getmypid()]);
    $this->info('Scheduler heartbeat updated.');
})->purpose('Update the scheduler heartbeat used by production readiness checks');

Schedule::command('workintel:scheduler-heartbeat')->everyMinute()->withoutOverlapping();
Schedule::command('workintel:automation-maintenance --limit=100')->everyMinute()->withoutOverlapping();

Artisan::command('workintel:migration-doctor {--json}', function (\App\Services\System\MigrationHealthService $service) {
    $checks = $service->inspect();
    if ($this->option('json')) {
        $this->line(json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return;
    }
    $this->info('WorkIntel Migration Doctor');
    foreach ($checks as $check) {
        $tag = in_array($check['status'], ['present', 'applied'], true) ? '<info>OK</info>' : ($check['status'] === 'pending' ? '<comment>PENDING</comment>' : '<comment>MISSING</comment>');
        $this->line(sprintf('%-12s %-55s %s', strip_tags($tag), $check['name'], $check['detail']));
    }
})->purpose('Inspect migration records and schema landmarks before or after an upgrade');

Artisan::command('workintel:production-check {--json}', function (\App\Services\System\ProductionCheckService $service) {
    $checks = $service->checks();
    if ($this->option('json')) {
        $this->line(json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return;
    }
    $errors = 0;
    foreach ($checks as $check) {
        $label = $check['ok'] ? 'OK' : strtoupper($check['level']);
        if (! $check['ok'] && $check['level'] === 'error') $errors++;
        $this->line(sprintf('[%-7s] %-24s %s', $label, $check['name'], $check['detail']));
    }
    if ($errors) {
        $this->error("Production check found {$errors} blocking issue(s).");
        return 1;
    }
    $this->info('Production check passed (warnings may remain).');
    return 0;
})->purpose('Validate production configuration, runtime directories and release artifacts');

Artisan::command('workintel:intelligence-maintenance {--workspace=}', function (\App\Services\Intelligence\WorkforceIntelligenceService $service) {
    $result = $service->runDue($this->option('workspace') ? (int) $this->option('workspace') : null);
    $this->info('Workforce intelligence maintenance: '.json_encode($result, JSON_UNESCAPED_SLASHES));
})->purpose('Run due Phase 25 explainable workforce intelligence calculations');
Schedule::command('workintel:intelligence-maintenance')->everyFifteenMinutes()->withoutOverlapping();

Artisan::command('workintel:prune-intelligence-snapshots {--workspace=}', function (\App\Services\Intelligence\WorkforceIntelligenceService $service) {
    $count = $service->pruneSnapshots($this->option('workspace') ? (int) $this->option('workspace') : null);
    $this->info("Pruned {$count} expired workforce intelligence snapshot(s).");
})->purpose('Prune Phase 25 metric snapshots while respecting Phase 23 legal hold policies');
Schedule::command('workintel:prune-intelligence-snapshots')->dailyAt('04:20')->withoutOverlapping();

Artisan::command('workintel:platform-maintenance', function () {
    $expired = \App\Models\Workspace::query()->where('workspace_type','sandbox')->where('status','active')->whereNotNull('sandbox_expires_at')->where('sandbox_expires_at','<=',now())->update(['status'=>'archived']);
    $this->info("Archived {$expired} expired sandbox workspace(s).");
})->purpose('Archive expired Phase 26 sandbox workspaces');
Schedule::command('workintel:platform-maintenance')->hourly()->withoutOverlapping();

Artisan::command('workintel:prune-media-upload-sessions {--limit=500}', function (\App\Services\Media\MediaLibraryService $service) {
    $count=$service->pruneUploadSessions(max(1,(int)$this->option('limit')));$this->info("Expired {$count} abandoned media upload session(s).");
})->purpose('Remove expired resumable Media Library upload chunks and old terminal session rows');

Schedule::command('workintel:prune-media-upload-sessions --limit=1000')->hourly()->withoutOverlapping();

Artisan::command('workintel:prune-identity-tokens', function () {
    $now = now();
    $verify = \App\Models\EmailVerificationToken::query()->where(function ($q) use ($now) { $q->whereNotNull('used_at')->orWhere('expires_at', '<', $now); })->delete();
    $invites = \App\Models\WorkspaceInvitation::query()->where(function ($q) use ($now) { $q->whereNotNull('accepted_at')->orWhere('expires_at', '<', $now); })->where('created_at', '<', now()->subDays(7))->delete();
    $this->info("Pruned {$verify} verification token(s) and {$invites} old invitation(s).");
})->purpose('Prune expired/consumed P1 identity tokens and invitations');
Schedule::command('workintel:prune-identity-tokens')->dailyAt('04:30')->withoutOverlapping();

Schedule::command('workintel:commerce-maintenance')->hourly()->withoutOverlapping();


Artisan::command('workintel:chat-enterprise-maintenance {--workspace=}', function (\App\Services\Chat\ChatEnterpriseMaintenanceService $service) {
    $workspaceId = $this->option('workspace') ? (int) $this->option('workspace') : null;
    $this->line(json_encode($service->run($workspaceId), JSON_PRETTY_PRINT));
})->purpose('Expire external chat access, enforce retention and remove expired eDiscovery files');
Schedule::command('workintel:chat-enterprise-maintenance')->hourly()->withoutOverlapping();

// Block K — Production Operations & Disaster Recovery.
Schedule::command('workintel:backup-if-due')->everyThirtyMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('workintel:operations-prune-backups')->dailyAt('05:10')->withoutOverlapping()->onOneServer();

Artisan::command('workintel:operations-prune-backups', function (\App\Services\Operations\SystemOperationsService $service) {
    $result = $service->prune();
    $this->info(sprintf('Backup retention: %d pruned, %d failed, %d verified copies protected.', $result['pruned'], $result['failed'], $result['protected_verified']));
})->purpose('Apply platform backup retention while preserving verified restore points');

// Block L — Observability & Audit Operations.
Artisan::command('workintel:observability-evaluate', function (\App\Services\Observability\ObservabilityService $service) {
    $this->line((string)json_encode($service->evaluateAlerts(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
})->purpose('Evaluate platform observability alert thresholds');
Schedule::command('workintel:observability-evaluate')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

Artisan::command('workintel:observability-prune', function (\App\Services\Observability\ObservabilityService $service, \App\Services\Observability\DiagnosticsBundleService $diagnostics) {
    $result=$service->prune();$result['diagnostics']=$diagnostics->prune((int)config('workintel.observability.diagnostics_retention_hours',24));
    $this->line((string)json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
})->purpose('Prune expired resolved observability records and diagnostics bundles');
Schedule::command('workintel:observability-prune')->dailyAt('05:30')->withoutOverlapping()->onOneServer();

// Block M reuses the existing daily workintel:security-maintenance schedule above.

Artisan::command('workintel:process-document-batches {--limit=5} {--sources=25}', function (\App\Services\Documents\DocumentStudioV6Service $service) {
    $result = $service->processQueuedBatches((int) $this->option('limit'), (int) $this->option('sources'));
    $this->info(sprintf('Document batches: %d job(s), %d source(s), %d generated, %d failed.', $result['jobs'], $result['sources'], $result['generated'], $result['failed']));
})->purpose('Process persistent Document Studio large-batch generation jobs');

Schedule::command('workintel:process-document-batches --limit=5 --sources=25')->everyMinute()->withoutOverlapping();
