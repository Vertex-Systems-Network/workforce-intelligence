<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides screenshot behavior within the WorkIntel application. */ class Screenshot extends Model
{
    protected $fillable = [
        'uuid', 'workspace_id', 'member_id', 'device_id', 'project_id', 'task_id', 'disk', 'path',
        'mime_type', 'size_bytes', 'width', 'height', 'monitor_index', 'app_name', 'activity_percent',
        'blurred', 'flagged', 'flag_reason', 'note', 'captured_at', 'deleted_at', 'deleted_by',
        'storage_provider_id', 'storage_status', 'checksum_sha256', 'remote_key', 'remote_object_id', 'storage_verified_at', 'storage_error',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'deleted_at' => 'datetime',
            'storage_verified_at' => 'datetime',
            'blurred' => 'boolean',
            'flagged' => 'boolean',
        ];
    }

    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the member operation for the current WorkIntel workflow. */ public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
    /** Handles the device operation for the current WorkIntel workflow. */ public function device(): BelongsTo { return $this->belongsTo(Device::class); }
    /** Handles the project operation for the current WorkIntel workflow. */ public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    /** Handles the task operation for the current WorkIntel workflow. */ public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    /** Removes deleted by data from the requested resource. */ public function deletedBy(): BelongsTo { return $this->belongsTo(User::class, 'deleted_by'); }
    /** Handles the storage provider operation for the current WorkIntel workflow. */ public function storageProvider(): BelongsTo { return $this->belongsTo(ScreenshotStorageProvider::class, 'storage_provider_id'); }
}
