<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides leave balance behavior within the WorkIntel application. */ class LeaveBalance extends Model
{
    protected $fillable = ['workspace_id', 'member_id', 'leave_type_id', 'year', 'opening_days', 'carried_days', 'accrued_days', 'adjustment_days', 'used_days'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['opening_days' => 'decimal:2', 'carried_days' => 'decimal:2', 'accrued_days' => 'decimal:2', 'adjustment_days' => 'decimal:2', 'used_days' => 'decimal:2']; }
    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
    /** Handles the leave type operation for the current WorkIntel workflow. */ public function leaveType(): BelongsTo { return $this->belongsTo(LeaveType::class); }
}
