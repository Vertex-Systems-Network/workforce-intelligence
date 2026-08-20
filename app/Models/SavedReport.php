<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides saved report behavior within the WorkIntel application. */ class SavedReport extends Model
{
    protected $fillable = ['uuid', 'workspace_id', 'created_by', 'name', 'description', 'dataset', 'configuration', 'is_shared', 'last_run_at'];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return ['configuration' => 'array', 'is_shared' => 'boolean', 'last_run_at' => 'datetime'];
    }

    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the creator operation for the current WorkIntel workflow. */ public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    /** Handles the schedules operation for the current WorkIntel workflow. */ public function schedules(): HasMany { return $this->hasMany(ReportSchedule::class); }
    /** Handles the runs operation for the current WorkIntel workflow. */ public function runs(): HasMany { return $this->hasMany(ReportRun::class); }
}
