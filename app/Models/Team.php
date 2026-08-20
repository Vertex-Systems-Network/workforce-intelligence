<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Provides team behavior within the WorkIntel application. */ class Team extends Model
{
    protected $fillable = ['workspace_id', 'department_id', 'lead_id', 'name', 'code', 'description', 'status'];

    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the department operation for the current WorkIntel workflow. */ public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    /** Handles the lead operation for the current WorkIntel workflow. */ public function lead(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'lead_id'); }
    /** Handles the members operation for the current WorkIntel workflow. */ public function members(): BelongsToMany
    {
        return $this->belongsToMany(WorkspaceMember::class, 'team_members', 'team_id', 'member_id')
            ->withPivot('role')->withTimestamps();
    }
}
