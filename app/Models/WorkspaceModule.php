<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides workspace module behavior within the WorkIntel application. */ class WorkspaceModule extends Model
{
    protected $fillable = [
        'workspace_id', 'module_key', 'is_enabled', 'navigation_visible', 'background_processing',
        'label_override', 'settings', 'enabled_at', 'enabled_by', 'disabled_at', 'disabled_by',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean', 'navigation_visible' => 'boolean', 'background_processing' => 'boolean',
            'settings' => 'array', 'enabled_at' => 'datetime', 'disabled_at' => 'datetime',
        ];
    }

    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the enabled by operation for the current WorkIntel workflow. */ public function enabledBy(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'enabled_by'); }
    /** Handles the disabled by operation for the current WorkIntel workflow. */ public function disabledBy(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'disabled_by'); }
}
