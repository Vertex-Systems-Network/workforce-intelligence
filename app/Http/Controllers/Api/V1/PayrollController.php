<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CompensationProfile;
use App\Models\PayrollAction;
use App\Models\PayrollAdjustment;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\WorkspaceMember;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Approvals\ApprovalEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Provides payroll controller behavior within the WorkIntel application. */ class PayrollController extends Controller
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly PayrollCalculator $calculator, private readonly ApprovalEngine $approvals) {}

    /** Handles the runs operation for the current WorkIntel workflow. */ public function runs(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');
        abort_unless($member->hasPermission('payroll.view_all') || $member->hasPermission('payroll.manage'), 403, 'You do not have access to company payroll.');

        $runs = PayrollRun::query()
            ->where('workspace_id', $workspace->id)
            ->withCount('items')
            ->withSum('items as net_total', 'net_pay')
            ->withSum('items as gross_total', 'gross_pay')
            ->orderByDesc('period_start')
            ->get();

        return response()->json([
            'data' => $runs,
            'workspace_currency' => $workspace->currency,
            'can_manage' => $member->hasPermission('payroll.manage'),
        ]);
    }

    /** Handles the show run operation for the current WorkIntel workflow. */ public function showRun(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');
        $this->ensureRun($workspace->id, $payrollRun);
        abort_unless($member->hasPermission('payroll.view_all') || $member->hasPermission('payroll.manage'), 403);

        return response()->json(['data' => $this->loadRun($payrollRun)]);
    }

    /** Handles the store run operation for the current WorkIntel workflow. */ public function storeRun(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'pay_date' => ['nullable', 'date', 'after_or_equal:period_end'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'note' => ['nullable', 'string', 'max:5000'],
            'run_type' => ['sometimes', Rule::in(['regular', 'off_cycle', 'contractor', 'termination'])],
            'compliance_pack_id' => ['nullable', 'integer'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer'],
        ]);

        $currency = strtoupper($data['currency'] ?? $workspace->currency);
        $runType = $data['run_type'] ?? 'regular';
        $memberIds = array_values(array_unique(array_map('intval', $data['member_ids'] ?? [])));
        if ($runType !== 'regular' && ! $memberIds) {
            throw ValidationException::withMessages(['member_ids' => ['Off-cycle, contractor and termination payroll runs require one or more selected members.']]);
        }
        if ($memberIds) {
            $valid = WorkspaceMember::query()->where('workspace_id', $workspace->id)->whereIn('id', $memberIds)->count();
            if ($valid !== count($memberIds)) throw ValidationException::withMessages(['member_ids' => ['One or more selected members do not belong to this workspace.']]);
        }
        if (! empty($data['compliance_pack_id'])) {
            $pack = \App\Models\PayrollCompliancePack::query()->where('workspace_id', $workspace->id)->findOrFail($data['compliance_pack_id']);
            if (strtoupper($pack->currency) !== $currency) throw ValidationException::withMessages(['compliance_pack_id' => ['Compliance pack currency must match the payroll run currency.']]);
        }
        if ($currency !== strtoupper($workspace->currency)) {
            throw ValidationException::withMessages(['currency' => ['Payroll runs must use the workspace currency until currency conversion is enabled.']]);
        }

        $overlap = $runType === 'regular' && PayrollRun::query()
            ->where('workspace_id', $workspace->id)->where('run_type', 'regular')
            ->whereNotIn('status', ['void'])
            ->whereDate('period_start', '<=', $data['period_end'])
            ->whereDate('period_end', '>=', $data['period_start'])
            ->exists();
        if ($overlap) throw ValidationException::withMessages(['period_start' => ['This regular period overlaps an existing regular payroll run.']]);

        unset($data['member_ids']);
        $run = PayrollRun::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            ...$data,
            'run_type' => $runType,
            'currency' => $currency,
            'status' => 'draft',
        ]);
        foreach ($memberIds as $memberId) $run->selectedMembers()->create(['member_id' => $memberId]);
        $this->logAction($request, $run, 'created', null, 'draft', $data['note'] ?? null);

        return response()->json(['data' => $run], 201);
    }

    /** Handles the calculate operation for the current WorkIntel workflow. */ public function calculate(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureRun($workspace->id, $payrollRun);
        $from = $payrollRun->status;
        $result = $this->calculator->calculate($payrollRun);
        $this->logAction($request, $payrollRun->fresh(), 'calculated', $from, 'calculated', null, $result);

        return response()->json(['data' => $this->loadRun($payrollRun->fresh()), ...$result]);
    }

    /** Handles the submit operation for the current WorkIntel workflow. */ public function submit(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureRun($workspace->id, $payrollRun);
        abort_if($payrollRun->locked_at, 422, 'This payroll run is locked.');
        abort_unless($payrollRun->status === 'calculated', 422, 'Calculate the payroll before submitting it for review.');
        abort_if($payrollRun->items()->count() === 0, 422, 'This payroll run has no calculated items.');

        $payrollRun->update(['status' => 'review', 'submitted_at' => now(), 'submitted_by' => $request->user()->id]);
        $payrollRun->items()->update(['status' => 'review']);
        $this->logAction($request, $payrollRun, 'submitted', 'calculated', 'review', $request->input('note'));
        $member = $request->attributes->get('workspaceMember');
        $net = (float) $payrollRun->items()->sum('net_pay');
        $approval = $this->approvals->submitFor(
            $workspace, $member, 'payroll.submitted', 'payroll_run', $payrollRun,
            ['department_id' => $member->department_id, 'amount' => $net, 'currency' => $payrollRun->currency, 'period_start' => $payrollRun->period_start->toDateString()],
            'Payroll · '.$payrollRun->name,
            $payrollRun->period_start->toDateString().' → '.$payrollRun->period_end->toDateString(),
            $net, $payrollRun->currency
        );

        return response()->json(['data' => $this->loadRun($payrollRun->fresh()), 'approval_request_id' => $approval?->id]);
    }

    /** Handles the approve operation for the current WorkIntel workflow. */ public function approve(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureRun($workspace->id, $payrollRun);
        abort_unless($payrollRun->status === 'review', 422, 'Only payroll under review can be approved.');

        DB::transaction(function () use ($request, $payrollRun) {
            $payrollRun->update([
                'status' => 'approved', 'approved_at' => now(), 'approved_by' => $request->user()->id,
                'locked_at' => now(), 'locked_by' => $request->user()->id,
            ]);
            $payrollRun->items()->update(['status' => 'approved']);
            $this->logAction($request, $payrollRun, 'approved', 'review', 'approved', $request->input('note'));
        });

        $this->approvals->syncExternalDecision('payroll_run', $payrollRun->id, 'approved', $request->attributes->get('workspaceMember'), $request->input('note'));
        return response()->json(['data' => $this->loadRun($payrollRun->fresh())]);
    }

    /** Handles the mark paid operation for the current WorkIntel workflow. */ public function markPaid(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureRun($workspace->id, $payrollRun);
        abort_unless($payrollRun->status === 'approved', 422, 'Approve the payroll before marking it paid.');

        $payrollRun->update(['status' => 'paid', 'paid_at' => now(), 'paid_by' => $request->user()->id]);
        $payrollRun->items()->update(['status' => 'paid']);
        $this->logAction($request, $payrollRun, 'paid', 'approved', 'paid', $request->input('note'));

        return response()->json(['data' => $this->loadRun($payrollRun->fresh())]);
    }

    /** Handles the destroy run operation for the current WorkIntel workflow. */ public function destroyRun(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureRun($workspace->id, $payrollRun);
        abort_if($payrollRun->locked_at || in_array($payrollRun->status, ['approved', 'paid'], true), 422, 'Approved or paid payroll cannot be deleted.');
        $payrollRun->delete();
        return response()->json(['message' => 'Payroll run deleted.']);
    }

    /** Handles the compensation operation for the current WorkIntel workflow. */ public function compensation(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $members = WorkspaceMember::query()
            ->with(['user:id,first_name,last_name', 'department:id,name', 'jobTitle:id,name'])
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->orderBy('id')->get();

        $profiles = CompensationProfile::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->orderByDesc('effective_from')->get()->groupBy('member_id');

        return response()->json([
            'data' => $members->map(function (WorkspaceMember $member) use ($profiles) {
                $profile = $profiles->get($member->id)?->first();
                return [
                    'member_id' => $member->id,
                    'name' => trim($member->user->first_name.' '.$member->user->last_name),
                    'email' => $member->user->email,
                    'job_title' => $member->jobTitle?->name ?? $member->job_title,
                    'department' => $member->department?->name,
                    'profile' => $profile,
                ];
            })->values(),
            'workspace_currency' => $workspace->currency,
        ]);
    }

    /** Updates update compensation data for the requested resource. */ public function updateCompensation(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($member->workspace_id === $workspace->id, 404);

        $data = $request->validate([
            'pay_type' => ['required', Rule::in(['hourly', 'daily', 'monthly', 'yearly', 'project'])],
            'currency' => ['required', 'string', 'size:3'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'daily_rate' => ['nullable', 'numeric', 'min:0'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'annual_salary' => ['nullable', 'numeric', 'min:0'],
            'project_rate' => ['nullable', 'numeric', 'min:0'],
            'premium_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'standard_hours_per_day' => ['required', 'numeric', 'min:1', 'max:24'],
            'standard_hours_per_week' => ['required', 'numeric', 'min:1', 'max:168'],
            'overtime_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'weekend_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'holiday_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'default_tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'deduct_unpaid_leave' => ['required', 'boolean'],
            'proration_mode' => ['required', Rule::in(['calendar_days', 'none'])],
            'effective_from' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:3000'],
        ]);

        $currency = strtoupper($data['currency']);
        if ($currency !== strtoupper($workspace->currency)) {
            throw ValidationException::withMessages(['currency' => ['Compensation must use the workspace currency until currency conversion is enabled.']]);
        }
        $this->validatePayRate($data);

        $profile = DB::transaction(function () use ($workspace, $member, $data, $currency) {
            $current = CompensationProfile::query()
                ->where('workspace_id', $workspace->id)->where('member_id', $member->id)->where('status', 'active')
                ->orderByDesc('effective_from')->first();

            $newEffectiveFrom = \Carbon\Carbon::parse($data['effective_from']);
            if ($current && $newEffectiveFrom->lt($current->effective_from)) {
                throw ValidationException::withMessages(['effective_from' => ['The new effective date cannot be earlier than the current active compensation profile.']]);
            }

            if ($current && $newEffectiveFrom->isSameDay($current->effective_from)) {
                $current->update([...$data, 'currency' => $currency, 'status' => 'active']);
                return $current->fresh();
            }

            if ($current) {
                $current->update(['status' => 'superseded', 'effective_to' => $newEffectiveFrom->copy()->subDay()->toDateString()]);
            }

            return CompensationProfile::create([
                'workspace_id' => $workspace->id, 'member_id' => $member->id, ...$data, 'currency' => $currency, 'status' => 'active',
            ]);
        });

        return response()->json(['data' => $profile], 201);
    }

    /** Handles the add adjustment operation for the current WorkIntel workflow. */ public function addAdjustment(Request $request, PayrollItem $item): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureItem($workspace->id, $item);
        $item->load('run');
        $this->ensureMutableRun($item->run);

        $data = $request->validate([
            'category' => ['required', Rule::in(['bonus', 'commission', 'deduction', 'tax', 'reimbursement', 'advance', 'adjustment'])],
            'direction' => ['required', Rule::in(['earning', 'deduction'])],
            'label' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:3000'],
        ]);
        $this->validateAdjustmentDirection($data);

        $adjustment = $item->adjustments()->create([
            'workspace_id' => $workspace->id, 'source' => 'manual', 'created_by' => $request->user()->id, ...$data,
        ]);
        $updated = $this->calculator->recalculateItemTotals($item);
        $this->logAction($request, $item->run, 'adjustment_added', $item->status, $item->status, $data['label'], ['item_id' => $item->id, 'adjustment_id' => $adjustment->id]);

        return response()->json(['data' => $updated]);
    }

    /** Removes remove adjustment data from the requested resource. */ public function removeAdjustment(Request $request, PayrollItem $item, PayrollAdjustment $adjustment): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureItem($workspace->id, $item);
        abort_unless($adjustment->payroll_item_id === $item->id && $adjustment->workspace_id === $workspace->id, 404);
        $item->load('run');
        $this->ensureMutableRun($item->run);
        $adjustment->delete();
        $updated = $this->calculator->recalculateItemTotals($item);
        $this->logAction($request, $item->run, 'adjustment_removed', $item->status, $item->status, null, ['item_id' => $item->id]);

        return response()->json(['data' => $updated]);
    }

    /** Handles the my payroll operation for the current WorkIntel workflow. */ public function myPayroll(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');
        abort_unless($member->hasPermission('payroll.view_own') || $member->hasPermission('payroll.view_all') || $member->hasPermission('payroll.manage'), 403);

        $items = PayrollItem::query()
            ->with(['run:id,name,period_start,period_end,pay_date,status,currency,run_type', 'adjustments', 'complianceLines', 'member.user:id,first_name,last_name,email', 'member.department:id,name', 'projects.project:id,name,code'])
            ->where('workspace_id', $workspace->id)->where('member_id', $member->id)
            ->whereHas('run', fn ($query) => $query->whereIn('status', ['approved', 'paid']))
            ->orderByDesc('id')->get();

        return response()->json(['data' => $items]);
    }

    /** Handles the load run operation for the current WorkIntel workflow. */ private function loadRun(PayrollRun $run): PayrollRun
    {
        return $run->load([
            'items' => fn ($query) => $query->orderBy('member_id'),
            'items.member.user:id,first_name,last_name,email', 'items.member.department:id,name', 'items.adjustments', 'items.projects.project:id,name,code', 'items.complianceLines', 'compliancePack', 'selectedMembers', 'exports',
            'actions' => fn ($query) => $query->with('user:id,first_name,last_name')->orderByDesc('occurred_at'),
        ]);
    }

    /** Validates validate pay rate input before it is processed. */ private function validatePayRate(array $data): void
    {
        $field = match ($data['pay_type']) {
            'hourly' => 'hourly_rate', 'daily' => 'daily_rate', 'monthly' => 'monthly_salary', 'yearly' => 'annual_salary', 'project' => 'project_rate',
        };
        if (! isset($data[$field]) || (float) $data[$field] <= 0) {
            throw ValidationException::withMessages([$field => ['A positive rate is required for the selected pay type.']]);
        }
    }

    /** Validates validate adjustment direction input before it is processed. */ private function validateAdjustmentDirection(array $data): void
    {
        $earningOnly = ['bonus', 'commission', 'reimbursement'];
        $deductionOnly = ['deduction', 'tax', 'advance'];
        if (in_array($data['category'], $earningOnly, true) && $data['direction'] !== 'earning') {
            throw ValidationException::withMessages(['direction' => ['This adjustment category must be an earning.']]);
        }
        if (in_array($data['category'], $deductionOnly, true) && $data['direction'] !== 'deduction') {
            throw ValidationException::withMessages(['direction' => ['This adjustment category must be a deduction.']]);
        }
    }

    /** Handles the ensure run operation for the current WorkIntel workflow. */ private function ensureRun(int $workspaceId, PayrollRun $run): void { abort_unless($run->workspace_id === $workspaceId, 404); }
    /** Handles the ensure item operation for the current WorkIntel workflow. */ private function ensureItem(int $workspaceId, PayrollItem $item): void { abort_unless($item->workspace_id === $workspaceId, 404); }
    /** Handles the ensure mutable run operation for the current WorkIntel workflow. */ private function ensureMutableRun(PayrollRun $run): void { abort_if($run->locked_at || in_array($run->status, ['approved', 'paid'], true), 422, 'Approved or paid payroll is immutable.'); }

    /** Handles the log action operation for the current WorkIntel workflow. */ private function logAction(Request $request, PayrollRun $run, string $action, ?string $from, ?string $to, ?string $note = null, array $metadata = []): void
    {
        PayrollAction::create([
            'payroll_run_id' => $run->id, 'workspace_id' => $run->workspace_id, 'user_id' => $request->user()?->id,
            'action' => $action, 'from_status' => $from, 'to_status' => $to, 'note' => $note,
            'metadata' => $metadata ?: null, 'occurred_at' => now(),
        ]);
    }
}
