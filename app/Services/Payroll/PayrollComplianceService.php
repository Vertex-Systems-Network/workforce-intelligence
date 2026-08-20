<?php

namespace App\Services\Payroll;

use App\Models\MemberBenefit;
use App\Models\ContractorPaymentProfile;
use App\Models\MemberPayrollAssignment;
use App\Models\PayrollCompliancePack;
use App\Models\PayrollComplianceRule;
use App\Models\PayrollItem;
use App\Models\PayrollItemComplianceLine;
use App\Models\PayrollRun;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** Provides payroll compliance service behavior within the WorkIntel application. */ class PayrollComplianceService
{
    /** Handles the apply to item operation for the current WorkIntel workflow. */ public function applyToItem(PayrollItem $item, PayrollRun $run): array
    {
        $item->loadMissing(['member', 'adjustments']);
        $pack = $this->packFor($item, $run);
        $assignment = $this->assignmentFor($item->member_id, $run);

        PayrollItemComplianceLine::query()->where('payroll_item_id', $item->id)->delete();

        $lines = collect();
        if ($pack) {
            abort_unless(strtoupper($pack->currency) === strtoupper($item->currency), 422, 'Compliance pack and payroll item currency must match.');
            $pack->loadMissing('rules');
            foreach ($pack->rules->where('active', true) as $rule) {
                if (! $this->matchesConditions($rule, $assignment, $item)) continue;
                $basis = $this->basisFor($rule, $item);
                $employeeAmount = $this->calculateAmount($rule, $basis, false);
                $employerAmount = $this->calculateAmount($rule, $basis, true);
                if ($employeeAmount == 0.0 && $employerAmount == 0.0) continue;
                $lines->push(PayrollItemComplianceLine::create([
                    'payroll_item_id' => $item->id,
                    'workspace_id' => $item->workspace_id,
                    'payroll_compliance_rule_id' => $rule->id,
                    'code' => $rule->code,
                    'name' => $rule->name,
                    'category' => $rule->category,
                    'basis_amount' => round($basis, 2),
                    'employee_amount' => round($employeeAmount, 2),
                    'employer_amount' => round($employerAmount, 2),
                    'affects_gross' => $rule->affects_gross,
                    'taxable' => $rule->taxable,
                    'rule_snapshot' => $this->snapshot($rule, $pack),
                ]));
            }
        }

        $benefits = MemberBenefit::query()
            ->where('workspace_id', $item->workspace_id)->where('member_id', $item->member_id)->where('status', 'active')
            ->whereDate('effective_from', '<=', $run->period_end->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $run->period_start->toDateString()))
            ->orderBy('id')->get();

        foreach ($benefits as $benefit) {
            if (! $this->benefitApplies($benefit, $item, $run)) continue;
            $category = match ($benefit->type) {
                'allowance' => 'allowance',
                'deduction' => 'deduction',
                default => 'benefit',
            };
            $employee = (float) $benefit->employee_amount;
            $employer = (float) $benefit->employer_amount;
            if ($employee == 0.0 && $employer == 0.0) continue;
            $lines->push(PayrollItemComplianceLine::create([
                'payroll_item_id' => $item->id, 'workspace_id' => $item->workspace_id,
                'payroll_compliance_rule_id' => null, 'code' => 'BENEFIT-'.$benefit->id,
                'name' => $benefit->name, 'category' => $category, 'basis_amount' => 0,
                'employee_amount' => round($employee, 2), 'employer_amount' => round($employer, 2),
                'affects_gross' => $benefit->cash && in_array($category, ['allowance', 'benefit'], true),
                'taxable' => $benefit->taxable,
                'rule_snapshot' => ['source' => 'member_benefit', 'benefit_id' => $benefit->id, 'frequency' => $benefit->frequency, 'metadata' => $benefit->metadata],
            ]));
        }

        if (($assignment?->worker_classification ?? null) === 'contractor') {
            $profile = ContractorPaymentProfile::query()
                ->where('workspace_id', $item->workspace_id)->where('member_id', $item->member_id)->first();
            if ($profile?->withholding_enabled && (float) $profile->withholding_percent > 0) {
                $basis = max(0, (float) $item->base_pay + (float) $item->overtime_pay + (float) $item->weekend_pay + (float) $item->holiday_pay - (float) $item->unpaid_leave_deduction);
                $amount = round($basis * (float) $profile->withholding_percent / 100, 2);
                if ($amount > 0) {
                    $lines->push(PayrollItemComplianceLine::create([
                        'payroll_item_id' => $item->id, 'workspace_id' => $item->workspace_id,
                        'payroll_compliance_rule_id' => null, 'code' => 'CONTRACTOR-WITHHOLD',
                        'name' => 'Contractor withholding', 'category' => 'tax', 'basis_amount' => $basis,
                        'employee_amount' => $amount, 'employer_amount' => 0, 'affects_gross' => false, 'taxable' => false,
                        'rule_snapshot' => ['source' => 'contractor_payment_profile', 'withholding_percent' => (float) $profile->withholding_percent],
                    ]));
                }
            }
        }

        return [
            'pack_id' => $pack?->id,
            'replace_default_tax' => (bool) ($pack?->replace_default_tax ?? false),
            'line_count' => $lines->count(),
        ];
    }

    /** Handles the pack for operation for the current WorkIntel workflow. */ public function packFor(PayrollItem $item, PayrollRun $run): ?PayrollCompliancePack
    {
        if ($run->compliance_pack_id) {
            $pack = PayrollCompliancePack::query()->where('workspace_id', $run->workspace_id)->whereKey($run->compliance_pack_id)->first();
            abort_unless($pack && $pack->status === 'active', 422, 'The selected payroll compliance pack must be active.');
            abort_unless($this->packCoversRun($pack, $run), 422, 'The selected payroll compliance pack is not effective for this payroll period.');
            return $pack;
        }
        $assignment = $this->assignmentFor($item->member_id, $run);
        if (! $assignment?->pack) return null;
        abort_unless($assignment->pack->status === 'active' && $this->packCoversRun($assignment->pack, $run), 422, 'The assigned payroll compliance pack is not active/effective for this payroll period.');
        return $assignment->pack;
    }

    /** Handles the assignment for operation for the current WorkIntel workflow. */ public function assignmentFor(int $memberId, PayrollRun $run): ?MemberPayrollAssignment
    {
        return MemberPayrollAssignment::query()->with('pack')
            ->where('workspace_id', $run->workspace_id)->where('member_id', $memberId)->where('status', 'active')
            ->whereDate('effective_from', '<=', $run->period_end->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $run->period_start->toDateString()))
            ->orderByDesc('effective_from')->first();
    }

    /** Handles the pack covers run operation for the current WorkIntel workflow. */ private function packCoversRun(PayrollCompliancePack $pack, PayrollRun $run): bool
    {
        if ($pack->effective_from && $pack->effective_from->gt($run->period_end)) return false;
        if ($pack->effective_to && $pack->effective_to->lt($run->period_start)) return false;
        return true;
    }

    /** Handles the benefit applies operation for the current WorkIntel workflow. */ private function benefitApplies(MemberBenefit $benefit, PayrollItem $item, PayrollRun $run): bool
    {
        if ($benefit->frequency === 'payroll') return true;
        if ($benefit->frequency === 'one_time') return ! $this->benefitPreviouslyApplied($benefit, $item, $run);
        if ($benefit->frequency === 'monthly') {
            if ($run->run_type !== 'regular') return false;
            return ! $this->benefitPreviouslyApplied($benefit, $item, $run, $run->period_end->year, $run->period_end->month);
        }
        if ($benefit->frequency === 'annual') {
            if ($run->run_type !== 'regular') return false;
            return ! $this->benefitPreviouslyApplied($benefit, $item, $run, $run->period_end->year, null);
        }
        return false;
    }

    /** Handles the benefit previously applied operation for the current WorkIntel workflow. */ private function benefitPreviouslyApplied(MemberBenefit $benefit, PayrollItem $item, PayrollRun $run, ?int $year = null, ?int $month = null): bool
    {
        $query = PayrollItemComplianceLine::query()
            ->where('code', 'BENEFIT-'.$benefit->id)
            ->whereHas('item', function ($itemQuery) use ($item, $run, $year, $month) {
                $itemQuery->where('member_id', $item->member_id)->where('payroll_run_id', '!=', $run->id)
                    ->whereHas('run', function ($runQuery) use ($year, $month) {
                        $runQuery->whereNotIn('status', ['void']);
                        if ($year !== null) $runQuery->whereYear('period_end', $year);
                        if ($month !== null) $runQuery->whereMonth('period_end', $month);
                    });
            });
        return $query->exists();
    }

    /** Handles the basis for operation for the current WorkIntel workflow. */ private function basisFor(PayrollComplianceRule $rule, PayrollItem $item): float
    {
        $base = (float) $item->base_pay;
        $gross = max(0, $base + (float) $item->overtime_pay + (float) $item->weekend_pay + (float) $item->holiday_pay - (float) $item->unpaid_leave_deduction
            + (float) $item->bonus_total + (float) $item->commission_total + (float) $item->adjustment_total);
        $basis = match ($rule->basis) {
            'base' => $base,
            'fixed' => 0,
            'taxable_gross' => $gross,
            default => $gross,
        };
        if ($rule->minimum_basis !== null && $basis < (float) $rule->minimum_basis) return 0;
        if ($rule->maximum_basis !== null) $basis = min($basis, (float) $rule->maximum_basis);
        return max(0, $basis);
    }

    /** Handles the calculate amount operation for the current WorkIntel workflow. */ private function calculateAmount(PayrollComplianceRule $rule, float $basis, bool $employer): float
    {
        $amount = 0.0;
        if ($rule->calculation_type === 'fixed') {
            $amount = (float) ($employer ? $rule->employer_fixed_amount : $rule->fixed_amount);
        } elseif ($rule->calculation_type === 'brackets' && ! $employer) {
            $amount = $this->bracketAmount($basis, collect($rule->brackets ?? []));
        } else {
            $rate = (float) ($employer ? $rule->employer_rate_percent : $rule->rate_percent);
            $amount = $basis * max(0, $rate) / 100;
        }
        $cap = $employer ? $rule->employer_cap : $rule->employee_cap;
        if ($cap !== null) $amount = min($amount, (float) $cap);
        return max(0, round($amount, 2));
    }

    /** Handles the bracket amount operation for the current WorkIntel workflow. */ private function bracketAmount(float $basis, Collection $brackets): float
    {
        $remaining = $basis; $previous = 0.0; $total = 0.0;
        foreach ($brackets->sortBy(fn ($row) => $row['up_to'] ?? PHP_FLOAT_MAX) as $row) {
            $upper = isset($row['up_to']) && $row['up_to'] !== null ? (float) $row['up_to'] : PHP_FLOAT_MAX;
            $rate = max(0, (float) ($row['rate_percent'] ?? 0));
            $band = max(0, min($remaining, $upper - $previous));
            $total += $band * $rate / 100;
            $remaining -= $band;
            if ($remaining <= 0) break;
            $previous = $upper;
        }
        return round($total, 2);
    }

    /** Handles the matches conditions operation for the current WorkIntel workflow. */ private function matchesConditions(PayrollComplianceRule $rule, ?MemberPayrollAssignment $assignment, PayrollItem $item): bool
    {
        foreach ($rule->conditions ?? [] as $key => $expected) {
            $actual = match ($key) {
                'worker_classification' => $assignment?->worker_classification ?? 'employee',
                'residency_status' => $assignment?->residency_status,
                'pay_type' => $item->pay_type,
                default => null,
            };
            if (is_array($expected) ? ! in_array($actual, $expected, true) : $actual !== $expected) return false;
        }
        return true;
    }

    /** Handles the snapshot operation for the current WorkIntel workflow. */ private function snapshot(PayrollComplianceRule $rule, PayrollCompliancePack $pack): array
    {
        return [
            'pack' => ['id' => $pack->id, 'name' => $pack->name, 'version' => $pack->version, 'country_code' => $pack->country_code],
            'rule' => $rule->only(['id','code','name','category','calculation_type','basis','rate_percent','employer_rate_percent','fixed_amount','employer_fixed_amount','minimum_basis','maximum_basis','employee_cap','employer_cap','taxable','affects_gross','brackets','conditions','priority']),
            'calculated_at' => now()->toIso8601String(),
        ];
    }
}
