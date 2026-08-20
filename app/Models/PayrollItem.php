<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides payroll item behavior within the WorkIntel application. */ class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_run_id', 'workspace_id', 'member_id', 'compensation_profile_id', 'pay_type', 'currency', 'rate_snapshot',
        'tracked_seconds', 'regular_seconds', 'overtime_seconds', 'weekend_seconds', 'holiday_seconds', 'attendance_days',
        'unpaid_leave_days', 'project_units', 'base_pay', 'overtime_pay', 'weekend_pay', 'holiday_pay',
        'unpaid_leave_deduction', 'bonus_total', 'commission_total', 'reimbursement_total', 'deduction_total',
        'tax_total', 'statutory_deduction_total', 'employer_contribution_total', 'benefit_total', 'allowance_total', 'adjustment_total', 'gross_pay', 'net_pay', 'status', 'note',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return [
            'rate_snapshot' => 'array', 'attendance_days' => 'decimal:2', 'unpaid_leave_days' => 'decimal:2',
            'base_pay' => 'decimal:2', 'overtime_pay' => 'decimal:2', 'weekend_pay' => 'decimal:2', 'holiday_pay' => 'decimal:2',
            'unpaid_leave_deduction' => 'decimal:2', 'bonus_total' => 'decimal:2', 'commission_total' => 'decimal:2',
            'reimbursement_total' => 'decimal:2', 'deduction_total' => 'decimal:2', 'tax_total' => 'decimal:2',
            'statutory_deduction_total' => 'decimal:2', 'employer_contribution_total' => 'decimal:2', 'benefit_total' => 'decimal:2', 'allowance_total' => 'decimal:2',
            'adjustment_total' => 'decimal:2', 'gross_pay' => 'decimal:2', 'net_pay' => 'decimal:2',
        ];
    }

    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): BelongsTo { return $this->belongsTo(PayrollRun::class, 'payroll_run_id'); }
    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
    /** Handles the compensation profile operation for the current WorkIntel workflow. */ public function compensationProfile(): BelongsTo { return $this->belongsTo(CompensationProfile::class); }
    /** Handles the adjustments operation for the current WorkIntel workflow. */ public function adjustments(): HasMany { return $this->hasMany(PayrollAdjustment::class); }
    /** Handles the projects operation for the current WorkIntel workflow. */ public function projects(): HasMany { return $this->hasMany(PayrollItemProject::class); }
    /** Handles the compliance lines operation for the current WorkIntel workflow. */ public function complianceLines(): HasMany { return $this->hasMany(PayrollItemComplianceLine::class); }
}
