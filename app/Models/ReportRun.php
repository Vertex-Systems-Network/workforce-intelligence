<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides report run behavior within the WorkIntel application. */ class ReportRun extends Model
{
    protected $fillable = ['uuid', 'workspace_id', 'saved_report_id', 'report_schedule_id', 'requested_by', 'name', 'dataset', 'configuration', 'status', 'row_count', 'columns', 'result_rows', 'summary', 'started_at', 'completed_at', 'error_message'];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return ['configuration' => 'array', 'columns' => 'array', 'summary' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    /** Handles the saved report operation for the current WorkIntel workflow. */ public function savedReport(): BelongsTo { return $this->belongsTo(SavedReport::class); }
    /** Handles the schedule operation for the current WorkIntel workflow. */ public function schedule(): BelongsTo { return $this->belongsTo(ReportSchedule::class, 'report_schedule_id'); }
    /** Handles the requester operation for the current WorkIntel workflow. */ public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    /** Handles the exports operation for the current WorkIntel workflow. */ public function exports(): HasMany { return $this->hasMany(ReportExport::class); }

    /** Handles the rows operation for the current WorkIntel workflow. */ public function rows(): array
    {
        if (! $this->result_rows) return [];
        $decoded = json_decode($this->result_rows, true);
        return is_array($decoded) ? $decoded : [];
    }
}
