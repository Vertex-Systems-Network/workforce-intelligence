<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\JobTitle;
use App\Models\Team;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Provides organization controller behavior within the WorkIntel application. */ class OrganizationController extends Controller
{
    /** Returns the requested resource collection. */ public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $departments = Department::query()
            ->withCount('members')
            ->where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->get();

        $jobTitles = JobTitle::query()
            ->withCount('members')
            ->where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->get();

        $teams = Team::query()
            ->with([
                'department:id,name',
                'lead.user:id,first_name,last_name',
                'members.user:id,first_name,last_name',
            ])
            ->withCount('members')
            ->where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->get();

        $people = WorkspaceMember::query()
            ->with('user:id,first_name,last_name')
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->map(fn (WorkspaceMember $member) => [
                'id' => $member->id,
                'name' => trim($member->user->first_name.' '.$member->user->last_name),
            ]);

        return response()->json([
            'departments' => $departments,
            'job_titles' => $jobTitles,
            'teams' => $teams,
            'people' => $people,
        ]);
    }

    /** Handles the store department operation for the current WorkIntel workflow. */ public function storeDepartment(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $this->validateDepartment($request, $workspace->id);

        $department = Department::create(['workspace_id' => $workspace->id, ...$data]);

        return response()->json(['data' => $department->loadCount('members')], 201);
    }

    /** Updates update department data for the requested resource. */ public function updateDepartment(Request $request, Department $department): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureWorkspaceRecord($workspace->id, $department->workspace_id);
        $department->update($this->validateDepartment($request, $workspace->id, $department->id));

        return response()->json(['data' => $department->fresh()->loadCount('members')]);
    }

    /** Handles the destroy department operation for the current WorkIntel workflow. */ public function destroyDepartment(Request $request, Department $department): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureWorkspaceRecord($workspace->id, $department->workspace_id);

        abort_if($department->members()->exists(), 422, 'Move employees out of this department before deleting it.');
        abort_if(Team::where('department_id', $department->id)->exists(), 422, 'Move or delete teams assigned to this department first.');
        $department->delete();

        return response()->json(['message' => 'Department deleted.']);
    }

    /** Handles the store job title operation for the current WorkIntel workflow. */ public function storeJobTitle(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $this->validateJobTitle($request, $workspace->id);
        $jobTitle = JobTitle::create(['workspace_id' => $workspace->id, ...$data]);

        return response()->json(['data' => $jobTitle->loadCount('members')], 201);
    }

    /** Updates update job title data for the requested resource. */ public function updateJobTitle(Request $request, JobTitle $jobTitle): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureWorkspaceRecord($workspace->id, $jobTitle->workspace_id);
        $jobTitle->update($this->validateJobTitle($request, $workspace->id, $jobTitle->id));

        WorkspaceMember::query()
            ->where('job_title_id', $jobTitle->id)
            ->update(['job_title' => $jobTitle->name]);

        return response()->json(['data' => $jobTitle->fresh()->loadCount('members')]);
    }

    /** Handles the destroy job title operation for the current WorkIntel workflow. */ public function destroyJobTitle(Request $request, JobTitle $jobTitle): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureWorkspaceRecord($workspace->id, $jobTitle->workspace_id);
        abort_if($jobTitle->members()->exists(), 422, 'Reassign employees using this job title before deleting it.');
        $jobTitle->delete();

        return response()->json(['message' => 'Job title deleted.']);
    }

    /** Handles the store team operation for the current WorkIntel workflow. */ public function storeTeam(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $this->validateTeam($request, $workspace->id);

        $team = DB::transaction(function () use ($workspace, $data) {
            $team = Team::create([
                'workspace_id' => $workspace->id,
                ...collect($data)->except('member_ids')->all(),
            ]);
            $team->members()->sync($data['member_ids'] ?? []);
            return $team;
        });

        return response()->json(['data' => $this->loadTeam($team)], 201);
    }

    /** Updates update team data for the requested resource. */ public function updateTeam(Request $request, Team $team): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureWorkspaceRecord($workspace->id, $team->workspace_id);
        $data = $this->validateTeam($request, $workspace->id, $team->id);

        DB::transaction(function () use ($team, $data) {
            $team->update(collect($data)->except('member_ids')->all());
            if (array_key_exists('member_ids', $data)) {
                $team->members()->sync($data['member_ids']);
            }
        });

        return response()->json(['data' => $this->loadTeam($team->fresh())]);
    }

    /** Handles the destroy team operation for the current WorkIntel workflow. */ public function destroyTeam(Request $request, Team $team): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureWorkspaceRecord($workspace->id, $team->workspace_id);
        $team->delete();

        return response()->json(['message' => 'Team deleted.']);
    }

    /** Validates validate department input before it is processed. */ private function validateDepartment(Request $request, int $workspaceId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->where('workspace_id', $workspaceId)->ignore($ignoreId)],
            'code' => ['nullable', 'string', 'max:32'],
        ]);
    }

    /** Validates validate job title input before it is processed. */ private function validateJobTitle(Request $request, int $workspaceId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('job_titles', 'name')->where('workspace_id', $workspaceId)->ignore($ignoreId)],
            'code' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    /** Validates validate team input before it is processed. */ private function validateTeam(Request $request, int $workspaceId, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('teams', 'name')->where('workspace_id', $workspaceId)->ignore($ignoreId)],
            'code' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:2000'],
            'department_id' => ['nullable', 'integer'],
            'lead_id' => ['nullable', 'integer'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'member_ids' => ['sometimes', 'array'],
            'member_ids.*' => ['integer'],
        ]);

        if (! empty($data['department_id'])) {
            Department::query()->where('workspace_id', $workspaceId)->findOrFail($data['department_id']);
        }

        $memberIds = array_values(array_unique(array_filter([
            ...($data['member_ids'] ?? []),
            $data['lead_id'] ?? null,
        ])));

        if ($memberIds) {
            $validCount = WorkspaceMember::query()->where('workspace_id', $workspaceId)->whereIn('id', $memberIds)->count();
            if ($validCount !== count($memberIds)) {
                throw ValidationException::withMessages(['member_ids' => ['One or more selected team members do not belong to this workspace.']]);
            }
        }

        if (! empty($data['lead_id']) && ! in_array($data['lead_id'], $data['member_ids'] ?? [], true)) {
            $data['member_ids'][] = $data['lead_id'];
        }

        return $data;
    }

    /** Handles the load team operation for the current WorkIntel workflow. */ private function loadTeam(Team $team): Team
    {
        return $team->load(['department:id,name', 'lead.user:id,first_name,last_name', 'members.user:id,first_name,last_name'])->loadCount('members');
    }

    /** Handles the ensure workspace record operation for the current WorkIntel workflow. */ private function ensureWorkspaceRecord(int $workspaceId, int $recordWorkspaceId): void
    {
        abort_unless($workspaceId === $recordWorkspaceId, 404);
    }
}
