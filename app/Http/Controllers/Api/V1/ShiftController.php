<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\WorkspaceMember;
use App\Services\Access\WorkScopeService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Provides shift controller behavior within the WorkIntel application. */ class ShiftController extends Controller
{
    /** Returns the requested resource collection. */ public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $currentMember = $request->attributes->get('workspaceMember');
        $canViewAll = $currentMember->hasPermission('people.view_all') || $currentMember->hasPermission('people.manage');
        $visibleMemberIds = $canViewAll ? WorkspaceMember::query()->where('workspace_id', $workspace->id)->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all() : app(WorkScopeService::class)->teamMemberIds($currentMember);
        $start = $request->query('start') ? Carbon::parse($request->query('start'))->startOfDay() : now($workspace->timezone)->startOfWeek();
        $end = $start->copy()->addDays(6)->endOfDay();

        $shifts = Shift::query()->where('workspace_id', $workspace->id)->where('status', '!=', 'archived')->orderBy('name')->get();
        $assignments = ShiftAssignment::query()
            ->with(['shift', 'member.user:id,first_name,last_name', 'member.department:id,name'])
            ->where('workspace_id', $workspace->id)
            ->whereIn('member_id', $visibleMemberIds)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->orderBy('date')
            ->get();

        $people = WorkspaceMember::query()
            ->with(['user:id,first_name,last_name', 'department:id,name'])
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->whereIn('id', $visibleMemberIds)
            ->orderBy('id')->get();

        return response()->json([
            'week_start' => $start->toDateString(),
            'week_end' => $end->toDateString(),
            'shifts' => $shifts,
            'assignments' => $assignments,
            'people' => $people,
        ]);
    }

    /** Creates and persists the requested resource. */ public function store(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $this->validateShift($request);
        $shift = Shift::create(['workspace_id' => $workspace->id, 'timezone' => $data['timezone'] ?? $workspace->timezone, ...$data]);
        return response()->json(['data' => $shift], 201);
    }

    /** Updates update data for the requested resource. */ public function update(Request $request, Shift $shift): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($shift->workspace_id === $workspace->id, 404);
        $shift->update($this->validateShift($request));
        return response()->json(['data' => $shift->fresh()]);
    }

    /** Removes destroy data from the requested resource. */ public function destroy(Request $request, Shift $shift): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($shift->workspace_id === $workspace->id, 404);
        $shift->update(['status' => 'archived']);
        return response()->json(['message' => 'Shift archived.']);
    }

    /** Handles the assign operation for the current WorkIntel workflow. */ public function assign(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'shift_id' => ['required', 'integer'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer'],
            'dates' => ['required', 'array', 'min:1', 'max:31'],
            'dates.*' => ['date'],
            'work_mode' => ['nullable', Rule::in(['office', 'remote', 'hybrid', 'field'])],
        ]);

        $shift = Shift::query()->where('workspace_id', $workspace->id)->findOrFail($data['shift_id']);
        $currentMember = $request->attributes->get('workspaceMember');
        $validMembers = WorkspaceMember::query()->where('workspace_id', $workspace->id)->whereIn('id', $data['member_ids'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($validMembers) !== count(array_unique($data['member_ids']))) {
            throw ValidationException::withMessages(['member_ids' => ['One or more employees do not belong to this workspace.']]);
        }
        if (! $currentMember->hasPermission('people.view_all') && ! $currentMember->hasPermission('people.manage')) {
            $allowed = app(WorkScopeService::class)->teamMemberIds($currentMember);
            if (collect($validMembers)->contains(fn ($id) => ! in_array((int) $id, $allowed, true))) {
                throw ValidationException::withMessages(['member_ids' => ['You can only assign shifts to employees in your team scope.']]);
            }
        }

        $count = 0;
        foreach ($validMembers as $memberId) {
            foreach (array_unique($data['dates']) as $date) {
                $date = Carbon::parse($date)->toDateString();
                // SQL DATE is normalized by DateOnly. Use an exact comparison here rather
                // than whereDate(), which can produce driver-specific SQL around SQLite DATE
                // strings and previously let an existing member/date row slip through.
                $assignment = ShiftAssignment::query()
                    ->where('member_id', $memberId)
                    ->where('date', $date)
                    ->first();

                $values = [
                    'workspace_id' => $workspace->id,
                    'shift_id' => $shift->id,
                    'work_mode' => $data['work_mode'] ?? $shift->location_type,
                ];

                if ($assignment) {
                    $assignment->update($values);
                } else {
                    try {
                        ShiftAssignment::create([
                            ...$values,
                            'member_id' => $memberId,
                            'date' => $date,
                        ]);
                    } catch (QueryException $e) {
                        // Concurrent schedule writers can race on the unique member/date key.
                        // Resolve and update the winning row instead of returning a 500.
                        $assignment = ShiftAssignment::query()
                            ->where('member_id', $memberId)
                            ->where('date', $date)
                            ->first();
                        if (! $assignment) throw $e;
                        $assignment->update($values);
                    }
                }
                $count++;
            }
        }

        return response()->json(['assigned' => $count]);
    }

    /** Handles the destroy assignment operation for the current WorkIntel workflow. */ public function destroyAssignment(Request $request, ShiftAssignment $assignment): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($assignment->workspace_id === $workspace->id, 404);
        $currentMember = $request->attributes->get('workspaceMember');
        if (! $currentMember->hasPermission('people.view_all') && ! $currentMember->hasPermission('people.manage')) {
            abort_unless(in_array((int) $assignment->member_id, app(WorkScopeService::class)->teamMemberIds($currentMember), true), 403, 'This shift assignment is outside your team scope.');
        }
        $assignment->delete();
        return response()->json(['message' => 'Shift assignment removed.']);
    }

    /** Validates validate shift input before it is processed. */ private function validateShift(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'location_type' => ['required', Rule::in(['office', 'remote', 'hybrid', 'field'])],
            'timezone' => ['nullable', 'string', 'max:64'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'archived'])],
        ]);
    }
}
