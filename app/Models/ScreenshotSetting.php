<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides screenshot setting behavior within the WorkIntel application. */ class ScreenshotSetting extends Model
{
    protected $fillable = [
        'workspace_id', 'enabled', 'interval_minutes', 'randomize_minutes', 'capture_all_monitors',
        'blur_by_default', 'quality', 'allow_employee_delete', 'retention_days', 'max_upload_kb',
        'capture_notification_mode', 'notify_on_upload_failure',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'capture_all_monitors' => 'boolean',
            'blur_by_default' => 'boolean',
            'allow_employee_delete' => 'boolean',
            'notify_on_upload_failure' => 'boolean',
        ];
    }

    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
}
