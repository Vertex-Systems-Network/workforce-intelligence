<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides attendance break behavior within the WorkIntel application. */ class AttendanceBreak extends Model
{
    protected $fillable = ['workspace_id', 'attendance_record_id', 'member_id', 'type', 'paid', 'started_at', 'ended_at', 'duration_seconds', 'note'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['paid' => 'boolean', 'started_at' => 'datetime', 'ended_at' => 'datetime']; }
    /** Handles the attendance record operation for the current WorkIntel workflow. */ public function attendanceRecord(): BelongsTo { return $this->belongsTo(AttendanceRecord::class); }
    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
}
