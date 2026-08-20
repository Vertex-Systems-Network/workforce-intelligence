<?php

namespace App\Models;

use App\Casts\DateOnly;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides timesheet period behavior within the WorkIntel application. */ class TimesheetPeriod extends Model
{
    protected $fillable = ['workspace_id', 'member_id', 'week_start', 'week_end', 'status', 'submitted_at', 'locked_by', 'locked_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['week_start' => DateOnly::class, 'week_end' => DateOnly::class, 'submitted_at' => 'datetime', 'locked_at' => 'datetime']; }
    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
    /** Handles the actions operation for the current WorkIntel workflow. */ public function actions(): HasMany { return $this->hasMany(TimesheetAction::class); }
}
