<?php

namespace App\Services\Field;

use App\Models\FieldCheckpoint;
use App\Models\FieldCheckpointVisit;
use App\Models\FieldWorkOrder;
use App\Models\MobileSyncEvent;
use App\Models\SafetyIncident;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Integrations\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Provides field workforce service behavior within the WorkIntel application. */ class FieldWorkforceService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly WebhookService $events) {}

    private const MOBILE_TRANSITIONS = [
        'assigned' => ['accepted', 'in_progress', 'blocked', 'completed'],
        'accepted' => ['in_progress', 'blocked', 'completed'],
        'in_progress' => ['blocked', 'completed'],
        'blocked' => ['in_progress', 'completed'],
        'completed' => [],
        'canceled' => [],
    ];

    /** Handles the assert assigned operation for the current WorkIntel workflow. */ public function assertAssigned(WorkspaceMember $member, FieldWorkOrder $order): void
    {
        abort_unless((int) $order->workspace_id === (int) $member->workspace_id, 404);
        if ($member->hasPermission('field.manage')) return;
        abort_unless($order->assignees()->where('member_id', $member->id)->exists(), 403, 'This work order is not assigned to you.');
    }

    /** Updates update status data for the requested resource. */ public function updateStatus(FieldWorkOrder $order, WorkspaceMember $member, string $status, ?string $note, array $location = []): FieldWorkOrder
    {
        $this->assertAssigned($member, $order);
        abort_unless(in_array($status, ['assigned', 'accepted', 'in_progress', 'blocked', 'completed'], true), 422, 'Unsupported work-order status.');

        if (! $member->hasPermission('field.manage') && $status !== $order->status) {
            $allowed = self::MOBILE_TRANSITIONS[$order->status] ?? [];
            abort_unless(in_array($status, $allowed, true), 422, "Work order cannot transition from {$order->status} to {$status}.");
        }

        $order->update(['status' => $status]);
        $assignee = $order->assignees()->where('member_id', $member->id)->first();
        if ($assignee) {
            if ($status === 'accepted' && ! $assignee->accepted_at) $assignee->update(['accepted_at' => now()]);
            if ($status === 'completed') $assignee->update(['completed_at' => now()]);
        }

        $order->events()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $order->workspace_id,
            'member_id' => $member->id,
            'event_type' => 'status.'.$status,
            'note' => $note,
            'latitude' => $location['latitude'] ?? null,
            'longitude' => $location['longitude'] ?? null,
            'accuracy_meters' => $location['accuracy_meters'] ?? null,
            'metadata' => ['source' => $location['source'] ?? 'mobile'],
            'occurred_at' => $location['occurred_at'] ?? now(),
            'created_at' => now(),
        ]);

        $fresh=$order->fresh(['assignees.member.user', 'project', 'client']);
        if($workspace=Workspace::find($order->workspace_id)){
            try{$this->events->queueEvent($workspace,'work_orders.updated',['work_order_id'=>$order->id,'number'=>$order->work_order_number,'status'=>$status,'member_id'=>$member->id,'project_id'=>$order->project_id,'note'=>$note]);}catch(\Throwable $e){report($e);}
        }
        return $fresh;
    }

    /** Handles the scan checkpoint operation for the current WorkIntel workflow. */ public function scanCheckpoint(WorkspaceMember $member, string $scanToken, array $data): FieldCheckpointVisit
    {
        $checkpoint = FieldCheckpoint::query()
            ->where('workspace_id', $member->workspace_id)
            ->where('status', 'active')
            ->where('scan_token_hash', hash('sha256', $scanToken))
            ->first();
        abort_unless($checkpoint, 422, 'Checkpoint code is invalid or inactive.');

        $within = null;
        if ($checkpoint->latitude !== null && $checkpoint->longitude !== null && $checkpoint->radius_meters) {
            abort_unless(isset($data['latitude'], $data['longitude']), 422, 'Location is required for this checkpoint.');
            $distance = $this->distanceMeters(
                (float) $data['latitude'], (float) $data['longitude'],
                (float) $checkpoint->latitude, (float) $checkpoint->longitude
            );
            $within = $distance <= (int) $checkpoint->radius_meters;
            abort_unless($within, 422, 'You are outside this checkpoint geofence.');
        }

        $orderId = $data['field_work_order_id'] ?? null;
        if ($orderId) {
            $order = FieldWorkOrder::where('workspace_id', $member->workspace_id)->findOrFail($orderId);
            $this->assertAssigned($member, $order);
        }

        $visit=FieldCheckpointVisit::create([
            'uuid' => (string) Str::uuid(), 'workspace_id' => $member->workspace_id,
            'field_checkpoint_id' => $checkpoint->id, 'member_id' => $member->id,
            'field_work_order_id' => $orderId, 'scan_method' => $data['scan_method'] ?? 'qr',
            'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null,
            'accuracy_meters' => $data['accuracy_meters'] ?? null, 'within_geofence' => $within,
            'visited_at' => $data['visited_at'] ?? now(), 'created_at' => now(),
        ]);
        if($workspace=Workspace::find($member->workspace_id)){
            try{$this->events->queueEvent($workspace,'field.checkpoint_visited',['visit_id'=>$visit->id,'checkpoint_id'=>$checkpoint->id,'checkpoint_name'=>$checkpoint->name,'member_id'=>$member->id,'work_order_id'=>$orderId,'within_geofence'=>$within]);}catch(\Throwable $e){report($e);}
        }
        return $visit;
    }

    /** Handles the process offline event operation for the current WorkIntel workflow. */ public function processOfflineEvent(WorkspaceMember $member, array $event): array
    {
        $sync = MobileSyncEvent::firstOrCreate(
            ['event_uuid' => $event['event_uuid']],
            [
                'workspace_id' => $member->workspace_id, 'member_id' => $member->id,
                'event_type' => $event['event_type'], 'payload' => $event['payload'] ?? [],
                'status' => 'processing', 'client_occurred_at' => $event['occurred_at'] ?? null, 'created_at' => now(),
            ]
        );
        abort_unless(
            (int) $sync->workspace_id === (int) $member->workspace_id && (int) $sync->member_id === (int) $member->id,
            409,
            'Offline event UUID is already owned by another mobile identity.'
        );
        if (! $sync->wasRecentlyCreated && $sync->status === 'processed') {
            return ['event_uuid' => $event['event_uuid'], 'status' => 'duplicate'];
        }
        if (! $sync->wasRecentlyCreated && $sync->status === 'processing') {
            return ['event_uuid' => $event['event_uuid'], 'status' => 'processing'];
        }
        if ($sync->status === 'failed') {
            $sync->update([
                'status' => 'processing', 'error' => null, 'payload' => $event['payload'] ?? [],
                'client_occurred_at' => $event['occurred_at'] ?? $sync->client_occurred_at,
            ]);
        }

        try {
            $result = DB::transaction(function () use ($member, $event) {
                $payload = $event['payload'] ?? [];
                $payload['source'] = 'mobile_offline';
                $payload['occurred_at'] = $event['occurred_at'] ?? null;
                return match ($event['event_type']) {
                    'work_order.status' => $this->updateStatus(
                        FieldWorkOrder::where('workspace_id', $member->workspace_id)->findOrFail($payload['work_order_id'] ?? 0),
                        $member, $payload['status'] ?? '', $payload['note'] ?? null, $payload
                    ),
                    'checkpoint.visit' => $this->scanCheckpoint($member, $payload['scan_token'] ?? '', $payload),
                    'incident.report' => $this->createIncident($member, $payload),
                    default => throw new \InvalidArgumentException('Unsupported mobile event type.'),
                };
            });
            $sync->update(['status' => 'processed', 'processed_at' => now(), 'error' => null]);
            return ['event_uuid' => $event['event_uuid'], 'status' => 'processed', 'resource_id' => $result->id ?? null];
        } catch (\Throwable $e) {
            $sync->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000), 'processed_at' => now()]);
            throw $e;
        }
    }

    /** Creates create incident data for the requested workflow. */ public function createIncident(WorkspaceMember $member, array $data): SafetyIncident
    {
        $orderId = $data['field_work_order_id'] ?? null;
        if ($orderId) {
            $order = FieldWorkOrder::where('workspace_id', $member->workspace_id)->findOrFail($orderId);
            $this->assertAssigned($member, $order);
        }

        $incident=DB::transaction(function () use ($member, $data, $orderId) {
            // Serialize human-readable incident numbering per workspace.
            DB::table('workspaces')->where('id', $member->workspace_id)->lockForUpdate()->first();
            $number = 'INC-'.now()->format('Y').'-'.str_pad(
                (string) (SafetyIncident::where('workspace_id', $member->workspace_id)->count() + 1),
                5, '0', STR_PAD_LEFT
            );

            return SafetyIncident::create([
                'uuid' => (string) Str::uuid(), 'workspace_id' => $member->workspace_id,
                'reporter_member_id' => $member->id, 'field_work_order_id' => $orderId,
                'incident_number' => $number, 'type' => $data['type'] ?? 'safety',
                'severity' => $data['severity'] ?? 'medium', 'status' => 'open',
                'title' => $data['title'] ?? 'Field incident', 'description' => $data['description'] ?? '',
                'occurred_at' => $data['occurred_at'] ?? now(), 'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null, 'immediate_action' => $data['immediate_action'] ?? null,
            ]);
        });
        if($workspace=Workspace::find($member->workspace_id)){
            try{$this->events->queueEvent($workspace,'incidents.created',['incident_id'=>$incident->id,'incident_number'=>$incident->incident_number,'severity'=>$incident->severity,'type'=>$incident->type,'title'=>$incident->title,'description'=>$incident->description,'member_id'=>$member->id,'work_order_id'=>$orderId]);}catch(\Throwable $e){report($e);}
        }
        return $incident;
    }

    /** Handles the distance meters operation for the current WorkIntel workflow. */ public function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $radius = 6371000;
        $phi1 = deg2rad($lat1); $phi2 = deg2rad($lat2);
        $deltaPhi = deg2rad($lat2 - $lat1); $deltaLambda = deg2rad($lon2 - $lon1);
        $a = sin($deltaPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($deltaLambda / 2) ** 2;
        return $radius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
