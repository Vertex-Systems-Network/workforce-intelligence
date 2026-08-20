<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Provides production check service behavior within the WorkIntel application. */ class ProductionCheckService
{
    /** Handles the checks operation for the current WorkIntel workflow. */ public function checks(): array
    {
        $checks = [];
        $add = function (string $name, bool $ok, string $detail, string $level = 'error') use (&$checks) {
            $checks[] = compact('name', 'ok', 'detail', 'level');
        };

        $production = app()->environment('production');
        $add('APP_ENV', $production, 'APP_ENV should be production for a production deployment.', 'warning');
        $add('APP_DEBUG', ! config('app.debug'), 'APP_DEBUG must be false in production.');
        $add('APP_KEY', filled(config('app.key')), 'APP_KEY must be configured.');
        $appUrl = (string) config('app.url');
        $add('APP_URL', filter_var($appUrl, FILTER_VALIDATE_URL) !== false, 'APP_URL must be a valid public URL.');
        $add('HTTPS', str_starts_with($appUrl, 'https://'), 'HTTPS is strongly recommended for production.', 'warning');
        $add('QUEUE_CONNECTION', config('queue.default') !== 'sync', 'Use database/redis/sqs instead of sync for production background work.', 'warning');
        $add('CACHE_STORE', config('cache.default') !== 'array', 'Use a persistent cache store in production.', 'warning');
        $add('SESSION_DRIVER', config('session.driver') !== 'array', 'Use a persistent session driver.', 'warning');
        $add('MAIL_MAILER', config('mail.default') !== 'log', 'Configure a real mail transport if email notifications are enabled.', 'warning');
        $add('storage writable', is_writable(storage_path()), 'storage/ must be writable by the PHP process.');
        $add('bootstrap/cache writable', is_writable(base_path('bootstrap/cache')), 'bootstrap/cache must be writable by the PHP process.');
        $add('release manifest', is_file(storage_path('app/releases/manifest.json')), 'Release manifest must be generated for Downloads.');
        try {
            $pending = [];
            if (! Schema::hasTable('migrations')) $pending[] = 'migrations table';
            else { $applied=DB::table('migrations')->pluck('migration')->all(); foreach (glob(database_path('migrations/*.php')) ?: [] as $file) { $name=pathinfo($file,PATHINFO_FILENAME); if (! in_array($name,$applied,true)) $pending[]=$name; } }
            $missing = array_values(array_filter(['security_events','audit_logs','attendance_policies','attendance_events','attendance_correction_requests','approval_workflows','approval_requests','approval_request_steps','approval_decisions','employee_documents','employment_contracts','company_assets','performance_goals','performance_reviews','training_enrollments','expense_claims','purchase_requests','job_budgets','payroll_compliance_packs','payroll_item_compliance_lines','mobile_access_tokens','field_work_orders','field_checkpoints','safety_incidents','enterprise_identity_providers','workspace_security_policies','workspace_access_policies','scim_access_tokens','legal_entities','data_governance_policies','data_retention_tombstones','automation_workflows','automation_runs','automation_incoming_hooks','intelligence_settings','intelligence_rules','intelligence_runs','intelligence_insights','intelligence_snapshots','workspace_brandings','workspace_domains','partner_accounts','partner_account_members','partner_workspaces','partner_api_keys','platform_addons','workspace_addons','addon_usage_events','industry_templates','industry_template_installations','data_import_jobs','data_import_items','workspace_preferences','document_templates','document_template_versions','generated_documents','screenshot_storage_providers','screenshot_storage_jobs','installation_guide_progress','chat_conversations','chat_conversation_members','chat_messages','chat_message_attachments','chat_message_reactions','chat_message_pins','chat_presence','chat_message_edit_history','chat_saved_messages','chat_drafts','chat_polls','chat_poll_options','chat_poll_votes','chat_thread_follows','commerce_provider_configs','commerce_coupons','commerce_tax_rules','commerce_checkout_sessions','commerce_coupon_redemptions','commerce_refunds','commerce_webhook_events','commerce_dunning_attempts','user_page_preferences','website_sites','website_pages','website_page_versions','website_reusable_sections','website_forms','website_form_submissions'], fn($table)=>!Schema::hasTable($table)));
            $add('database schema', ! $pending && ! $missing, ! $pending && ! $missing ? 'Migrations and operational schema are current.' : 'Pending: '.implode(', ',$pending).'; missing tables: '.implode(', ',$missing));
        } catch (\Throwable $e) { $add('database schema', false, $e->getMessage()); }

        return $checks;
    }
}
