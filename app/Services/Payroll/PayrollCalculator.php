<?php

namespace App\Services\Payroll;

use App\Models\AttendanceRecord;
use App\Models\CompensationProfile;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\WorkspaceMember;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Provides payroll calculator behavior within the WorkIntel application. */ class PayrollCalculator
{
    /** Handles the calculate operation for the current WorkIntel workflow. */ public function calculate(PayrollRun $run): array
    {
        abort_if($run->locked_at || in_array($run->status, ['approved', 'paid'], true), 422, 'Approved or paid payroll runs cannot be recalculated.');

        $selectedMemberIds = $run->selectedMembers()->pluck('member_id')->all();
        $members = WorkspaceMember::query()
            ->with(['user:id,first_name,last_name', 'department:id,name'])
            ->where('workspace_id', $run->workspace_id)
            ->where('status', 'active')
            ->when($selectedMemberIds, fn ($query) => $query->whereIn('id', $selectedMemberIds))
            ->orderBy('id')
            ->get();

        $missing = [];
        $calculatedMemberIds = [];
        DB::transaction(function () use ($run, $members, &$missing, &$calculatedMemberIds) {
            foreach ($members as $member) {
                $profile = $this->profileForPeriod($member->id, $run);
                if (! $profile) {
                    $missing[] = $member->id;
                    continue;
                }

                if (strtoupper($profile->currency) !== strtoupper($run->currency)) {
                    $missing[] = $member->id;
                    continue;
                }

                $calculatedMemberIds[] = $member->id;
                $calculation = $this->calculateMember($member, $profile, $run);
                $item = PayrollItem::updateOrCreate(
                    ['payroll_run_id' => $run->id, 'member_id' => $member->id],
                    [
                        'workspace_id' => $run->workspace_id,
                        'compensation_profile_id' => $profile->id,
                        'pay_type' => $profile->pay_type,
                        'currency' => $run->currency,
                        'rate_snapshot' => $this->rateSnapshot($profile),
                        ...$calculation['fields'],
                        'status' => 'calculated',
                    ]
                );

                $item->projects()->delete();
                foreach ($calculation['projects'] as $project) {
                    $item->projects()->create([
                        'workspace_id' => $run->workspace_id,
                        'member_id' => $member->id,
                        'project_id' => $project['id'],
                        'amount' => $project['amount'],
                    ]);
                }

                // First establish the ordinary gross basis, then snapshot compliance
                // lines, then recalculate final statutory/net totals.
                $this->recalculateItemTotals($item->fresh());
                app(PayrollComplianceService::class)->applyToItem($item->fresh(), $run);
                $this->recalculateItemTotals($item->fresh());
            }

            PayrollItem::query()
                ->where('payroll_run_id', $run->id)
                ->when($calculatedMemberIds, fn ($query) => $query->whereNotIn('member_id', $calculatedMemberIds))
                ->when(! $calculatedMemberIds, fn ($query) => $query)
                ->delete();

            $run->update([
                'status' => 'calculated',
                'calculated_at' => now(),
                'submitted_at' => null,
                'submitted_by' => null,
            ]);
        });

        return ['missing_member_ids' => $missing];
    }

    /** Handles the recalculate item totals operation for the current WorkIntel workflow. */ public function recalculateItemTotals(PayrollItem $item): PayrollItem
    {
        $item->loadMissing(['adjustments', 'compensationProfile']);
        $item->loadMissing('complianceLines');
        $adjustments = $item->adjustments;
        $compliance = $item->complianceLines;

        $bonus = $this->sumCategory($adjustments, 'bonus', 'earning');
        $commission = $this->sumCategory($adjustments, 'commission', 'earning');
        $reimbursement = $this->sumCategory($adjustments, 'reimbursement', 'earning');
        $deduction = $this->sumCategories($adjustments, ['deduction', 'advance'], 'deduction');
        $manualTax = $this->sumCategory($adjustments, 'tax', 'deduction');
        $adjustmentEarnings = $this->sumCategory($adjustments, 'adjustment', 'earning');
        $adjustmentDeductions = $this->sumCategory($adjustments, 'adjustment', 'deduction');
        $adjustmentTotal = $adjustmentEarnings - $adjustmentDeductions;

        $allowance = (float) $compliance->where('category', 'allowance')->where('affects_gross', true)->sum('employee_amount');
        $cashBenefit = (float) $compliance->where('category', 'benefit')->where('affects_gross', true)->sum('employee_amount');
        $benefitTotal = (float) $compliance->where('category', 'benefit')->sum('employee_amount');
        $statutoryDeduction = (float) $compliance->where('category', 'statutory_deduction')->sum('employee_amount');
        $complianceDeduction = (float) $compliance->where('category', 'deduction')->sum('employee_amount');
        $complianceTax = (float) $compliance->where('category', 'tax')->sum('employee_amount');
        $employerContribution = (float) $compliance->sum('employer_amount');

        $grossBeforeTax = max(0, (float) $item->base_pay
            + (float) $item->overtime_pay
            + (float) $item->weekend_pay
            + (float) $item->holiday_pay
            - (float) $item->unpaid_leave_deduction
            + $bonus + $commission + $adjustmentTotal + $allowance + $cashBenefit);

        $packId = $item->run?->compliance_pack_id ?? null;
        $replaceDefaultTax = false;
        if ($packId) $replaceDefaultTax = (bool) \App\Models\PayrollCompliancePack::query()->whereKey($packId)->value('replace_default_tax');
        elseif ($compliance->isNotEmpty()) {
            $packIds = $compliance->pluck('rule_snapshot')->filter()->pluck('pack.id')->filter()->unique();
            if ($packIds->count() === 1) $replaceDefaultTax = (bool) \App\Models\PayrollCompliancePack::query()->whereKey($packIds->first())->value('replace_default_tax');
        }
        $taxPercent = (float) ($item->rate_snapshot['default_tax_percent'] ?? 0);
        $automaticTax = $replaceDefaultTax ? 0 : round($grossBeforeTax * max(0, $taxPercent) / 100, 2);
        $tax = $automaticTax + $manualTax + $complianceTax;
        $net = $grossBeforeTax + $reimbursement - $deduction - $statutoryDeduction - $complianceDeduction - $tax;

        $item->update([
            'bonus_total' => round($bonus, 2),
            'commission_total' => round($commission, 2),
            'reimbursement_total' => round($reimbursement, 2),
            'deduction_total' => round($deduction + $complianceDeduction, 2),
            'statutory_deduction_total' => round($statutoryDeduction, 2),
            'employer_contribution_total' => round($employerContribution, 2),
            'benefit_total' => round($benefitTotal, 2),
            'allowance_total' => round($allowance, 2),
            'tax_total' => round($tax, 2),
            'adjustment_total' => round($adjustmentTotal, 2),
            'gross_pay' => round($grossBeforeTax, 2),
            'net_pay' => round($net, 2),
        ]);

        return $item->fresh(['adjustments', 'projects.project', 'complianceLines']);
    }

    /** Handles the calculate member operation for the current WorkIntel workflow. */ private function calculateMember(WorkspaceMember $member, CompensationProfile $profile, PayrollRun $run): array
    {
        $entries = TimeEntry::query()
            ->where('workspace_id', $run->workspace_id)
            ->where('member_id', $member->id)
            ->whereBetween('date', [$run->period_start->toDateString(), $run->period_end->toDateString()])
            ->where('approval_status', 'approved')
            ->get();

        $attendance = AttendanceRecord::query()
            ->where('workspace_id', $run->workspace_id)
            ->where('member_id', $member->id)
            ->whereBetween('date', [$run->period_start->toDateString(), $run->period_end->toDateString()])
            ->get()->keyBy(fn (AttendanceRecord $record) => $record->date->toDateString());

        $holidayDates = Holiday::query()
            ->where('workspace_id', $run->workspace_id)
            ->where('status', 'active')
            ->whereBetween('date', [$run->period_start->toDateString(), $run->period_end->toDateString()])
            ->pluck('date')->map(fn ($date) => Carbon::parse($date)->toDateString())->flip();

        $secondsByDate = $entries->groupBy(fn (TimeEntry $entry) => $entry->date->toDateString())
            ->map(fn (Collection $rows) => (int) $rows->sum('duration_seconds'));

        $trackedSeconds = (int) $secondsByDate->sum();
        $regularSeconds = 0;
        $overtimeSeconds = 0;
        $weekendSeconds = 0;
        $holidaySeconds = 0;

        $dates = $secondsByDate->keys()->merge($attendance->keys())->unique()->sort();
        foreach ($dates as $date) {
            $day = Carbon::parse($date);
            $trackedForDay = (int) ($secondsByDate->get($date) ?? 0);
            $attendanceRecord = $attendance->get($date);
            $attendanceWorked = (int) ($attendanceRecord?->worked_seconds ?? 0);
            $premiumSourceSeconds = $profile->pay_type === 'hourly'
                ? $trackedForDay
                : ($attendanceWorked > 0 ? $attendanceWorked : $trackedForDay);

            if ($holidayDates->has($date)) {
                $holidaySeconds += $premiumSourceSeconds;
                continue;
            }
            if ($day->isWeekend()) {
                $weekendSeconds += $premiumSourceSeconds;
                continue;
            }

            $attendanceOvertime = ((int) ($attendanceRecord?->overtime_minutes ?? 0)) * 60;
            $dayOvertime = $profile->pay_type === 'hourly'
                ? min($trackedForDay, max(0, $attendanceOvertime))
                : max(0, $attendanceOvertime);
            $overtimeSeconds += $dayOvertime;
            $regularSeconds += max(0, $trackedForDay - min($trackedForDay, $dayOvertime));
        }

        $attendanceDays = $attendance->filter(fn (AttendanceRecord $record) => $record->clock_in_at !== null)->count();
        $unpaidLeaveDays = $this->unpaidLeaveDays($member->id, $run);
        $basePay = $this->basePay($profile, $run, $trackedSeconds, $attendanceDays);
        $hourlyEquivalent = $this->hourlyEquivalent($profile);

        $overtimePay = ($overtimeSeconds / 3600) * $hourlyEquivalent * max(0, (float) $profile->overtime_multiplier - 1);
        $weekendPay = ($weekendSeconds / 3600) * $hourlyEquivalent * max(0, (float) $profile->weekend_multiplier - 1);
        $holidayPay = ($holidaySeconds / 3600) * $hourlyEquivalent * max(0, (float) $profile->holiday_multiplier - 1);
        $unpaidLeaveDeduction = $profile->deduct_unpaid_leave ? $this->unpaidLeaveDeduction($profile, $run, $unpaidLeaveDays) : 0;

        $projects = $profile->pay_type === 'project' ? $this->eligibleProjectEarnings($member, $profile, $run) : [];
        if ($profile->pay_type === 'project') {
            $basePay = collect($projects)->sum('amount');
        }

        return [
            'fields' => [
                'tracked_seconds' => $trackedSeconds,
                'regular_seconds' => $regularSeconds,
                'overtime_seconds' => $overtimeSeconds,
                'weekend_seconds' => $weekendSeconds,
                'holiday_seconds' => $holidaySeconds,
                'attendance_days' => $attendanceDays,
                'unpaid_leave_days' => round($unpaidLeaveDays, 2),
                'project_units' => count($projects),
                'base_pay' => round($basePay, 2),
                'overtime_pay' => round($overtimePay, 2),
                'weekend_pay' => round($weekendPay, 2),
                'holiday_pay' => round($holidayPay, 2),
                'unpaid_leave_deduction' => round($unpaidLeaveDeduction, 2),
            ],
            'projects' => $projects,
        ];
    }

    /** Handles the profile for period operation for the current WorkIntel workflow. */ private function profileForPeriod(int $memberId, PayrollRun $run): ?CompensationProfile
    {
        return CompensationProfile::query()
            ->where('workspace_id', $run->workspace_id)
            ->where('member_id', $memberId)
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $run->period_end)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $run->period_start))
            ->orderByDesc('effective_from')
            ->first();
    }

    /** Handles the base pay operation for the current WorkIntel workflow. */ private function basePay(CompensationProfile $profile, PayrollRun $run, int $trackedSeconds, int $attendanceDays): float
    {
        return match ($profile->pay_type) {
            'hourly' => ($trackedSeconds / 3600) * (float) $profile->hourly_rate,
            'daily' => $attendanceDays * (float) $profile->daily_rate,
            'monthly' => $this->monthlyProratedPay($profile, $run),
            'yearly' => $this->yearlyProratedPay($profile, $run),
            'project' => 0,
            default => 0,
        };
    }

    /** Handles the monthly prorated pay operation for the current WorkIntel workflow. */ private function monthlyProratedPay(CompensationProfile $profile, PayrollRun $run): float
    {
        if ($profile->proration_mode === 'none') return (float) $profile->monthly_salary;
        $total = 0.0;
        foreach (CarbonPeriod::create($run->period_start, $run->period_end) as $date) {
            $total += (float) $profile->monthly_salary / $date->daysInMonth;
        }
        return $total;
    }

    /** Handles the yearly prorated pay operation for the current WorkIntel workflow. */ private function yearlyProratedPay(CompensationProfile $profile, PayrollRun $run): float
    {
        if ($profile->proration_mode === 'none') return (float) $profile->annual_salary / 12;
        $total = 0.0;
        foreach (CarbonPeriod::create($run->period_start, $run->period_end) as $date) {
            $total += (float) $profile->annual_salary / ($date->isLeapYear() ? 366 : 365);
        }
        return $total;
    }

    /** Handles the hourly equivalent operation for the current WorkIntel workflow. */ private function hourlyEquivalent(CompensationProfile $profile): float
    {
        if ((float) $profile->premium_hourly_rate > 0) return (float) $profile->premium_hourly_rate;
        $hoursPerDay = max(1, (float) $profile->standard_hours_per_day);
        $hoursPerWeek = max(1, (float) $profile->standard_hours_per_week);

        return match ($profile->pay_type) {
            'hourly' => (float) $profile->hourly_rate,
            'daily' => (float) $profile->daily_rate / $hoursPerDay,
            'monthly' => (float) $profile->monthly_salary / ($hoursPerWeek * 52 / 12),
            'yearly' => (float) $profile->annual_salary / ($hoursPerWeek * 52),
            default => 0,
        };
    }

    /** Handles the unpaid leave days operation for the current WorkIntel workflow. */ private function unpaidLeaveDays(int $memberId, PayrollRun $run): float
    {
        $requests = LeaveRequest::query()
            ->with(['leaveType.policy'])
            ->where('workspace_id', $run->workspace_id)
            ->where('member_id', $memberId)
            ->where('status', 'approved')
            ->whereHas('leaveType', fn ($query) => $query->where('is_paid', false))
            ->whereDate('start_date', '<=', $run->period_end)
            ->whereDate('end_date', '>=', $run->period_start)
            ->get();

        $holidays = Holiday::query()->where('workspace_id', $run->workspace_id)->where('status', 'active')
            ->whereBetween('date', [$run->period_start->toDateString(), $run->period_end->toDateString()])
            ->pluck('date')->map(fn ($date) => Carbon::parse($date)->toDateString())->flip();

        $days = 0.0;
        foreach ($requests as $request) {
            $start = Carbon::parse($request->start_date)->max($run->period_start);
            $end = Carbon::parse($request->end_date)->min($run->period_end);
            $policy = $request->leaveType?->policy;
            foreach (CarbonPeriod::create($start, $end) as $date) {
                if (($policy?->exclude_weekends ?? true) && $date->isWeekend()) continue;
                if (($policy?->exclude_holidays ?? true) && $holidays->has($date->toDateString())) continue;
                $days += 1;
            }
        }
        return $days;
    }

    /** Handles the unpaid leave deduction operation for the current WorkIntel workflow. */ private function unpaidLeaveDeduction(CompensationProfile $profile, PayrollRun $run, float $days): float
    {
        if ($days <= 0) return 0;
        return match ($profile->pay_type) {
            'daily' => $days * (float) $profile->daily_rate,
            'monthly' => $this->calendarDayDeduction((float) $profile->monthly_salary, $run, $days),
            'yearly' => $days * ((float) $profile->annual_salary / 365),
            default => 0,
        };
    }

    /** Handles the calendar day deduction operation for the current WorkIntel workflow. */ private function calendarDayDeduction(float $monthlySalary, PayrollRun $run, float $days): float
    {
        $representative = $run->period_start;
        return $days * ($monthlySalary / $representative->daysInMonth);
    }

    /** Handles the eligible project earnings operation for the current WorkIntel workflow. */ private function eligibleProjectEarnings(WorkspaceMember $member, CompensationProfile $profile, PayrollRun $run): array
    {
        $alreadyPaidProjectIds = DB::table('payroll_item_projects as pip')
            ->join('payroll_items as pi', 'pi.id', '=', 'pip.payroll_item_id')
            ->join('payroll_runs as pr', 'pr.id', '=', 'pi.payroll_run_id')
            ->where('pip.workspace_id', $run->workspace_id)
            ->where('pip.member_id', $member->id)
            ->whereIn('pr.status', ['approved', 'paid'])
            ->pluck('pip.project_id');

        return Project::query()
            ->where('workspace_id', $run->workspace_id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$run->period_start->copy()->startOfDay(), $run->period_end->copy()->endOfDay()])
            ->whereHas('members', fn ($query) => $query->where('workspace_members.id', $member->id))
            ->when($alreadyPaidProjectIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $alreadyPaidProjectIds))
            ->orderBy('completed_at')
            ->get(['id', 'name', 'code'])
            ->map(fn (Project $project) => ['id' => $project->id, 'name' => $project->name, 'code' => $project->code, 'amount' => (float) $profile->project_rate])
            ->all();
    }

    /** Handles the rate snapshot operation for the current WorkIntel workflow. */ private function rateSnapshot(CompensationProfile $profile): array
    {
        return collect($profile->only([
            'pay_type', 'currency', 'hourly_rate', 'daily_rate', 'monthly_salary', 'annual_salary', 'project_rate',
            'premium_hourly_rate', 'standard_hours_per_day', 'standard_hours_per_week', 'overtime_multiplier',
            'weekend_multiplier', 'holiday_multiplier', 'default_tax_percent', 'deduct_unpaid_leave', 'proration_mode',
            'effective_from', 'effective_to',
        ]))->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value)->all();
    }

    /** Handles the sum category operation for the current WorkIntel workflow. */ private function sumCategory(Collection $adjustments, string $category, string $direction): float
    {
        return (float) $adjustments->where('category', $category)->where('direction', $direction)->sum('amount');
    }

    /** Handles the sum categories operation for the current WorkIntel workflow. */ private function sumCategories(Collection $adjustments, array $categories, string $direction): float
    {
        return (float) $adjustments->whereIn('category', $categories)->where('direction', $direction)->sum('amount');
    }
}
