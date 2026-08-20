<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides timesheet action behavior within the WorkIntel application. */ class TimesheetAction extends Model
{
    public $timestamps = false;
    protected $fillable = ['workspace_id', 'timesheet_period_id', 'time_entry_id', 'member_id', 'actor_user_id', 'action', 'previous_status', 'new_status', 'note', 'metadata', 'created_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['metadata' => 'array', 'created_at' => 'datetime']; }
    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
    /** Handles the actor operation for the current WorkIntel workflow. */ public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }
    /** Handles the period operation for the current WorkIntel workflow. */ public function period(): BelongsTo { return $this->belongsTo(TimesheetPeriod::class, 'timesheet_period_id'); }
    /** Handles the entry operation for the current WorkIntel workflow. */ public function entry(): BelongsTo { return $this->belongsTo(TimeEntry::class, 'time_entry_id'); }
}
