<?php

namespace App\Models;

use App\Casts\DateOnly;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides attendance record behavior within the WorkIntel application. */ class AttendanceRecord extends Model
{
    protected $fillable = ['workspace_id', 'member_id', 'shift_assignment_id', 'date', 'clock_in_at', 'clock_out_at', 'break_seconds', 'worked_seconds', 'late_minutes', 'overtime_minutes', 'status', 'source', 'flag_type', 'flagged_at', 'note'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['date' => DateOnly::class, 'clock_in_at' => 'datetime', 'clock_out_at' => 'datetime', 'flagged_at' => 'datetime']; }
    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
    /** Handles the shift assignment operation for the current WorkIntel workflow. */ public function shiftAssignment(): BelongsTo { return $this->belongsTo(ShiftAssignment::class); }
    /** Handles the breaks operation for the current WorkIntel workflow. */ public function breaks(): HasMany { return $this->hasMany(AttendanceBreak::class); }
}
