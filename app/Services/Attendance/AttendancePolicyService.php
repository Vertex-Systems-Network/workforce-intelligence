<?php

namespace App\Services\Attendance;

use App\Models\AttendanceEvent;
use App\Models\AttendanceLocation;
use App\Models\AttendancePolicy;
use App\Models\AttendanceRecord;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Provides attendance policy service behavior within the WorkIntel application. */ class AttendancePolicyService
{
    /** Handles the installed operation for the current WorkIntel workflow. */ public function installed(): bool
    {
        return Schema::hasTable('attendance_policies');
    }

    /** Handles the default payload operation for the current WorkIntel workflow. */ public function defaultPayload(): array
    {
        return [
            'allow_web'=>true,'allow_mobile'=>true,'require_geolocation'=>false,'require_geofence'=>false,
            'max_accuracy_meters'=>250,'correction_window_days'=>7,'missed_clock_out_hours'=>16,
            'auto_flag_missed_clock_out'=>true,'allow_employee_corrections'=>true,
        ];
    }

    /** Handles the policy payload operation for the current WorkIntel workflow. */ public function policyPayload(Workspace $workspace): array
    {
        return $this->installed() ? $this->policy($workspace)->toArray() : $this->defaultPayload();
    }

    /** Handles the policy operation for the current WorkIntel workflow. */ public function policy(Workspace $workspace): AttendancePolicy
    {
        return AttendancePolicy::firstOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'allow_web' => true,
                'allow_mobile' => true,
                'require_geolocation' => false,
                'require_geofence' => false,
                'max_accuracy_meters' => 250,
                'correction_window_days' => 7,
                'missed_clock_out_hours' => 16,
                'auto_flag_missed_clock_out' => true,
                'allow_employee_corrections' => true,
            ]
        );
    }

    /**
     * @return array{required:bool,provided:bool,accepted:bool,within_geofence:?bool,location_id:?int,location_name:?string,distance_meters:?float,accuracy_meters:?float}
     */
    /** Validates validate location input before it is processed. */ public function validateLocation(Workspace $workspace, array $input, string $source = 'web'): array
    {
        if (! $this->installed()) {
            return ['required'=>false,'provided'=>false,'accepted'=>true,'within_geofence'=>null,'location_id'=>null,'location_name'=>null,'distance_meters'=>null,'accuracy_meters'=>null];
        }
        $policy = $this->policy($workspace);
        if ($source === 'web' && ! $policy->allow_web) {
            throw ValidationException::withMessages(['source' => ['Web attendance is disabled for this workspace.']]);
        }
        if ($source === 'mobile' && ! $policy->allow_mobile) {
            throw ValidationException::withMessages(['source' => ['Mobile attendance is disabled for this workspace.']]);
        }

        $lat = isset($input['latitude']) ? (float) $input['latitude'] : null;
        $lng = isset($input['longitude']) ? (float) $input['longitude'] : null;
        $accuracy = isset($input['accuracy_meters']) ? (float) $input['accuracy_meters'] : null;
        $provided = $lat !== null && $lng !== null;

        if ($policy->require_geolocation && ! $provided) {
            throw ValidationException::withMessages(['location' => ['Location permission is required to mark attendance.']]);
        }
        if ($provided && $accuracy !== null && $accuracy > $policy->max_accuracy_meters) {
            throw ValidationException::withMessages(['location' => ["Location accuracy is too low. Required within {$policy->max_accuracy_meters} meters."]]);
        }

        $nearest = null;
        if ($provided) {
            foreach (AttendanceLocation::query()->where('workspace_id', $workspace->id)->where('status', 'active')->get() as $location) {
                $distance = $this->distanceMeters($lat, $lng, (float) $location->latitude, (float) $location->longitude);
                if (! $nearest || $distance < $nearest['distance_meters']) {
                    $nearest = [
                        'id' => $location->id,
                        'name' => $location->name,
                        'distance_meters' => $distance,
                        'within' => $distance <= (int) $location->radius_meters,
                    ];
                }
            }
        }

        if ($policy->require_geofence) {
            if (! $provided) {
                throw ValidationException::withMessages(['location' => ['A valid work location is required to mark attendance.']]);
            }
            if (! $nearest || ! $nearest['within']) {
                throw ValidationException::withMessages(['location' => ['You are outside every allowed attendance location.']]);
            }
        }

        return [
            'required' => (bool) ($policy->require_geolocation || $policy->require_geofence),
            'provided' => $provided,
            'accepted' => true,
            'within_geofence' => $nearest ? (bool) $nearest['within'] : null,
            'location_id' => $nearest['id'] ?? null,
            'location_name' => $nearest['name'] ?? null,
            'distance_meters' => isset($nearest['distance_meters']) ? round((float) $nearest['distance_meters'], 1) : null,
            'accuracy_meters' => $accuracy,
        ];
    }

    /** Handles the record event operation for the current WorkIntel workflow. */ public function recordEvent(
        Workspace $workspace,
        WorkspaceMember $member,
        ?AttendanceRecord $record,
        string $eventType,
        string $source,
        array $locationCheck = [],
        array $metadata = []
    ): ?AttendanceEvent {
        if (! Schema::hasTable('attendance_events')) return null;
        return AttendanceEvent::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'attendance_record_id' => $record?->id,
            'attendance_location_id' => $locationCheck['location_id'] ?? null,
            'event_type' => $eventType,
            'source' => $source,
            'occurred_at' => now($workspace->timezone),
            'latitude' => $metadata['latitude'] ?? null,
            'longitude' => $metadata['longitude'] ?? null,
            'accuracy_meters' => $locationCheck['accuracy_meters'] ?? ($metadata['accuracy_meters'] ?? null),
            'within_geofence' => $locationCheck['within_geofence'] ?? null,
            'metadata' => array_merge($metadata, [
                'location_name' => $locationCheck['location_name'] ?? null,
                'distance_meters' => $locationCheck['distance_meters'] ?? null,
            ]),
            'created_at' => now(),
        ]);
    }

    /** Handles the distance meters operation for the current WorkIntel workflow. */ private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000.0;
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dPhi = deg2rad($lat2 - $lat1);
        $dLambda = deg2rad($lng2 - $lng1);
        $a = sin($dPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;
        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
