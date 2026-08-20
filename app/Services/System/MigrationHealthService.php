<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Provides migration health service behavior within the WorkIntel application. */ class MigrationHealthService
{
    /** @return array<int,array{type:string,name:string,status:string,detail:string}> */
    /** Handles the inspect operation for the current WorkIntel workflow. */ public function inspect(): array
    {
        $checks = [];

        foreach ([
            'users' => ['use_workspace_locale'],
            'projects' => ['completed_at', 'client_visible'],
            'tasks' => ['recurrence_template_id', 'client_visible', 'task_status_id', 'owner_member_id', 'description_html', 'start_at', 'position'],
            'workspace_members' => ['job_title_id', 'legal_entity_id', 'business_unit_id', 'collaboration_type', 'external_company', 'external_expires_at', 'external_scope'],
            'workspace_invitations' => ['collaboration_type', 'external_company', 'external_expires_at', 'chat_conversation_id'],
            'chat_conversations' => ['external_access', 'retention_days', 'legal_hold', 'export_policy', 'dlp_mode'],
            'chat_message_attachments' => ['security_status', 'security_reason'],
            'clients' => ['billing_email', 'billing_address', 'tax_id'],
            'agent_enrollments' => ['browser_used_at'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $checks[] = ['type' => 'table', 'name' => $table, 'status' => 'missing', 'detail' => 'Base table is missing.'];
                continue;
            }
            foreach ($columns as $column) {
                $checks[] = [
                    'type' => 'column',
                    'name' => $table.'.'.$column,
                    'status' => Schema::hasColumn($table, $column) ? 'present' : 'missing',
                    'detail' => Schema::hasColumn($table, $column) ? 'Schema column exists.' : 'Column will be created by its owning migration if that migration is pending.',
                ];
            }
        }

        if (Schema::hasTable('permissions')) {
            $checks[] = [
                'type' => 'schema',
                'name' => 'permissions.timestamps',
                'status' => 'present',
                'detail' => (Schema::hasColumn('permissions', 'created_at') || Schema::hasColumn('permissions', 'updated_at'))
                    ? 'Permission migration compatibility: timestamp columns are present; M13 insert works with or without them.'
                    : 'Permission migration compatibility: base permissions table has no timestamps; M13 insert correctly omits them.',
            ];
        }

        if (Schema::hasTable('compensation_profiles')) {
            foreach ([
                ['workspace_id', 'member_id', 'effective_from'],
                ['workspace_id', 'status'],
            ] as $columns) {
                $present = Schema::hasIndex('compensation_profiles', $columns);
                $checks[] = [
                    'type' => 'index',
                    'name' => 'compensation_profiles.'.implode('+', $columns),
                    'status' => $present ? 'present' : 'missing',
                    'detail' => $present
                        ? 'Required compensation lookup index exists.'
                        : 'Missing index will be repaired when the pending payroll migration runs.',
                ];
            }
        }

        foreach ([
            'compensation_profiles', 'payroll_runs', 'saved_reports', 'workspace_subscriptions',
            'client_portal_accounts', 'client_invoices', 'notification_preferences', 'workspace_notifications', 'audit_logs', 'security_events',
            'integration_connections', 'workspace_api_keys', 'webhook_endpoints', 'webhook_deliveries',
            'attendance_policies', 'attendance_locations', 'attendance_events', 'attendance_correction_requests',
            'approval_workflows', 'approval_workflow_steps', 'approval_delegations', 'approval_requests', 'approval_request_steps', 'approval_decisions',
            'employee_document_folders', 'employee_documents', 'employment_contracts', 'employee_emergency_contacts', 'employee_dependents',
            'employee_custom_fields', 'employee_custom_values', 'employee_lifecycle_checklists', 'company_assets', 'asset_assignments', 'company_policies', 'policy_acknowledgements', 'employment_history',
            'performance_goals', 'performance_review_cycles', 'performance_reviews', 'one_on_ones', 'skill_catalog', 'member_skills', 'training_courses', 'training_enrollments', 'development_plans', 'recognitions', 'pulse_surveys', 'compensation_review_cycles',
            'cost_centers', 'expense_policies', 'expense_claims', 'expense_claim_items', 'expense_reimbursements', 'purchase_requests', 'job_budgets', 'project_cost_allocations', 'job_cost_snapshots',
            'payroll_compliance_packs', 'payroll_compliance_rules', 'member_payroll_assignments', 'member_benefits', 'payroll_item_compliance_lines', 'payroll_run_members', 'contractor_payment_profiles', 'retro_pay_adjustments', 'termination_settlements', 'payroll_exports',
            'mobile_access_tokens', 'field_work_orders', 'field_work_order_assignees', 'field_work_order_events', 'field_files', 'field_checkpoints', 'field_checkpoint_visits', 'field_form_templates', 'field_form_fields', 'field_form_submissions', 'field_form_answers', 'safety_incidents', 'mobile_sync_events',
            'enterprise_identity_providers', 'enterprise_sso_states', 'scim_access_tokens', 'user_mfa_methods', 'workspace_security_policies', 'workspace_ip_rules', 'workspace_access_policies', 'workspace_access_sessions', 'legal_entities', 'business_units', 'data_governance_policies','data_retention_tombstones', 'data_retention_runs',
            'automation_workflows', 'automation_actions', 'automation_events', 'automation_runs', 'automation_run_steps', 'automation_incoming_hooks', 'automation_dead_letters',
            'intelligence_settings', 'intelligence_rules', 'intelligence_runs', 'intelligence_insights', 'intelligence_snapshots',
            'workspace_brandings', 'workspace_domains', 'partner_accounts', 'partner_account_members', 'partner_workspaces', 'partner_api_keys', 'platform_addons', 'workspace_addons', 'addon_usage_events', 'industry_templates', 'industry_template_installations', 'data_import_jobs', 'data_import_items',
            'role_permission_denies', 'role_data_scopes', 'role_module_access',
            'workspace_registration_settings', 'workspace_invitations', 'email_verification_tokens', 'workspace_preferences',
            'workspace_modules', 'workspace_module_events',
            'task_statuses', 'task_status_transitions', 'task_tags', 'task_tag_assignments', 'task_observers', 'task_checklist_items', 'task_relations', 'task_activities','document_templates','document_template_versions','generated_documents','screenshot_storage_providers','screenshot_storage_jobs','installation_guide_progress','chat_conversations','chat_conversation_members','chat_messages','chat_message_attachments','chat_message_reactions','chat_message_pins','chat_presence','chat_message_edit_history','chat_saved_messages','chat_drafts','chat_polls','chat_poll_options','chat_poll_votes','chat_thread_follows','chat_legal_holds','chat_moderation_events','chat_export_jobs','chat_dlp_policies','chat_dlp_events','commerce_provider_configs','commerce_coupons','commerce_tax_rules','commerce_checkout_sessions','commerce_coupon_redemptions','commerce_refunds','commerce_webhook_events','commerce_dunning_attempts','user_page_preferences','website_sites','website_pages','website_page_versions','website_reusable_sections','website_forms','website_form_submissions',
        ] as $table) {
            $checks[] = [
                'type' => 'table', 'name' => $table,
                'status' => Schema::hasTable($table) ? 'present' : 'missing',
                'detail' => Schema::hasTable($table) ? 'Table exists.' : 'Table is expected after all migrations are applied.',
            ];
        }

        if (Schema::hasTable('migrations')) {
            $applied = DB::table('migrations')->pluck('migration')->all();
            foreach (glob(database_path('migrations/*.php')) ?: [] as $file) {
                $migration = pathinfo($file, PATHINFO_FILENAME);
                $checks[] = [
                    'type' => 'migration', 'name' => $migration,
                    'status' => in_array($migration, $applied, true) ? 'applied' : 'pending',
                    'detail' => in_array($migration, $applied, true) ? 'Recorded in migrations table.' : 'Will run on php artisan migrate.',
                ];
            }
        }

        return $checks;
    }
}
