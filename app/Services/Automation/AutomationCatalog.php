<?php
namespace App\Services\Automation;
/** Provides automation catalog behavior within the WorkIntel application. */ final class AutomationCatalog
{
    public const CONDITION_OPERATORS=['eq','neq','gt','gte','lt','lte','in','not_in','contains','exists','not_exists','truthy','falsy'];
    /** Handles the trigger events operation for the current WorkIntel workflow. */ public static function triggerEvents(): array
    {
        return [
            'time.started','time.paused','time.resumed','time.stopped','attendance.clocked_in','attendance.clocked_out',
            'payroll.approved','payroll.paid','report.generated','documents.generated','documents.review_requested','documents.approved','documents.shared','documents.signed','device.revoked','client_invoice.sent','client_invoice.payment_recorded','website.page_published','website.lead_received',
            'tasks.created','tasks.updated','tasks.deleted','projects.created','projects.updated','projects.deleted','leave.created','leave.updated',
            'approvals.created','approvals.updated','expense_claims.created','expense_claims.updated','purchase_requests.created','purchase_requests.updated',
            'work_orders.created','work_orders.updated','field.checkpoint_visited','incidents.created','incidents.updated','security_events.created',
            'intelligence.insight_created','intelligence.insight_resolved','intelligence.run_completed','workspace.activity','*',
        ];
    }
    /** Handles the templates operation for the current WorkIntel workflow. */ public static function templates(): array
    {
        return [
            ['key'=>'attendance_to_slack','name'=>'Attendance alert → Slack','description'=>'Send an attendance event to Slack.','trigger_event'=>'attendance.clocked_in','conditions'=>[],'action'=>['action_type'=>'connector','action_key'=>'message.send','config'=>['text'=>'Attendance clock-in · member {{payload.actor.member_id}}']]],
            ['key'=>'critical_incident_to_jira','name'=>'Critical incident → Jira','description'=>'Create a Jira issue when a critical field incident is recorded.','trigger_event'=>'incidents.created','conditions'=>[['field'=>'payload.severity','operator'=>'eq','value'=>'critical']],'action'=>['action_type'=>'connector','action_key'=>'issue.create','config'=>['summary'=>'Critical WorkIntel incident','description'=>'{{payload.title}} · {{payload.description}}','issue_type'=>'Task']]],
            ['key'=>'expense_approval_notification','name'=>'Approved expense → Finance notification','description'=>'Notify finance/admins when an expense approval reaches approved state.','trigger_event'=>'approvals.updated','conditions'=>[['field'=>'payload.approval.subject_type','operator'=>'eq','value'=>'expense_claim'],['field'=>'payload.approval.status','operator'=>'eq','value'=>'approved']],'action'=>['action_type'=>'notification','action_key'=>'notify','config'=>['role_slugs'=>['owner','admin','payroll-manager'],'title'=>'Expense approved','body'=>'{{payload.approval.title}} · {{payload.approval.currency}} {{payload.approval.amount}}','severity'=>'success']]],
            ['key'=>'intelligence_danger_notification','name'=>'High-risk intelligence signal → Admin notification','description'=>'Notify workspace admins when Phase 25 creates a danger or critical explainable signal.','trigger_event'=>'intelligence.insight_created','conditions'=>[['field'=>'payload.insight.severity','operator'=>'in','value'=>['danger','critical']]],'action'=>['action_type'=>'notification','action_key'=>'notify','config'=>['role_slugs'=>['owner','admin'],'title'=>'Workforce intelligence alert','body'=>'{{payload.insight.title}} · {{payload.insight.summary}}','severity'=>'warning']]],
            ['key'=>'report_notification','name'=>'Scheduled report → Notification','description'=>'Notify workspace admins when a report is generated.','trigger_event'=>'report.generated','conditions'=>[],'action'=>['action_type'=>'notification','action_key'=>'notify','config'=>['role_slugs'=>['owner','admin'],'title'=>'Report generated','body'=>'Report run {{payload.report_run_id}} is ready.','severity'=>'info']]],
        ];
    }
}
