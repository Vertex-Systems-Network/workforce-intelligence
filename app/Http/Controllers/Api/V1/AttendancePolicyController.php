<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceEvent;
use App\Models\AttendanceLocation;
use App\Models\AttendanceRecord;
use App\Models\ShiftAssignment;
use App\Models\WorkspaceMember;
use App\Services\Access\WorkScopeService;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\AttendancePolicyService;
use App\Services\Approvals\ApprovalEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Provides attendance phase15 controller behavior within the WorkIntel application. */ class AttendancePolicyController extends Controller
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(
        private readonly AttendancePolicyService $policies,
        private readonly AttendanceCalculator $calculator,
        private readonly WorkScopeService $scope,
    ) {}

    /** Handles the settings operation for the current WorkIntel workflow. */ public function settings(Request $request): JsonResponse
    {
        $this->assertInstalled();
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');
        $canManagePolicy = $member->hasPermission('attendance.policy_manage');

        return response()->json([
            'policy' => $this->policies->policy($workspace),
            'locations' => AttendanceLocation::query()
                ->where('workspace_id', $workspace->id)
                ->when(! $canManagePolicy, fn ($q) => $q->where('status', 'active'))
                ->orderBy('name')->get(),
            'can_manage_policy' => $canManagePolicy,
            'pwa' => ['installable' => true, 'mobile_source' => 'mobile'],
        ]);
    }

    /** Updates update policy data for the requested resource. */ public function updatePolicy(Request $request): JsonResponse
    {
        $this->assertInstalled();
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'allow_web' => ['required', 'boolean'],
            'allow_mobile' => ['required', 'boolean'],
            'require_geolocation' => ['required', 'boolean'],
            'require_geofence' => ['required', 'boolean'],
            'max_accuracy_meters' => ['required', 'integer', 'min:10', 'max:5000'],
            'correction_window_days' => ['required', 'integer', 'min:1', 'max:90'],
            'missed_clock_out_hours' => ['required', 'integer', 'min:4', 'max:48'],
            'auto_flag_missed_clock_out' => ['required', 'boolean'],
            'allow_employee_corrections' => ['required', 'boolean'],
        ]);
        if ($data['require_geofence']) $data['require_geolocation'] = true;
        $policy = $this->policies->policy($workspace);
        $policy->update($data);
        return response()->json(['data' => $policy->fresh()]);
    }

    /** Handles the store location operation for the current WorkIntel workflow. */ public function storeLocation(Request $request): JsonResponse
    {
        $this->assertInstalled();
        $workspace = $request->attributes->get('workspace');
        $data = $this->locationData($request);
        $location = AttendanceLocation::create(['uuid' => (string) Str::uuid(), 'workspace_id' => $workspace->id] + $data);
        return response()->json(['data' => $location], 201);
    }

    /** Updates update location data for the requested resource. */ public function updateLocation(Request $request, AttendanceLocation $location): JsonResponse
    {
        $this->assertInstalled();
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $location->workspace_id === (int) $workspace->id, 404);
        $location->update($this->locationData($request));
        return response()->json(['data' => $location->fresh()]);
    }

    /** Handles the destroy location operation for the current WorkIntel workflow. */ public function destroyLocation(Request $request, AttendanceLocation $location): JsonResponse
    {
        $this->assertInstalled();
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $location->workspace_id === (int) $workspace->id, 404);
        $location->delete();
        return response()->json(null, 204);
    }

    /** Handles the corrections operation for the current WorkIntel workflow. */ public function corrections(Request $request): JsonResponse
    {
        $this->assertInstalled();
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        $query = AttendanceCorrectionRequest::query()
            ->with(['member.user:id,first_name,last_name', 'reviewer:id,first_name,last_name'])
            ->where('workspace_id', $workspace->id)
            ->latest('created_at');

        if ($viewer->hasPermission('attendance.manage')) {
            if (! ($viewer->hasPermission('people.view_all') || $viewer->hasPermission('people.manage'))) {
                $query->whereIn('member_id', $this->scope->teamMemberIds($viewer));
            }
        } else {
            $query->where('member_id', $viewer->id);
        }

        if ($request->filled('status')) $query->where('status', $request->string('status')->toString());
        return response()->json(['data' => $query->limit(200)->get()]);
    }

    /** Handles the request correction operation for the current WorkIntel workflow. */ public function requestCorrection(Request $request, ApprovalEngine $approvals): JsonResponse
    {
        $this->assertInstalled();
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');
        $policy = $this->policies->policy($workspace);
        abort_unless($policy->allow_employee_corrections || $member->hasPermission('attendance.manage'), 403, 'Attendance correction requests are disabled.');

        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'requested_clock_in_at' => ['nullable', 'date'],
            'requested_clock_out_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        abort_if(empty($data['requested_clock_in_at']) && empty($data['requested_clock_out_at']), 422, 'Provide a corrected clock-in or clock-out time.');
        if (! empty($data['requested_clock_in_at']) && ! empty($data['requested_clock_out_at'])) {
            abort_unless(Carbon::parse($data['requested_clock_out_at'])->gt(Carbon::parse($data['requested_clock_in_at'])), 422, 'Corrected clock-out must be after clock-in.');
        }
        $date = Carbon::parse($data['date'], $workspace->timezone)->startOfDay();
        abort_if($date->lt(now($workspace->timezone)->startOfDay()->subDays($policy->correction_window_days)), 422, "Corrections are limited to the last {$policy->correction_window_days} days.");

        $record = AttendanceRecord::query()->where('workspace_id', $workspace->id)->where('member_id', $member->id)->whereDate('date', $date->toDateString())->first();
        abort_if(AttendanceCorrectionRequest::query()->where('workspace_id', $workspace->id)->where('member_id', $member->id)->whereDate('date', $date->toDateString())->where('status', 'pending')->exists(), 422, 'A correction for this date is already pending.');

        $correction = AttendanceCorrectionRequest::create([
            'uuid' => (string) Str::uuid(), 'workspace_id' => $workspace->id, 'member_id' => $member->id,
            'attendance_record_id' => $record?->id, 'date' => $date->toDateString(),
            'requested_clock_in_at' => $data['requested_clock_in_at'] ?? null,
            'requested_clock_out_at' => $data['requested_clock_out_at'] ?? null,
            'reason' => $data['reason'], 'status' => 'pending',
        ]);
        $this->policies->recordEvent($workspace, $member, $record, 'correction_requested', 'web', [], ['correction_id' => $correction->id]);
        $approval = $approvals->submitFor(
            $workspace, $member, 'attendance_correction.submitted', 'attendance_correction', $correction,
            ['department_id' => $member->department_id, 'date' => $date->toDateString()],
            'Attendance correction · '.$date->toDateString(), $data['reason']
        );
        return response()->json(['data' => $correction, 'approval_request_id' => $approval?->id], 201);
    }

    /** Handles the review correction operation for the current WorkIntel workflow. */ public function reviewCorrection(Request $request, AttendanceCorrectionRequest $correction, ApprovalEngine $approvals): JsonResponse
    {
        $this->assertInstalled();
        $workspace = $request->attributes->get('workspace');
        $reviewer = $request->attributes->get('workspaceMember');
        abort_unless((int) $correction->workspace_id === (int) $workspace->id, 404);
        abort_if($correction->status !== 'pending', 422, 'This correction has already been reviewed.');
        $target = WorkspaceMember::query()->where('workspace_id', $workspace->id)->findOrFail($correction->member_id);
        $this->assertCanManageMember($reviewer, $target);
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])], 'review_note' => ['nullable', 'string', 'max:2000']]);

        if ($data['status'] === 'approved') {
            $assignment = ShiftAssignment::query()->where('workspace_id', $workspace->id)->where('member_id', $target->id)->whereDate('date', $correction->date->toDateString())->first();
            $record = AttendanceRecord::query()->where('workspace_id', $workspace->id)->where('member_id', $target->id)->whereDate('date', $correction->date->toDateString())->first();
            if (! $record) {
                $record = AttendanceRecord::create([
                    'workspace_id' => $workspace->id, 'member_id' => $target->id, 'date' => $correction->date->toDateString(),
                    'shift_assignment_id' => $assignment?->id, 'status' => 'present', 'source' => 'manual',
                ]);
            }
            if ($correction->requested_clock_in_at) $record->clock_in_at = $correction->requested_clock_in_at;
            if ($correction->requested_clock_out_at) $record->clock_out_at = $correction->requested_clock_out_at;
            $record->flag_type = null; $record->flagged_at = null; $record->source = 'manual';
            $record->note = trim(($record->note ? $record->note."\n" : '').'Attendance correction approved: '.$correction->reason);
            $record->save();
            $record = $this->calculator->recalculate($record);
            $correction->attendance_record_id = $record->id;
            $this->policies->recordEvent($workspace, $target, $record, 'correction_approved', 'manual', [], ['correction_id' => $correction->id]);
        } else {
            $this->policies->recordEvent($workspace, $target, $correction->attendanceRecord, 'correction_rejected', 'manual', [], ['correction_id' => $correction->id]);
        }

        $correction->forceFill(['status' => $data['status'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['review_note'] ?? null])->save();
        $approvals->syncExternalDecision('attendance_correction', $correction->id, $data['status'], $request->attributes->get('workspaceMember'), $data['review_note'] ?? null);
        return response()->json(['data' => $correction->fresh()->load(['member.user', 'reviewer'])]);
    }

    /** Handles the events operation for the current WorkIntel workflow. */ public function events(Request $request): JsonResponse
    {
        $this->assertInstalled();
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        $query = AttendanceEvent::query()->where('workspace_id', $workspace->id)->latest('occurred_at');
        if ($viewer->hasPermission('attendance.view_team') || $viewer->hasPermission('attendance.manage')) {
            if (! ($viewer->hasPermission('people.view_all') || $viewer->hasPermission('people.manage'))) $query->whereIn('member_id', $this->scope->teamMemberIds($viewer));
        } else $query->where('member_id', $viewer->id);
        return response()->json(['data' => $query->limit(min(300, max(1, (int) $request->integer('limit', 80))))->get()]);
    }

    /** Handles the assert installed operation for the current WorkIntel workflow. */ private function assertInstalled(): void
    {
        abort_unless($this->policies->installed(), 503, 'Attendance 2.0 schema is not installed yet. Run php artisan migrate.');
    }

    /** Handles the location data operation for the current WorkIntel workflow. */ private function locationData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:20', 'max:10000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    /** Handles the assert can manage member operation for the current WorkIntel workflow. */ private function assertCanManageMember(WorkspaceMember $actor, WorkspaceMember $target): void
    {
        abort_unless($actor->hasPermission('attendance.manage'), 403, 'Attendance management permission is required.');
        if ($actor->hasPermission('people.view_all') || $actor->hasPermission('people.manage')) return;
        abort_unless(in_array((int) $target->id, $this->scope->teamMemberIds($actor), true), 403, 'This employee is outside your attendance scope.');
    }
}
