<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides leave policy behavior within the WorkIntel application. */ class LeavePolicy extends Model
{
    protected $fillable = ['workspace_id', 'leave_type_id', 'accrual_method', 'monthly_accrual_days', 'carryover_days', 'min_notice_days', 'max_consecutive_days', 'probation_months', 'allow_negative_balance', 'requires_approval', 'exclude_weekends', 'exclude_holidays'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['monthly_accrual_days' => 'decimal:2', 'carryover_days' => 'decimal:2', 'allow_negative_balance' => 'boolean', 'requires_approval' => 'boolean', 'exclude_weekends' => 'boolean', 'exclude_holidays' => 'boolean']; }
    /** Handles the leave type operation for the current WorkIntel workflow. */ public function leaveType(): BelongsTo { return $this->belongsTo(LeaveType::class); }
}
