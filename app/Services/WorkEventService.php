<?php

namespace App\Services;

use App\Models\WorkEvent;
use Illuminate\Support\Str;

/** Provides work event service behavior within the WorkIntel application. */ class WorkEventService
{
    /** Handles the record operation for the current WorkIntel workflow. */ public function record(array $data): WorkEvent
    {
        $payload = [
            'uuid' => $data['uuid'] ?? (string) Str::uuid(),
            'workspace_id' => $data['workspace_id'],
            'member_id' => $data['member_id'],
            'device_id' => $data['device_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'task_id' => $data['task_id'] ?? null,
            'event_type' => $data['event_type'],
            'source' => $data['source'] ?? 'system',
            'title' => $data['title'] ?? null,
            'detail' => $data['detail'] ?? null,
            'started_at' => $data['started_at'] ?? now(),
            'ended_at' => $data['ended_at'] ?? null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'activity_percent' => $data['activity_percent'] ?? null,
            'dedupe_key' => $data['dedupe_key'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ];

        if ($payload['dedupe_key']) {
            return WorkEvent::firstOrCreate(
                ['workspace_id' => $payload['workspace_id'], 'dedupe_key' => $payload['dedupe_key']],
                $payload
            );
        }

        return WorkEvent::create($payload);
    }
}
