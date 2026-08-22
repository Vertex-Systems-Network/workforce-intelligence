<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Installation\AgentEnrollmentService;
use App\Models\AgentCommand;
use App\Models\Device;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Provides device controller behavior within the WorkIntel application. */ class DeviceController extends Controller
{
    /** Returns the requested resource collection. */ public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $onlineAfter = now()->subSeconds(config('workintel.agent.online_threshold_seconds', 90));

        $devices = Device::query()
            ->with(['member.user', 'member.department'])
            ->where('workspace_id', $workspace->id)
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn (Device $device) => $this->devicePayload($device, $onlineAfter));

        $members = WorkspaceMember::query()
            ->with('user')
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->map(fn (WorkspaceMember $member) => [
                'id' => $member->id,
                'name' => trim($member->user->first_name.' '.$member->user->last_name),
                'job_title' => $member->job_title,
            ]);

        return response()->json([
            'devices' => $devices,
            'members' => $members,
            'agent' => [
                'latest_version' => config('workintel.agent.latest_version'),
                'minimum_supported_version' => config('workintel.agent.minimum_supported_version'),
                'heartbeat_interval_seconds' => config('workintel.agent.heartbeat_interval_seconds'),
                'online_threshold_seconds' => config('workintel.agent.online_threshold_seconds'),
            ],
            'stats' => [
                'total' => $devices->count(),
                'online' => $devices->where('connection_status', 'online')->count(),
                'update_required' => $devices->whereIn('health', ['update_available', 'unsupported'])->count(),
                'revoked' => $devices->where('status', 'revoked')->count(),
            ],
        ]);
    }

    /** Returns details for the requested resource. */ public function show(Request $request, Device $device): JsonResponse
    {
        $this->assertDeviceWorkspace($request, $device);
        $device->load(['member.user', 'member.department']);
        $onlineAfter = now()->subSeconds(config('workintel.agent.online_threshold_seconds', 90));

        return response()->json([
            'device' => $this->devicePayload($device, $onlineAfter),
            'events' => $device->events()->latest('occurred_at')->limit(40)->get()->map(fn ($event) => [
                'id' => $event->id,
                'event_uuid' => $event->event_uuid,
                'event_type' => $event->event_type,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
                'payload' => $event->payload,
            ]),
            'sync_batches' => $device->syncBatches()->latest('received_at')->limit(12)->get()->map(fn ($batch) => [
                'batch_uuid' => $batch->batch_uuid,
                'event_count' => $batch->event_count,
                'accepted_count' => $batch->accepted_count,
                'duplicate_count' => $batch->duplicate_count,
                'received_at' => $batch->received_at?->toIso8601String(),
            ]),
            'commands' => $device->commands()->latest()->limit(12)->get()->map(fn (AgentCommand $command) => $this->commandPayload($command)),
        ]);
    }

    /** Creates create enrollment data for the requested workflow. */ public function createEnrollment(Request $request, AgentEnrollmentService $enrollments): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'member_id' => ['required', 'integer'],
            'expires_minutes' => ['nullable', 'integer', 'min:5', 'max:60'],
        ]);
        $member = WorkspaceMember::query()->where('workspace_id', $workspace->id)->where('status', 'active')->findOrFail($data['member_id']);
        return response()->json($enrollments->create($workspace, $member, $request->user(), $data['expires_minutes'] ?? null), 201);
    }

    /** Updates update data for the requested resource. */ public function update(Request $request, Device $device): JsonResponse
    {
        $this->assertDeviceWorkspace($request, $device);
        abort_if($device->status === 'revoked', 422, 'A revoked device cannot be edited.');

        $data = $request->validate(['name' => ['required', 'string', 'max:160']]);
        $device->update(['name' => $data['name']]);

        return response()->json(['device' => $this->devicePayload($device->fresh(['member.user']), now()->subSeconds(config('workintel.agent.online_threshold_seconds', 90)))]);
    }

    /** Handles the revoke operation for the current WorkIntel workflow. */ public function revoke(Request $request, Device $device): JsonResponse
    {
        $this->assertDeviceWorkspace($request, $device);

        DB::transaction(function () use ($device) {
            $device->tokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $device->update(['status' => 'revoked', 'tracking_status' => 'stopped', 'revoked_at' => now()]);
            $device->commands()->whereIn('status', ['queued', 'delivered'])->update(['status' => 'cancelled']);
        });

        return response()->json(['message' => 'Device access revoked. Re-enrollment is required before it can reconnect.']);
    }

    /** Handles the queue command operation for the current WorkIntel workflow. */ public function queueCommand(Request $request, Device $device): JsonResponse
    {
        $this->assertDeviceWorkspace($request, $device);
        abort_if($device->status !== 'active', 422, 'Commands can only be sent to active devices.');

        $data = $request->validate([
            'command_type' => ['required', Rule::in(['update_agent', 'restart_agent', 'pause_tracking', 'resume_tracking'])],
            'payload' => ['nullable', 'array'],
        ]);

        $command = AgentCommand::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $device->workspace_id,
            'device_id' => $device->id,
            'queued_by' => $request->user()->id,
            'command_type' => $data['command_type'],
            'payload' => $data['payload'] ?? null,
            'status' => 'queued',
        ]);

        return response()->json(['command' => $this->commandPayload($command)], 201);
    }

    /** Handles the device payload operation for the current WorkIntel workflow. */ private function devicePayload(Device $device, $onlineAfter): array
    {
        $connectionStatus = $device->status === 'active' && $device->last_heartbeat_at && $device->last_heartbeat_at->gte($onlineAfter)
            ? 'online'
            : 'offline';

        $latest = (string) config('workintel.agent.latest_version', '0.1.0');
        $minimum = (string) config('workintel.agent.minimum_supported_version', '0.1.0');
        $version = $device->agent_version ?: '0.0.0';
        $health = version_compare($version, $minimum, '<') ? 'unsupported' : (version_compare($version, $latest, '<') ? 'update_available' : 'healthy');

        return [
            'id' => $device->id,
            'uuid' => $device->uuid,
            'member_id' => $device->member_id,
            'employee' => $device->member?->user ? trim($device->member->user->first_name.' '.$device->member->user->last_name) : 'Unknown employee',
            'department' => $device->member?->department?->name,
            'name' => $device->name,
            'platform' => $device->platform,
            'os_name' => $device->os_name,
            'os_version' => $device->os_version,
            'architecture' => $device->architecture,
            'agent_version' => $device->agent_version,
            'status' => $device->status,
            'connection_status' => $connectionStatus,
            'health' => $health,
            'tracking_status' => $device->tracking_status,
            'is_idle' => $device->is_idle,
            'offline_queue_size' => $device->offline_queue_size,
            'capabilities' => $device->capabilities ?? [],
            'last_ip' => $device->last_ip,
            'enrolled_at' => $device->enrolled_at?->toIso8601String(),
            'last_heartbeat_at' => $device->last_heartbeat_at?->toIso8601String(),
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'last_sync_at' => $device->last_sync_at?->toIso8601String(),
            'revoked_at' => $device->revoked_at?->toIso8601String(),
        ];
    }

    /** Handles the command payload operation for the current WorkIntel workflow. */ private function commandPayload(AgentCommand $command): array
    {
        return [
            'uuid' => $command->uuid,
            'command_type' => $command->command_type,
            'status' => $command->status,
            'payload' => $command->payload,
            'created_at' => $command->created_at?->toIso8601String(),
            'delivered_at' => $command->delivered_at?->toIso8601String(),
            'acknowledged_at' => $command->acknowledged_at?->toIso8601String(),
            'result' => $command->result,
        ];
    }

    /** Handles the assert device workspace operation for the current WorkIntel workflow. */ private function assertDeviceWorkspace(Request $request, Device $device): void
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($workspace && $device->workspace_id === $workspace->id, 404);
    }

    /** Handles the new enrollment code operation for the current WorkIntel workflow. */ private function newEnrollmentCode(): string
    {
        $value = strtoupper(bin2hex(random_bytes(6)));
        return 'WI-'.substr($value, 0, 4).'-'.substr($value, 4, 4).'-'.substr($value, 8, 4);
    }

    /** Handles the normalize enrollment code operation for the current WorkIntel workflow. */ private function normalizeEnrollmentCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($code)) ?? '');
    }
}
