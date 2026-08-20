<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\Holiday;
use App\Models\ShiftAssignment;
use App\Models\WorkspaceMember;
use App\Services\Access\WorkScopeService;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\AttendancePolicyService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Provides attendance controller behavior within the WorkIntel application. */ class AttendanceController extends Controller
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(
        private readonly AttendanceCalculator $calculator,
        private readonly AttendancePolicyService $attendancePolicy,
    ) {}

    /** Returns the requested resource collection. */ public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $currentMember = $request->attributes->get('workspaceMember');
        $date = $request->query('date') ? Carbon::parse($request->query('date'))->toDateString() : now($workspace->timezone)->toDateString();
        $canViewAll = $currentMember->hasPermission('people.view_all') || $currentMember->hasPermission('people.manage');
        $canViewTeam = $currentMember->hasPermission('attendance.view_team') || $currentMember->hasPermission('attendance.manage');
        $visibleMemberIds = $canViewAll
            ? WorkspaceMember::query()->where('workspace_id', $workspace->id)->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : ($canViewTeam ? app(WorkScopeService::class)->teamMemberIds($currentMember) : [(int) $currentMember->id]);

        $members = WorkspaceMember::query()
            ->with(['user:id,first_name,last_name', 'department:id,name'])
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->whereIn('id', $visibleMemberIds)
            ->orderBy('id')->get();

        $records = AttendanceRecord::query()
            ->with(['shiftAssignment.shift', 'breaks' => fn ($query) => $query->orderBy('started_at')])
            ->where('workspace_id', $workspace->id)
            ->whereDate('date', $date)
            ->whereIn('member_id', $members->pluck('id'))
            ->get()->keyBy('member_id');

        $assignments = ShiftAssignment::query()
            ->with('shift')
            ->where('workspace_id', $workspace->id)
            ->whereDate('date', $date)
            ->whereIn('member_id', $members->pluck('id'))
            ->get()->keyBy('member_id');

        $holiday = Holiday::query()
            ->where('workspace_id', $workspace->id)
            ->whereDate('date', $date)
            ->where('status', 'active')
            ->first();

        $rows = $members->map(function (WorkspaceMember $member) use ($records, $assignments, $holiday) {
            $record = $records->get($member->id);
            $assignment = $assignments->get($member->id);
            $activeBreak = $record?->breaks?->firstWhere('ended_at', null);

            return [
                'member_id' => $member->id,
                'name' => trim($member->user->first_name.' '.$member->user->last_name),
                'department' => $member->department?->name,
                'shift' => $assignment?->shift,
                'work_mode' => $assignment?->work_mode,
                'record' => $record,
                'active_break' => $activeBreak,
                'display_status' => $record?->status ?? ($holiday ? 'holiday' : ($assignment ? 'scheduled' : 'unscheduled')),
            ];
        });

        return response()->json([
            'date' => $date,
            'rows' => $rows,
            'holiday' => $holiday,
            'current_member_id' => $currentMember->id,
            'can_manage' => $currentMember->hasPermission('attendance.manage'),
            'policy' => $this->attendancePolicy->policyPayload($workspace),
        ]);
    }

    /** Handles the clock in operation for the current WorkIntel workflow. */ public function clockIn(Request $request): JsonResponse
    {
        return $this->clock($request, true);
    }

    /** Handles the clock out operation for the current WorkIntel workflow. */ public function clockOut(Request $request): JsonResponse
    {
        return $this->clock($request, false);
    }

    /** Handles the start break operation for the current WorkIntel workflow. */ public function startBreak(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $currentMember = $request->attributes->get('workspaceMember');
        $canManage = $currentMember->hasPermission('attendance.manage');
        $data = $request->validate([
            'member_id' => ['nullable', 'integer'],
            'type' => ['sometimes', Rule::in(['break', 'lunch', 'personal', 'other'])],
            'paid' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
            'source' => ['sometimes', Rule::in(['web', 'mobile'])],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);

        $memberId = $canManage && ! empty($data['member_id']) ? (int) $data['member_id'] : $currentMember->id;
        $member = WorkspaceMember::query()->where('workspace_id', $workspace->id)->findOrFail($memberId);
        $this->assertCanManageMember($currentMember, $member);
        $today = now($workspace->timezone)->toDateString();
        $record = AttendanceRecord::query()->where('workspace_id', $workspace->id)->where('member_id', $member->id)->whereDate('date', $today)->first();

        abort_unless($record?->clock_in_at, 422, 'Clock in before starting a break.');
        abort_if($record->clock_out_at, 422, 'A break cannot start after clock out.');
        abort_if($record->breaks()->whereNull('ended_at')->exists(), 422, 'This employee already has an active break.');
        $isOwnAction = (int) $member->id === (int) $currentMember->id;
        $source = $isOwnAction ? ($data['source'] ?? 'web') : 'manual';
        $locationCheck = $isOwnAction ? $this->attendancePolicy->validateLocation($workspace, $data, $source) : $this->manualLocationCheck();

        $break = $record->breaks()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'type' => $data['type'] ?? 'break',
            'paid' => $data['paid'] ?? false,
            'started_at' => now($workspace->timezone),
            'note' => $data['note'] ?? null,
        ]);
        $this->attendancePolicy->recordEvent($workspace, $member, $record, 'break_started', $source, $locationCheck, [
            'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null,
            'accuracy_meters' => $data['accuracy_meters'] ?? null, 'break_id' => $break->id,
        ]);

        return response()->json(['data' => $break, 'location_check' => $locationCheck], 201);
    }

    /** Handles the end break operation for the current WorkIntel workflow. */ public function endBreak(Request $request, AttendanceBreak $break): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $currentMember = $request->attributes->get('workspaceMember');
        abort_unless($break->workspace_id === $workspace->id, 404);
        $targetMember = WorkspaceMember::query()->where('workspace_id', $workspace->id)->findOrFail($break->member_id);
        $this->assertCanManageMember($currentMember, $targetMember);
        abort_if($break->ended_at, 422, 'This break has already ended.');
        $data = $request->validate([
            'source' => ['sometimes', Rule::in(['web', 'mobile'])],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);
        $isOwnAction = (int) $targetMember->id === (int) $currentMember->id;
        $source = $isOwnAction ? ($data['source'] ?? 'web') : 'manual';
        $locationCheck = $isOwnAction ? $this->attendancePolicy->validateLocation($workspace, $data, $source) : $this->manualLocationCheck();

        $endedAt = now($workspace->timezone);
        $break->update([
            'ended_at' => $endedAt,
            'duration_seconds' => max(0, (int) $break->started_at->diffInSeconds($endedAt)),
        ]);
        $record = $break->attendanceRecord()->firstOrFail();
        $this->calculator->recalculate($record);
        $this->attendancePolicy->recordEvent($workspace, $targetMember, $record, 'break_ended', $source, $locationCheck, [
            'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null,
            'accuracy_meters' => $data['accuracy_meters'] ?? null, 'break_id' => $break->id,
        ]);

        return response()->json(['data' => $break->fresh(), 'location_check' => $locationCheck]);
    }

    /** Updates update data for the requested resource. */ public function update(Request $request, AttendanceRecord $record): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($record->workspace_id === $workspace->id, 404);
        $currentMember = $request->attributes->get('workspaceMember');
        $targetMember = WorkspaceMember::query()->where('workspace_id', $workspace->id)->findOrFail($record->member_id);
        $this->assertCanManageMember($currentMember, $targetMember);
        $data = $request->validate([
            'clock_in_at' => ['nullable', 'date'],
            'clock_out_at' => ['nullable', 'date', 'after:clock_in_at'],
            'status' => ['required', Rule::in(['present', 'late', 'absent', 'leave', 'wfh', 'partial', 'holiday'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! empty($data['clock_out_at'])) { $data['flag_type'] = null; $data['flagged_at'] = null; }
        $record->update($data);
        $this->calculator->recalculate($record);
        return response()->json(['data' => $record->fresh()->load('breaks')]);
    }

    /** Handles the clock operation for the current WorkIntel workflow. */ private function clock(Request $request, bool $clockIn): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $currentMember = $request->attributes->get('workspaceMember');
        $canManage = $currentMember->hasPermission('attendance.manage');
        $data = $request->validate([
            'member_id' => ['nullable', 'integer'],
            'source' => ['sometimes', Rule::in(['web', 'mobile'])],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);
        $memberId = $canManage && ! empty($data['member_id']) ? (int) $data['member_id'] : $currentMember->id;
        $member = WorkspaceMember::query()->where('workspace_id', $workspace->id)->findOrFail($memberId);
        $this->assertCanManageMember($currentMember, $member);
        $isOwnAction = (int) $member->id === (int) $currentMember->id;
        $source = $isOwnAction ? ($data['source'] ?? 'web') : 'manual';
        $locationCheck = $isOwnAction ? $this->attendancePolicy->validateLocation($workspace, $data, $source) : $this->manualLocationCheck();
        $now = now($workspace->timezone);
        $date = $now->toDateString();
        $assignment = ShiftAssignment::query()->where('workspace_id', $workspace->id)->where('member_id', $member->id)->whereDate('date', $date)->first();
        $record = AttendanceRecord::query()
            ->where('workspace_id', $workspace->id)
            ->where('member_id', $member->id)
            ->whereDate('date', $date)
            ->first();

        if (! $record) {
            $record = AttendanceRecord::create([
                'workspace_id' => $workspace->id,
                'member_id' => $member->id,
                'date' => $date,
                'shift_assignment_id' => $assignment?->id,
                'status' => 'present',
                'source' => $source,
            ]);
        } elseif (! $record->shift_assignment_id && $assignment) {
            $record->shift_assignment_id = $assignment->id;
        }

        if ($clockIn) {
            abort_if($record->clock_in_at, 422, 'This employee is already clocked in.');
            $record->clock_in_at = $now;
        } else {
            abort_unless($record->clock_in_at, 422, 'Clock in before clocking out.');
            abort_if($record->clock_out_at, 422, 'This employee is already clocked out.');
            abort_if($record->breaks()->whereNull('ended_at')->exists(), 422, 'End the active break before clocking out.');
            $record->clock_out_at = $now;
            $record->flag_type = null;
            $record->flagged_at = null;
        }

        $record->save();
        $record = $this->calculator->recalculate($record);
        $this->attendancePolicy->recordEvent($workspace, $member, $record, $clockIn ? 'clock_in' : 'clock_out', $source, $locationCheck, [
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'accuracy_meters' => $data['accuracy_meters'] ?? null,
        ]);
        return response()->json(['data' => $record, 'location_check' => $locationCheck]);
    }

    /** Handles the manual location check operation for the current WorkIntel workflow. */ private function manualLocationCheck(): array
    {
        return ['required'=>false,'provided'=>false,'accepted'=>true,'within_geofence'=>null,'location_id'=>null,'location_name'=>null,'distance_meters'=>null,'accuracy_meters'=>null];
    }

    /** Handles the assert can manage member operation for the current WorkIntel workflow. */ private function assertCanManageMember(WorkspaceMember $actor, WorkspaceMember $target): void
    {
        if ($actor->id === $target->id) return;
        abort_unless($actor->hasPermission('attendance.manage'), 403, 'You can only update your own attendance.');
        if ($actor->hasPermission('people.view_all') || $actor->hasPermission('people.manage')) return;

        abort_unless(
            in_array((int) $target->id, app(WorkScopeService::class)->teamMemberIds($actor), true),
            403,
            'This employee is outside your team attendance scope.'
        );
    }

}
