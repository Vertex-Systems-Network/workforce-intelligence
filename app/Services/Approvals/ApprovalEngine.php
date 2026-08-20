<?php

namespace App\Services\Approvals;

use App\Models\ApprovalDecision;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalRequest as ApprovalItem;
use App\Models\ApprovalRequestStep;
use App\Models\ApprovalWorkflow;
use App\Models\CompensationReviewItem;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\ExpenseClaim;
use App\Models\PayrollAction;
use App\Models\PayrollRun;
use App\Models\ProjectExpense;
use App\Models\PurchaseRequest;
use App\Models\ShiftAssignment;
use App\Models\ShiftSwapRequest;
use App\Models\TimeEntry;
use App\Models\TimesheetPeriod;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\LeaveRequest;
use App\Models\Role;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\AttendancePolicyService;
use App\Services\LeaveBalanceService;
use App\Services\Notifications\WorkspaceNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Provides approval engine behavior within the WorkIntel application. */ class ApprovalEngine
{
    public const SUBJECTS = [
        'leave_request' => LeaveRequest::class,
        'timesheet_period' => TimesheetPeriod::class,
        'project_expense' => ProjectExpense::class,
        'payroll_run' => PayrollRun::class,
        'shift_swap_request' => ShiftSwapRequest::class,
        'attendance_correction' => AttendanceCorrectionRequest::class,
        'expense_claim' => ExpenseClaim::class,
        'purchase_request' => PurchaseRequest::class,
        'compensation_review_item' => CompensationReviewItem::class,
    ];

    /** Initializes the class with its required dependencies and state. */ public function __construct(
        private readonly LeaveBalanceService $leaveBalances,
        private readonly AttendanceCalculator $attendanceCalculator,
        private readonly AttendancePolicyService $attendancePolicies,
        private readonly WorkspaceNotificationService $notifications,
    ) {}

    /** Handles the installed operation for the current WorkIntel workflow. */ public function installed(): bool
    {
        return Schema::hasTable('approval_workflows') && Schema::hasTable('approval_requests') && Schema::hasTable('approval_request_steps');
    }

    /** Handles the ensure default workflows operation for the current WorkIntel workflow. */ public function ensureDefaultWorkflows(Workspace $workspace, ?int $createdBy = null): void
    {
        if (! $this->installed()) return;
        $defaults = [
            ['leave-default', 'Leave requests', 'leave.submitted', 20, 'admin', [
                ['Manager review', 'manager', null], ['HR review', 'role', 'hr'],
            ]],
            ['timesheet-default', 'Timesheet approval', 'timesheet.submitted', 24, 'admin', [
                ['Manager review', 'manager', null],
            ]],
            ['expense-default', 'Project expense approval', 'project_expense.submitted', 24, 'owner', [
                ['Administrator review', 'role', 'admin'],
            ]],
            ['payroll-default', 'Payroll approval', 'payroll.submitted', 12, 'owner', [
                ['Administrator review', 'role', 'admin'],
            ]],
            ['schedule-default', 'Schedule change approval', 'schedule_change.submitted', 12, 'admin', [
                ['Manager review', 'manager', null],
            ]],
            ['attendance-correction-default', 'Attendance correction approval', 'attendance_correction.submitted', 12, 'admin', [
                ['Manager review', 'manager', null],
            ]],
            ['expense-claim-default', 'Expense claim approval', 'expense_claim.submitted', 24, 'admin', [
                ['Manager review', 'manager', null], ['Finance/Admin review', 'role', 'admin'],
            ]],
            ['purchase-request-default', 'Purchase request approval', 'purchase_request.submitted', 24, 'admin', [
                ['Manager review', 'manager', null], ['Administrator review', 'role', 'admin'],
            ]],
            ['comp-review-default', 'Compensation proposal approval', 'compensation_review.submitted', 24, 'owner', [
                ['Administrator review', 'role', 'admin'],
            ]],
            ['chat-approval-default', 'Chat approval request', 'chat.approval.submitted', 24, 'admin', [
                ['Manager review', 'manager', null],
            ]],
        ];
        foreach ($defaults as [$systemKey, $name, $trigger, $sla, $escalationRole, $steps]) {
            $workflow = ApprovalWorkflow::query()->firstOrCreate(
                ['workspace_id' => $workspace->id, 'system_key' => $systemKey],
                [
                    'uuid' => (string) Str::uuid(), 'name' => $name, 'trigger_key' => $trigger,
                    'description' => 'Default WorkIntel workflow. You can edit steps, SLA and conditions.',
                    'status' => 'active', 'priority' => 100, 'conditions' => [], 'sla_hours' => $sla,
                    'escalation_role_slug' => $escalationRole, 'notify_requester' => true, 'created_by' => $createdBy,
                ]
            );
            if ($workflow->steps()->count() === 0) {
                foreach ($steps as $index => [$stepName, $type, $roleSlug]) {
                    $workflow->steps()->create([
                        'position' => $index + 1, 'name' => $stepName, 'approver_type' => $type,
                        'approver_role_slug' => $roleSlug, 'required_approvals' => 1, 'allow_self_approval' => false,
                    ]);
                }
            }
        }
    }

    /** Handles the submit for operation for the current WorkIntel workflow. */ public function submitFor(
        Workspace $workspace,
        WorkspaceMember $requester,
        string $triggerKey,
        string $subjectType,
        Model $subject,
        array $context,
        string $title,
        ?string $summary = null,
        ?float $amount = null,
        ?string $currency = null,
    ): ?ApprovalItem {
        if (! $this->installed()) return null;
        $this->ensureDefaultWorkflows($workspace, $requester->user_id);

        $existing = ApprovalItem::query()->where('workspace_id', $workspace->id)
            ->where('trigger_key', $triggerKey)->where('subject_type', $subjectType)->where('subject_id', $subject->getKey())
            ->where('status', 'pending')->first();
        if ($existing) return $existing->load(['steps', 'workflow']);

        $workflow = ApprovalWorkflow::query()->with('steps')->where('workspace_id', $workspace->id)
            ->where('trigger_key', $triggerKey)->where('status', 'active')->orderBy('priority')->orderBy('id')->get()
            ->first(fn (ApprovalWorkflow $candidate) => $this->matchesConditions($candidate->conditions ?? [], $context + ['amount' => $amount, 'currency' => $currency]));

        if (! $workflow || $workflow->steps->isEmpty()) return null;

        return DB::transaction(function () use ($workspace, $requester, $triggerKey, $subjectType, $subject, $context, $title, $summary, $amount, $currency, $workflow) {
            $submittedAt = now();
            $request = ApprovalItem::create([
                'uuid' => (string) Str::uuid(), 'workspace_id' => $workspace->id, 'approval_workflow_id' => $workflow->id,
                'trigger_key' => $triggerKey, 'subject_type' => $subjectType, 'subject_id' => $subject->getKey(), 'requester_member_id' => $requester->id,
                'title' => $title, 'summary' => $summary, 'status' => 'pending', 'current_step_position' => 1,
                'amount' => $amount, 'currency' => $currency ? strtoupper($currency) : null,
                'submitted_at' => $submittedAt, 'context' => $context, 'metadata' => ['source' => 'phase17'],
            ]);

            foreach ($workflow->steps as $step) {
                $assigned = $this->resolveApprovers($workspace, $requester, $step->approver_type, $step->approver_role_slug, $step->approver_member_id, (bool) $step->allow_self_approval);
                $dueAt = $submittedAt->copy()->addHours(max(1, (int) $workflow->sla_hours));
                ApprovalRequestStep::create([
                    'approval_request_id' => $request->id, 'workflow_step_id' => $step->id, 'position' => $step->position,
                    'name' => $step->name, 'approver_type' => $step->approver_type, 'assigned_member_ids' => $assigned,
                    'status' => $step->position === 1 ? 'pending' : 'waiting',
                    'required_approvals' => max(1, min((int) $step->required_approvals, max(1, count($assigned)))),
                    'approved_count' => 0, 'allow_self_approval' => (bool) $step->allow_self_approval, 'due_at' => $dueAt,
                ]);
            }
            $first = $request->steps()->where('position', 1)->first();
            $request->update(['due_at' => $first?->due_at]);
            $this->record($request, $first, $requester, 'submitted', null, ['trigger_key' => $triggerKey]);
            $this->notifyStepApprovers($request->fresh(['steps', 'requester.user', 'workflow']), $first);
            return $request->fresh(['workflow', 'steps']);
        });
    }

    /** Handles the decide operation for the current WorkIntel workflow. */ public function decide(ApprovalItem $request, WorkspaceMember $actor, string $decision, ?string $note = null): ApprovalItem
    {
        abort_unless((int) $request->workspace_id === (int) $actor->workspace_id, 404);
        abort_unless($request->status === 'pending', 422, 'This approval request is already completed.');
        abort_unless(in_array($decision, ['approved', 'rejected', 'comment'], true), 422, 'Invalid approval decision.');
        $request->loadMissing(['steps', 'workflow', 'requester.user']);
        $step = $request->steps->firstWhere('position', (int) $request->current_step_position);
        abort_unless($step && $step->status === 'pending', 422, 'The current approval step is not actionable.');
        abort_unless($this->canActOnStep($step, $actor, $request), 403, 'This approval step is not assigned to you.');

        return DB::transaction(function () use ($request, $actor, $decision, $note, $step) {
            if ($decision === 'comment') {
                $this->record($request, $step, $actor, 'commented', $note);
                return $request->fresh($this->requestRelations());
            }
            $duplicate = ApprovalDecision::query()->where('approval_request_step_id', $step->id)->where('actor_member_id', $actor->id)
                ->where('decision', 'approved')->exists();
            abort_if($duplicate && $decision === 'approved', 422, 'You already approved this step.');

            $this->record($request, $step, $actor, $decision, $note);
            if ($decision === 'rejected') {
                $this->applySubjectDecision($request, 'rejected', $actor, $note);
                $step->update(['status' => 'rejected', 'completed_at' => now()]);
                $request->update(['status' => 'rejected', 'completed_at' => now(), 'due_at' => null]);
                $this->notifyRequester($request, 'rejected');
                return $request->fresh($this->requestRelations());
            }

            $approvedCount = ApprovalDecision::query()->where('approval_request_step_id', $step->id)->where('decision', 'approved')->distinct()->count('actor_member_id');
            $step->update(['approved_count' => $approvedCount]);
            if ($approvedCount < (int) $step->required_approvals) return $request->fresh($this->requestRelations());

            $step->update(['status' => 'approved', 'completed_at' => now()]);
            $next = $request->steps->first(fn (ApprovalRequestStep $candidate) => $candidate->position > $step->position);
            if ($next) {
                $next->update(['status' => 'pending']);
                $request->update(['current_step_position' => $next->position, 'due_at' => $next->due_at]);
                $this->notifyStepApprovers($request, $next);
            } else {
                $this->applySubjectDecision($request, 'approved', $actor, $note);
                $request->update(['status' => 'approved', 'completed_at' => now(), 'due_at' => null]);
                $this->notifyRequester($request, 'approved');
            }
            return $request->fresh($this->requestRelations());
        });
    }

    /** Determines whether the cancel condition is satisfied. */ public function cancel(ApprovalItem $request, WorkspaceMember $actor, ?string $note = null): ApprovalItem
    {
        abort_unless((int) $request->workspace_id === (int) $actor->workspace_id, 404);
        abort_unless($request->status === 'pending', 422, 'Only pending requests can be canceled.');
        abort_unless((int) $request->requester_member_id === (int) $actor->id || $actor->hasPermission('approvals.workflow_manage'), 403, 'You cannot cancel this request.');

        return DB::transaction(function () use ($request, $actor, $note) {
            $this->applySubjectCancellation($request);
            $request->loadMissing('steps');
            $step = $request->steps->firstWhere('position', (int) $request->current_step_position);
            $this->record($request, $step, $actor, 'canceled', $note ?: 'Canceled by requester.');
            $request->steps()->whereIn('status', ['pending', 'waiting'])->update(['status' => 'canceled', 'completed_at' => now()]);
            $request->update(['status' => 'canceled', 'completed_at' => now(), 'due_at' => null]);
            return $request->fresh($this->requestRelations());
        });
    }

    /** Synchronizes sync external decision data with the current application state. */ public function syncExternalDecision(string $subjectType, int $subjectId, string $decision, WorkspaceMember $actor, ?string $note = null): void
    {
        if (! $this->installed()) return;
        $request = ApprovalItem::query()->where('workspace_id', $actor->workspace_id)->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)->where('status', 'pending')->latest('id')->first();
        if (! $request) return;
        $request->load('steps');
        $step = $request->steps->firstWhere('position', (int) $request->current_step_position);
        $this->record($request, $step, $actor, $decision === 'approved' ? 'externally_approved' : 'externally_rejected', $note, ['legacy_endpoint' => true]);
        if ($step) $step->update(['status' => $decision, 'completed_at' => now()]);
        $request->steps()->where('position', '>', (int) $request->current_step_position)->whereIn('status', ['waiting', 'pending'])
            ->update(['status' => 'skipped', 'completed_at' => now()]);
        $request->update(['status' => $decision, 'completed_at' => now(), 'due_at' => null]);
        $this->notifyRequester($request, $decision);
    }

    /** Determines whether the can act on step condition is satisfied. */ public function canActOnStep(ApprovalRequestStep $step, WorkspaceMember $actor, ApprovalItem $request): bool
    {
        if (! $step->allow_self_approval && (int) $request->requester_member_id === (int) $actor->id) return false;
        $assigned = array_map('intval', $step->assigned_member_ids ?? []);
        if (in_array((int) $actor->id, $assigned, true)) return true;
        return ApprovalDelegation::query()->where('workspace_id', $actor->workspace_id)->whereIn('delegator_member_id', $assigned)
            ->where('delegate_member_id', $actor->id)->where('status', 'active')->where('starts_at', '<=', now())->where('ends_at', '>=', now())->exists();
    }

    /** Handles the escalate due operation for the current WorkIntel workflow. */ public function escalateDue(): int
    {
        if (! $this->installed()) return 0;
        $count = 0;
        ApprovalRequestStep::query()->with(['request.workflow', 'request.requester.user'])->where('status', 'pending')->whereNotNull('due_at')->where('due_at', '<=', now())
            ->orderBy('id')->chunkById(100, function ($steps) use (&$count) {
                foreach ($steps as $step) {
                    $request = $step->request;
                    if (! $request || $request->status !== 'pending') continue;
                    $workspaceForModule = Workspace::find($request->workspace_id);
                    if (! $workspaceForModule || ! app(\App\Services\Modules\WorkspaceModuleService::class)->shouldProcessBackground($workspaceForModule, 'approvals')) continue;
                    $roleSlug = $request->workflow?->escalation_role_slug ?: 'admin';
                    $extra = WorkspaceMember::query()->where('workspace_id', $request->workspace_id)->where('status', 'active')
                        ->whereHas('roles', fn ($q) => $q->where('slug', $roleSlug))->pluck('id')->map(fn ($id) => (int) $id)->all();
                    if (! $extra) $extra = $this->fallbackApprovers($request->workspace_id, $request->requester_member_id);
                    $assigned = array_values(array_unique(array_merge(array_map('intval', $step->assigned_member_ids ?? []), $extra)));
                    $step->update(['assigned_member_ids' => $assigned, 'due_at' => now()->addHours(24)]);
                    $request->update(['due_at' => $step->due_at]);
                    $this->record($request, $step, null, 'escalated', 'Approval SLA exceeded.', ['role' => $roleSlug]);
                    $this->notifyStepApprovers($request, $step, true);
                    $count++;
                }
            });
        return $count;
    }

    /** Handles the request relations operation for the current WorkIntel workflow. */ public function requestRelations(): array
    {
        return ['workflow:id,name,trigger_key,sla_hours,escalation_role_slug', 'requester.user:id,first_name,last_name,email', 'steps', 'decisions.actor.user:id,first_name,last_name'];
    }

    /** Handles the matches conditions operation for the current WorkIntel workflow. */ private function matchesConditions(array $conditions, array $context): bool
    {
        if (! $conditions) return true;
        $rows = isset($conditions['all']) && is_array($conditions['all']) ? $conditions['all'] : $conditions;
        foreach ($rows as $condition) {
            if (! is_array($condition) || empty($condition['field'])) continue;
            $actual = data_get($context, $condition['field']);
            $expected = $condition['value'] ?? null;
            $operator = $condition['operator'] ?? 'eq';
            $ok = match ($operator) {
                'eq' => (string) $actual === (string) $expected,
                'neq' => (string) $actual !== (string) $expected,
                'gt' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
                'gte' => is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected,
                'lt' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
                'lte' => is_numeric($actual) && is_numeric($expected) && (float) $actual <= (float) $expected,
                'in' => in_array((string) $actual, array_map('strval', is_array($expected) ? $expected : explode(',', (string) $expected)), true),
                default => false,
            };
            if (! $ok) return false;
        }
        return true;
    }

    /** Returns resolve approvers data required by the current workflow. */ private function resolveApprovers(Workspace $workspace, WorkspaceMember $requester, string $type, ?string $roleSlug, ?int $memberId, bool $allowSelf): array
    {
        $ids = match ($type) {
            'manager' => $requester->manager_id ? [(int) $requester->manager_id] : [],
            'role' => WorkspaceMember::query()->where('workspace_id', $workspace->id)->where('status', 'active')->whereHas('roles', fn ($q) => $q->where('slug', $roleSlug))->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'member' => $memberId ? [(int) $memberId] : [],
            default => [],
        };
        if (! $allowSelf) $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== (int) $requester->id));
        if (! $ids) $ids = $this->fallbackApprovers($workspace->id, $allowSelf ? null : $requester->id);
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** Handles the fallback approvers operation for the current WorkIntel workflow. */ private function fallbackApprovers(int $workspaceId, ?int $excludeMemberId = null): array
    {
        $query = WorkspaceMember::query()->where('workspace_id', $workspaceId)->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['owner', 'admin']));
        if ($excludeMemberId) $query->whereKeyNot($excludeMemberId);
        $ids = $query->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($ids) return $ids;
        return WorkspaceMember::query()->where('workspace_id', $workspaceId)->where('status', 'active')->when($excludeMemberId, fn ($q) => $q->whereKeyNot($excludeMemberId))->limit(1)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** Handles the record operation for the current WorkIntel workflow. */ private function record(ApprovalItem $request, ?ApprovalRequestStep $step, ?WorkspaceMember $actor, string $decision, ?string $note = null, array $metadata = []): ApprovalDecision
    {
        return ApprovalDecision::create([
            'workspace_id' => $request->workspace_id, 'approval_request_id' => $request->id,
            'approval_request_step_id' => $step?->id, 'actor_member_id' => $actor?->id,
            'decision' => $decision, 'note' => $note, 'metadata' => $metadata ?: null, 'acted_at' => now(),
        ]);
    }

    /** Sends notify step approvers information to the configured recipient. */ private function notifyStepApprovers(ApprovalItem $request, ?ApprovalRequestStep $step, bool $escalated = false): void
    {
        if (! $step || ! Schema::hasTable('workspace_notifications')) return;
        $workspace = Workspace::find($request->workspace_id); if (! $workspace) return;
        $members = WorkspaceMember::query()->with('user')->whereIn('id', array_map('intval', $step->assigned_member_ids ?? []))->get();
        foreach ($members as $member) if ($member->user) $this->notifications->notify(
            $workspace, $member->user, 'approvals', $escalated ? 'approval.escalated' : 'approval.assigned',
            $escalated ? 'Approval escalated' : 'Approval needs your review', $request->title, $escalated ? 'warning' : 'info', ['approval_request_id' => $request->id]
        );
    }

    /** Sends notify requester information to the configured recipient. */ private function notifyRequester(ApprovalItem $request, string $decision): void
    {
        if (! $request->workflow?->notify_requester) return;
        $request->loadMissing('requester.user'); $workspace = Workspace::find($request->workspace_id);
        if ($workspace && $request->requester?->user) $this->notifications->notify(
            $workspace, $request->requester->user, 'approvals', 'approval.'.$decision,
            'Request '.ucfirst($decision), $request->title, $decision === 'approved' ? 'success' : 'warning', ['approval_request_id' => $request->id]
        );
    }

    /** Handles the apply subject cancellation operation for the current WorkIntel workflow. */ private function applySubjectCancellation(ApprovalItem $request): void
    {
        switch ($request->subject_type) {
            case 'leave_request':
                LeaveRequest::query()->where('workspace_id', $request->workspace_id)->whereKey($request->subject_id)->where('status', 'pending')->update(['status' => 'canceled']);
                break;
            case 'timesheet_period':
                $period = TimesheetPeriod::query()->where('workspace_id', $request->workspace_id)->find($request->subject_id);
                if ($period && $period->status === 'submitted') {
                    TimeEntry::query()->where('workspace_id', $period->workspace_id)->where('member_id', $period->member_id)
                        ->whereDate('date', '>=', $period->week_start->toDateString())->whereDate('date', '<=', $period->week_end->toDateString())
                        ->where('approval_status', 'submitted')->update(['approval_status' => 'draft', 'updated_at' => now()]);
                    $period->update(['status' => 'open', 'submitted_at' => null]);
                }
                break;
            case 'project_expense':
                ProjectExpense::query()->where('workspace_id', $request->workspace_id)->whereKey($request->subject_id)->where('approval_status', 'pending')->update(['approval_status' => 'canceled']);
                break;
            case 'payroll_run':
                $run = PayrollRun::query()->where('workspace_id', $request->workspace_id)->find($request->subject_id);
                if ($run && $run->status === 'review') {
                    $run->update(['status' => 'calculated', 'submitted_at' => null, 'submitted_by' => null]);
                    $run->items()->update(['status' => 'calculated']);
                }
                break;
            case 'shift_swap_request':
                ShiftSwapRequest::query()->where('workspace_id', $request->workspace_id)->whereKey($request->subject_id)->where('status', 'pending')->update(['status' => 'canceled']);
                break;
            case 'attendance_correction':
                AttendanceCorrectionRequest::query()->where('workspace_id', $request->workspace_id)->whereKey($request->subject_id)->where('status', 'pending')->update(['status' => 'canceled']);
                break;
            case 'expense_claim':
                ExpenseClaim::query()->where('workspace_id', $request->workspace_id)->whereKey($request->subject_id)->where('status', 'submitted')->update(['status' => 'canceled']);
                break;
            case 'purchase_request':
                PurchaseRequest::query()->where('workspace_id', $request->workspace_id)->whereKey($request->subject_id)->where('status', 'submitted')->update(['status' => 'canceled']);
                break;
            case 'compensation_review_item':
                CompensationReviewItem::query()->where('workspace_id', $request->workspace_id)->whereKey($request->subject_id)->where('status', 'proposed')->update(['status' => 'draft']);
                break;
        }
    }

    /** Handles the apply subject decision operation for the current WorkIntel workflow. */ private function applySubjectDecision(ApprovalItem $request, string $decision, WorkspaceMember $actor, ?string $note): void
    {
        $userId = $actor->user_id;
        switch ($request->subject_type) {
            case 'leave_request':
                $leave = LeaveRequest::query()->where('workspace_id', $request->workspace_id)->findOrFail($request->subject_id);
                if ($leave->status !== 'pending') return;
                $leave->load(['member', 'leaveType.policy']);
                if ($decision === 'approved') {
                    $balance = $this->leaveBalances->balanceFor($leave->member, $leave->leaveType, $leave->start_date->year);
                    $policy = $this->leaveBalances->policyFor($leave->leaveType);
                    if (! $policy->allow_negative_balance && (float) $leave->days > $balance['remaining']) throw ValidationException::withMessages(['decision' => ['Leave balance is no longer sufficient.']]);
                }
                $leave->update(['status' => $decision, 'reviewed_by' => $userId, 'reviewed_at' => now(), 'review_note' => $note]);
                if ($decision === 'approved') $this->leaveBalances->balanceFor($leave->member, $leave->leaveType, $leave->start_date->year);
                break;

            case 'timesheet_period':
                $period = TimesheetPeriod::query()->where('workspace_id', $request->workspace_id)->findOrFail($request->subject_id);
                if ($decision === 'approved') {
                    TimeEntry::query()->where('workspace_id', $period->workspace_id)->where('member_id', $period->member_id)
                        ->whereDate('date', '>=', $period->week_start->toDateString())->whereDate('date', '<=', $period->week_end->toDateString())
                        ->where('approval_status', 'submitted')->update(['approval_status' => 'approved', 'updated_at' => now()]);
                    $period->update(['status' => 'approved']);
                } else {
                    TimeEntry::query()->where('workspace_id', $period->workspace_id)->where('member_id', $period->member_id)
                        ->whereDate('date', '>=', $period->week_start->toDateString())->whereDate('date', '<=', $period->week_end->toDateString())
                        ->where('approval_status', 'submitted')->update(['approval_status' => 'rejected', 'updated_at' => now()]);
                    $period->update(['status' => 'open', 'submitted_at' => null]);
                }
                break;

            case 'project_expense':
                $expense = ProjectExpense::query()->where('workspace_id', $request->workspace_id)->findOrFail($request->subject_id);
                $expense->update(['approval_status' => $decision, 'reviewed_by' => $userId, 'reviewed_at' => now()]);
                break;

            case 'payroll_run':
                $run = PayrollRun::query()->where('workspace_id', $request->workspace_id)->findOrFail($request->subject_id);
                if ($decision === 'approved') {
                    abort_unless($run->status === 'review', 422, 'Payroll is no longer under review.');
                    $run->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $userId, 'locked_at' => now(), 'locked_by' => $userId]);
                    $run->items()->update(['status' => 'approved']);
                    PayrollAction::create(['payroll_run_id' => $run->id, 'workspace_id' => $run->workspace_id, 'user_id' => $userId, 'action' => 'approved_via_inbox', 'from_status' => 'review', 'to_status' => 'approved', 'note' => $note, 'occurred_at' => now()]);
                } else {
                    abort_unless($run->status === 'review', 422, 'Payroll is no longer under review.');
                    $run->update(['status' => 'calculated', 'submitted_at' => null, 'submitted_by' => null]);
                    $run->items()->update(['status' => 'calculated']);
                    PayrollAction::create(['payroll_run_id' => $run->id, 'workspace_id' => $run->workspace_id, 'user_id' => $userId, 'action' => 'rejected_via_inbox', 'from_status' => 'review', 'to_status' => 'calculated', 'note' => $note, 'occurred_at' => now()]);
                }
                break;

            case 'shift_swap_request':
                $swap = ShiftSwapRequest::query()->where('workspace_id', $request->workspace_id)->findOrFail($request->subject_id);
                abort_unless($swap->status === 'pending', 422, 'Schedule change is no longer pending.');
                if ($decision === 'approved') {
                    $assignment = $swap->assignment()->lockForUpdate()->firstOrFail();
                    if ($swap->request_type === 'drop') $assignment->delete();
                    else {
                        abort_unless($swap->target_member_id, 422, 'A target employee is required for a swap.');
                        $conflict = ShiftAssignment::query()->where('workspace_id', $request->workspace_id)->where('member_id', $swap->target_member_id)
                            ->whereDate('date', $assignment->date->toDateString())->where('id', '!=', $assignment->id)->exists();
                        abort_if($conflict, 422, 'The target employee already has a shift on this date.');
                        $assignment->update(['member_id' => $swap->target_member_id]);
                    }
                }
                $swap->update(['status' => $decision, 'reviewed_by' => $userId, 'reviewed_at' => now(), 'review_note' => $note]);
                break;

            case 'expense_claim':
                $claim = ExpenseClaim::query()->where('workspace_id', $request->workspace_id)->findOrFail($request->subject_id);
                abort_unless($claim->status === 'submitted', 422, 'Expense claim is no longer under review.');
                $claim->update(['status' => $decision, 'approved_amount' => $decision === 'approved' ? $claim->total_amount : 0, 'reimbursement_status' => $decision === 'approved' ? 'ready' : 'not_ready', 'reviewed_by' => $userId, 'reviewed_at' => now()]);
                break;

            case 'purchase_request':
                $purchase = PurchaseRequest::query()->where('workspace_id', $request->workspace_id)->findOrFail($request->subject_id);
                abort_unless($purchase->status === 'submitted', 422, 'Purchase request is no longer under review.');
                $purchase->update(['status' => $decision, 'reviewed_by' => $userId, 'reviewed_at' => now()]);
                break;

            case 'compensation_review_item':
                $item = CompensationReviewItem::query()->where('workspace_id', $request->workspace_id)->findOrFail($request->subject_id);
                abort_unless($item->status === 'proposed', 422, 'Compensation proposal is no longer under review.');
                $item->update(['status' => $decision, 'reviewed_by' => $userId, 'reviewed_at' => now()]);
                break;

            case 'attendance_correction':
                $correction = AttendanceCorrectionRequest::query()->where('workspace_id', $request->workspace_id)->findOrFail($request->subject_id);
                abort_unless($correction->status === 'pending', 422, 'Attendance correction is no longer pending.');
                $target = WorkspaceMember::query()->where('workspace_id', $request->workspace_id)->findOrFail($correction->member_id);
                if ($decision === 'approved') {
                    $record = AttendanceRecord::query()->where('workspace_id', $request->workspace_id)->where('member_id', $target->id)->whereDate('date', $correction->date->toDateString())->first();
                    if (! $record) $record = AttendanceRecord::create(['workspace_id' => $request->workspace_id, 'member_id' => $target->id, 'date' => $correction->date->toDateString(), 'status' => 'present', 'source' => 'manual']);
                    if ($correction->requested_clock_in_at) $record->clock_in_at = $correction->requested_clock_in_at;
                    if ($correction->requested_clock_out_at) $record->clock_out_at = $correction->requested_clock_out_at;
                    $record->flag_type = null; $record->flagged_at = null; $record->source = 'manual'; $record->save();
                    $this->attendanceCalculator->recalculate($record);
                    $correction->attendance_record_id = $record->id;
                    $this->attendancePolicies->recordEvent($target->workspace, $target, $record, 'correction_approved', 'manual', [], ['correction_id' => $correction->id]);
                }
                $correction->forceFill(['status' => $decision, 'reviewed_by' => $userId, 'reviewed_at' => now(), 'review_note' => $note])->save();
                break;
        }
    }
}
