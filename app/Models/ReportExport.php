<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides report export behavior within the WorkIntel application. */ class ReportExport extends Model
{
    protected $fillable = ['uuid', 'workspace_id', 'report_run_id', 'created_by', 'format', 'disk', 'path', 'filename', 'mime_type', 'size_bytes', 'status', 'completed_at', 'error_message'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['completed_at' => 'datetime']; }

    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): BelongsTo { return $this->belongsTo(ReportRun::class, 'report_run_id'); }
    /** Handles the creator operation for the current WorkIntel workflow. */ public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
