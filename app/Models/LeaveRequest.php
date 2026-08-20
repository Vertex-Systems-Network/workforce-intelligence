<?php

namespace App\Models;

use App\Casts\DateOnly;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides leave request behavior within the WorkIntel application. */ class LeaveRequest extends Model
{
    protected $fillable = ['workspace_id', 'member_id', 'leave_type_id', 'start_date', 'end_date', 'days', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_note'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['start_date' => DateOnly::class, 'end_date' => DateOnly::class, 'days' => 'decimal:2', 'reviewed_at' => 'datetime']; }
    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
    /** Handles the leave type operation for the current WorkIntel workflow. */ public function leaveType(): BelongsTo { return $this->belongsTo(LeaveType::class); }
    /** Handles the reviewer operation for the current WorkIntel workflow. */ public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
