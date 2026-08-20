<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\WorkspaceMember;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/** Provides leave balance service behavior within the WorkIntel application. */ class LeaveBalanceService
{
    /** Handles the policy for operation for the current WorkIntel workflow. */ public function policyFor(LeaveType $type): LeavePolicy
    {
        return $type->policy ?: LeavePolicy::create([
            'workspace_id' => $type->workspace_id,
            'leave_type_id' => $type->id,
            'accrual_method' => 'annual',
            'monthly_accrual_days' => 0,
            'carryover_days' => 0,
            'min_notice_days' => 0,
            'probation_months' => 0,
            'allow_negative_balance' => false,
            'requires_approval' => true,
            'exclude_weekends' => true,
            'exclude_holidays' => true,
        ]);
    }

    /** Handles the calculate request days operation for the current WorkIntel workflow. */ public function calculateRequestDays(LeaveType $type, Carbon $start, Carbon $end): float
    {
        $policy = $this->policyFor($type);
        $holidayDates = $policy->exclude_holidays
            ? Holiday::query()->where('workspace_id', $type->workspace_id)->where('status', 'active')
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->pluck('date')
                ->map(fn ($date) => Carbon::parse($date)->toDateString())->all()
            : [];

        $days = 0;
        foreach (CarbonPeriod::create($start, $end) as $day) {
            if ($policy->exclude_weekends && $day->isWeekend()) continue;
            if ($policy->exclude_holidays && in_array($day->toDateString(), $holidayDates, true)) continue;
            $days++;
        }

        return (float) $days;
    }

    /** Handles the balance for operation for the current WorkIntel workflow. */ public function balanceFor(WorkspaceMember $member, LeaveType $type, int $year): array
    {
        $policy = $this->policyFor($type);
        $balance = LeaveBalance::firstOrCreate([
            'workspace_id' => $type->workspace_id,
            'member_id' => $member->id,
            'leave_type_id' => $type->id,
            'year' => $year,
        ]);

        $accrued = $this->accruedDays($type, $policy, $year);
        $used = (float) LeaveRequest::query()
            ->where('workspace_id', $type->workspace_id)
            ->where('member_id', $member->id)
            ->where('leave_type_id', $type->id)
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->sum('days');

        $carried = (float) $balance->carried_days;
        if ($year > 2000 && $carried == 0.0 && (float) $policy->carryover_days > 0) {
            $previous = LeaveBalance::query()
                ->where('workspace_id', $type->workspace_id)
                ->where('member_id', $member->id)
                ->where('leave_type_id', $type->id)
                ->where('year', $year - 1)
                ->first();
            if ($previous) {
                $previousAvailable = (float) $previous->opening_days + (float) $previous->carried_days + (float) $previous->accrued_days + (float) $previous->adjustment_days - (float) $previous->used_days;
                $carried = max(0, min((float) $policy->carryover_days, $previousAvailable));
            }
        }

        $balance->update(['accrued_days' => $accrued, 'used_days' => $used, 'carried_days' => $carried]);
        $available = (float) $balance->opening_days + $carried + $accrued + (float) $balance->adjustment_days - $used;

        return [
            'leave_type_id' => $type->id,
            'name' => $type->name,
            'year' => $year,
            'allowance' => (float) $type->annual_allowance_days,
            'opening' => (float) $balance->opening_days,
            'carried' => $carried,
            'accrued' => $accrued,
            'adjustment' => (float) $balance->adjustment_days,
            'used' => $used,
            'remaining' => $available,
            'policy' => $policy,
        ];
    }

    /** Handles the adjust operation for the current WorkIntel workflow. */ public function adjust(WorkspaceMember $member, LeaveType $type, int $year, float $days): array
    {
        $balance = LeaveBalance::firstOrCreate([
            'workspace_id' => $type->workspace_id,
            'member_id' => $member->id,
            'leave_type_id' => $type->id,
            'year' => $year,
        ]);
        $balance->increment('adjustment_days', $days);
        return $this->balanceFor($member, $type, $year);
    }

    /** Handles the accrued days operation for the current WorkIntel workflow. */ private function accruedDays(LeaveType $type, LeavePolicy $policy, int $year): float
    {
        if ($policy->accrual_method === 'none') return 0;
        if ($policy->accrual_method === 'annual') return (float) $type->annual_allowance_days;

        $months = $year === now()->year ? now()->month : ($year < now()->year ? 12 : 0);
        return min((float) $type->annual_allowance_days, $months * (float) $policy->monthly_accrual_days);
    }
}
