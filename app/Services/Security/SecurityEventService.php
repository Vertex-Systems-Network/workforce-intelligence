<?php

namespace App\Services\Security;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Provides security event service behavior within the WorkIntel application. */ class SecurityEventService
{
    /** Handles the record operation for the current WorkIntel workflow. */ public function record(?Workspace $workspace, ?User $user, string $type, string $severity, Request $request, array $metadata = []): ?SecurityEvent
    {
        // Security telemetry must never become an authentication dependency.
        // A partially migrated installation should still be able to sign in/out.
        if (! Schema::hasTable('security_events')) {
            Log::warning('Security event storage is unavailable; event was not persisted.', [
                'event_type' => $type,
                'workspace_id' => $workspace?->id,
                'user_id' => $user?->id,
                'reason' => 'security_events table is missing',
            ]);
            return null;
        }

        try {
            return SecurityEvent::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $workspace?->id,
                'user_id' => $user?->id,
                'event_type' => $type,
                'severity' => $severity,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'metadata' => $metadata,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Security event persistence failed without blocking the request.', [
                'event_type' => $type,
                'workspace_id' => $workspace?->id,
                'user_id' => $user?->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
