<?php

namespace App\Models;

use App\Casts\DateOnly;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides shift assignment behavior within the WorkIntel application. */ class ShiftAssignment extends Model
{
    protected $fillable = ['workspace_id', 'shift_id', 'project_id', 'member_id', 'date', 'work_mode', 'status', 'published_at', 'published_by'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['date' => DateOnly::class, 'published_at' => 'datetime']; }
    /** Handles the shift operation for the current WorkIntel workflow. */ public function shift(): BelongsTo { return $this->belongsTo(Shift::class); }
    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
    /** Handles the project operation for the current WorkIntel workflow. */ public function project(): BelongsTo { return $this->belongsTo(Project::class); }
}
