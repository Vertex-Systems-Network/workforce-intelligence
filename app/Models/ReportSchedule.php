<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides report schedule behavior within the WorkIntel application. */ class ReportSchedule extends Model
{
    protected $fillable = ['uuid', 'workspace_id', 'saved_report_id', 'created_by', 'name', 'frequency', 'time_of_day', 'day_of_week', 'day_of_month', 'timezone', 'export_format', 'active', 'last_run_at', 'next_run_at'];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return ['active' => 'boolean', 'last_run_at' => 'datetime', 'next_run_at' => 'datetime'];
    }

    /** Handles the saved report operation for the current WorkIntel workflow. */ public function savedReport(): BelongsTo { return $this->belongsTo(SavedReport::class); }
    /** Handles the creator operation for the current WorkIntel workflow. */ public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    /** Handles the runs operation for the current WorkIntel workflow. */ public function runs(): HasMany { return $this->hasMany(ReportRun::class); }
}
