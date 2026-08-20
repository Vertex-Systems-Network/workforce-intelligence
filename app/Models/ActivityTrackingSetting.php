<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides activity tracking setting behavior within the WorkIntel application. */ class ActivityTrackingSetting extends Model
{
    protected $fillable = [
        'workspace_id', 'application_tracking_enabled', 'website_tracking_enabled', 'capture_window_titles',
        'capture_page_titles', 'store_full_urls', 'minimum_session_seconds', 'idle_threshold_seconds',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return [
            'application_tracking_enabled' => 'boolean',
            'website_tracking_enabled' => 'boolean',
            'capture_window_titles' => 'boolean',
            'capture_page_titles' => 'boolean',
            'store_full_urls' => 'boolean',
        ];
    }

    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
}
