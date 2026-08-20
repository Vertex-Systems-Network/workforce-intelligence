<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\WorkspaceMember;
use App\Services\LeaveBalanceService;
use App\Services\Approvals\ApprovalEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Provides leave controller behavior within the WorkIntel application. */ class LeaveController extends Controller
{
    /** Returns the requested resource collection. */ public function index(Request $request, LeaveBalanceService $balances): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $currentMember = $request->attributes->get('workspaceMember');
        $canManage = $currentMember->hasPermission('attendance.manage');
        $year = (int) ($request->query('year') ?: now($workspace->timezone)->year);

        $requests = LeaveRequest::query()
            ->with(['member.user:id,first_name,last_name', 'leaveType.policy'])
            ->where('workspace_id', $workspace->id)
            ->when(! $canManage, fn ($query) => $query->where('member_id', $currentMember->id))
            ->orderByDesc('created_at')->get();

        $types = LeaveType::query()
            ->with('policy')
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->orderBy('name')->get();

        $people = WorkspaceMember::query()
            ->with('user:id,first_name,last_name')
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->orderBy('id')->get();

        $myBalances = $types->map(fn (LeaveType $type) => $balances->balanceFor($currentMember, $type, $year))->values();

        return response()->json([
            'requests' => $requests,
            'leave_types' => $types,
            'people' => $people,
            'balances' => $myBalances,
            'balance_year' => $year,
            'current_member_id' => $currentMember->id,
            'can_manage' => $canManage,
        ]);
    }

    /** Creates and persists the requested resource. */ public function store(Request $request, LeaveBalanceService $balances, ApprovalEngine $approvals): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $currentMember = $request->attributes->get('workspaceMember');
        $canManage = $currentMember->hasPermission('attendance.manage');
        $data = $request->validate([
            'member_id' => ['nullable', 'integer'],
            'leave_type_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:3000'],
        ]);

        $memberId = $canManage && ! empty($data['member_id']) ? (int) $data['member_id'] : $currentMember->id;
        $member = WorkspaceMember::query()->where('workspace_id', $workspace->id)->findOrFail($memberId);
        $type = LeaveType::query()->with('policy')->where('workspace_id', $workspace->id)->findOrFail($data['leave_type_id']);
        $policy = $balances->policyFor($type);
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $days = $balances->calculateRequestDays($type, $start, $end);

        if ($days <= 0) {
            throw ValidationException::withMessages(['start_date' => ['This request contains no working leave days under the selected policy.']]);
        }

        if ($policy->min_notice_days > 0 && ! $canManage) {
            $notice = now($workspace->timezone)->startOfDay()->diffInDays($start, false);
            if ($notice < $policy->min_notice_days) {
                throw ValidationException::withMessages(['start_date' => ["This leave type requires at least {$policy->min_notice_days} days notice."]]);
            }
        }

        if ($policy->max_consecutive_days && $days > $policy->max_consecutive_days) {
            throw ValidationException::withMessages(['end_date' => ["This leave type allows at most {$policy->max_consecutive_days} consecutive working days."]]);
        }

        if ($policy->probation_months > 0 && $member->joining_date && ! $canManage) {
            $eligibleAt = $member->joining_date->copy()->addMonths($policy->probation_months);
            if ($start->lt($eligibleAt)) {
                throw ValidationException::withMessages(['start_date' => ['This employee is still inside the leave-policy probation period.']]);
            }
        }

        $overlap = LeaveRequest::query()
            ->where('workspace_id', $workspace->id)
            ->where('member_id', $member->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => ['This request overlaps another pending or approved leave request.']]);
        }

        $balance = $balances->balanceFor($member, $type, $start->year);
        if (! $policy->allow_negative_balance && $days > $balance['remaining']) {
            throw ValidationException::withMessages(['leave_type_id' => ['The requested leave exceeds the available balance.']]);
        }

        $autoApprove = ! $policy->requires_approval;
        $leave = LeaveRequest::create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'leave_type_id' => $type->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'reason' => $data['reason'] ?? null,
            'status' => $autoApprove ? 'approved' : 'pending',
            'reviewed_by' => $autoApprove ? $request->user()->id : null,
            'reviewed_at' => $autoApprove ? now() : null,
            'review_note' => $autoApprove ? 'Automatically approved by leave policy.' : null,
        ]);

        if ($autoApprove) $balances->balanceFor($member, $type, $start->year);
        else $approvals->submitFor(
            $workspace, $member, 'leave.submitted', 'leave_request', $leave,
            ['department_id' => $member->department_id, 'leave_type_id' => $type->id, 'days' => (float) $days],
            'Leave request · '.trim($member->user?->first_name.' '.$member->user?->last_name),
            $type->name.' · '.$start->toDateString().' → '.$end->toDateString()
        );

        return response()->json(['data' => $leave->load(['member.user', 'leaveType.policy'])], 201);
    }

    /** Handles the review operation for the current WorkIntel workflow. */ public function review(Request $request, LeaveRequest $leaveRequest, LeaveBalanceService $balances, ApprovalEngine $approvals): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($leaveRequest->workspace_id === $workspace->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_unless($leaveRequest->status === 'pending', 422, 'Only pending leave requests can be reviewed.');

        $leaveRequest->load(['member', 'leaveType.policy']);
        if ($data['status'] === 'approved') {
            $balance = $balances->balanceFor($leaveRequest->member, $leaveRequest->leaveType, $leaveRequest->start_date->year);
            $policy = $balances->policyFor($leaveRequest->leaveType);
            if (! $policy->allow_negative_balance && (float) $leaveRequest->days > $balance['remaining']) {
                throw ValidationException::withMessages(['status' => ['This request can no longer be approved because the balance is insufficient.']]);
            }
        }

        $leaveRequest->update([
            'status' => $data['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? null,
        ]);

        if ($data['status'] === 'approved') {
            $balances->balanceFor($leaveRequest->member, $leaveRequest->leaveType, $leaveRequest->start_date->year);
        }
        $approvals->syncExternalDecision('leave_request', $leaveRequest->id, $data['status'], $request->attributes->get('workspaceMember'), $data['review_note'] ?? null);

        return response()->json(['data' => $leaveRequest->fresh()->load(['member.user', 'leaveType.policy'])]);
    }

    /** Handles the store type operation for the current WorkIntel workflow. */ public function storeType(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $this->typeRules($request, $workspace->id);

        $type = DB::transaction(function () use ($workspace, $data) {
            $type = LeaveType::create([
                'workspace_id' => $workspace->id,
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
                'is_paid' => $data['is_paid'],
                'annual_allowance_days' => $data['annual_allowance_days'],
                'status' => 'active',
            ]);
            LeavePolicy::create(['workspace_id' => $workspace->id, 'leave_type_id' => $type->id, ...$data['policy']]);
            return $type;
        });

        return response()->json(['data' => $type->load('policy')], 201);
    }

    /** Updates update type data for the requested resource. */ public function updateType(Request $request, LeaveType $leaveType): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($leaveType->workspace_id === $workspace->id, 404);
        $data = $this->typeRules($request, $workspace->id, $leaveType->id);

        DB::transaction(function () use ($leaveType, $workspace, $data) {
            $leaveType->update([
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
                'is_paid' => $data['is_paid'],
                'annual_allowance_days' => $data['annual_allowance_days'],
            ]);
            LeavePolicy::updateOrCreate(['leave_type_id' => $leaveType->id], ['workspace_id' => $workspace->id, ...$data['policy']]);
        });

        return response()->json(['data' => $leaveType->fresh()->load('policy')]);
    }

    /** Handles the adjust balance operation for the current WorkIntel workflow. */ public function adjustBalance(Request $request, LeaveBalanceService $balances): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'member_id' => ['required', 'integer'],
            'leave_type_id' => ['required', 'integer'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'days' => ['required', 'numeric', 'between:-365,365'],
        ]);
        $member = WorkspaceMember::query()->where('workspace_id', $workspace->id)->findOrFail($data['member_id']);
        $type = LeaveType::query()->where('workspace_id', $workspace->id)->findOrFail($data['leave_type_id']);
        return response()->json(['data' => $balances->adjust($member, $type, $data['year'], (float) $data['days'])]);
    }

    /** Handles the type rules operation for the current WorkIntel workflow. */ private function typeRules(Request $request, int $workspaceId, ?int $ignoreTypeId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:32', Rule::unique('leave_types', 'code')->where('workspace_id', $workspaceId)->ignore($ignoreTypeId)],
            'is_paid' => ['required', 'boolean'],
            'annual_allowance_days' => ['required', 'numeric', 'min:0', 'max:365'],
            'policy.accrual_method' => ['sometimes', Rule::in(['annual', 'monthly', 'none'])],
            'policy.monthly_accrual_days' => ['nullable', 'numeric', 'min:0', 'max:31'],
            'policy.carryover_days' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'policy.min_notice_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'policy.max_consecutive_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'policy.probation_months' => ['nullable', 'integer', 'min:0', 'max:60'],
            'policy.allow_negative_balance' => ['sometimes', 'boolean'],
            'policy.requires_approval' => ['sometimes', 'boolean'],
            'policy.exclude_weekends' => ['sometimes', 'boolean'],
            'policy.exclude_holidays' => ['sometimes', 'boolean'],
        ]);

        $policy = $validated['policy'] ?? [];
        $policy['accrual_method'] = $policy['accrual_method'] ?? 'annual';
        $policy['monthly_accrual_days'] = $policy['monthly_accrual_days'] ?? 0;
        $policy['carryover_days'] = $policy['carryover_days'] ?? 0;
        $policy['min_notice_days'] = $policy['min_notice_days'] ?? 0;
        $policy['max_consecutive_days'] = $policy['max_consecutive_days'] ?? null;
        $policy['probation_months'] = $policy['probation_months'] ?? 0;
        $policy['allow_negative_balance'] = $policy['allow_negative_balance'] ?? false;
        $policy['requires_approval'] = $policy['requires_approval'] ?? true;
        $policy['exclude_weekends'] = $policy['exclude_weekends'] ?? true;
        $policy['exclude_holidays'] = $policy['exclude_holidays'] ?? true;
        $validated['policy'] = $policy;
        return $validated;
    }
}
