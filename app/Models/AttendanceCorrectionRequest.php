<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides attendance correction request behavior within the WorkIntel application. */ class AttendanceCorrectionRequest extends Model
{
    protected $fillable=['uuid','workspace_id','member_id','attendance_record_id','date','requested_clock_in_at','requested_clock_out_at','reason','status','reviewed_by','reviewed_at','review_note'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['date'=>DateOnly::class,'requested_clock_in_at'=>'datetime','requested_clock_out_at'=>'datetime','reviewed_at'=>'datetime']; }
    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class,'member_id'); }
    /** Handles the attendance record operation for the current WorkIntel workflow. */ public function attendanceRecord(): BelongsTo { return $this->belongsTo(AttendanceRecord::class); }
    /** Handles the reviewer operation for the current WorkIntel workflow. */ public function reviewer(): BelongsTo { return $this->belongsTo(User::class,'reviewed_by'); }
}
