<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Billing\EntitlementService;
use App\Services\Access\WorkScopeService;
use App\Services\Access\RoleAccessService;
use App\Http\Requests\People\PersonRequest;
use App\Models\Department;
use App\Models\JobTitle;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Provides people controller behavior within the WorkIntel application. */ class PeopleController extends Controller
{
    /** Returns the requested resource collection. */ public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $viewer = $request->attributes->get('workspaceMember');
        $query = WorkspaceMember::query()
            ->with(['user', 'department', 'jobTitle', 'manager.user', 'roles'])
            ->where('workspace_id', $workspace->id);

        if (! $viewer->hasPermission('people.manage')) {
            $query->where('status', '!=', 'archived');
        }

        $members = app(WorkScopeService::class)
            ->scopePeople($query, $viewer)
            ->orderBy('id')
            ->get()
            ->map(fn (WorkspaceMember $member) => $this->payload($member));

        return response()->json(['data' => $members]);
    }

    /** Handles the options operation for the current WorkIntel workflow. */ public function options(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'departments' => Department::query()
                ->where('workspace_id', $workspace->id)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'job_titles' => JobTitle::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'roles' => Role::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'managers' => WorkspaceMember::query()
                ->with('user:id,first_name,last_name')
                ->where('workspace_id', $workspace->id)
                ->where('status', 'active')
                ->orderBy('id')
                ->get()
                ->map(fn (WorkspaceMember $member) => [
                    'id' => $member->id,
                    'name' => trim($member->user->first_name.' '.$member->user->last_name),
                ]),
        ]);
    }

    /** Creates and persists the requested resource. */ public function store(PersonRequest $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        app(EntitlementService::class)->assertWithinLimit($workspace, 'members', $workspace->members()->where('status', 'active')->count());
        $data = $request->validated();

        $department = $this->departmentForWorkspace($workspace->id, $data['department_id'] ?? null);
        $manager = $this->managerForWorkspace($workspace->id, $data['manager_id'] ?? null);
        $jobTitle = $this->jobTitleForWorkspace($workspace->id, $data['job_title_id'] ?? null);
        $roles = $this->rolesForWorkspace($workspace->id, $data['role_slugs'] ?? [$data['role_slug'] ?? 'employee']);

        $member = DB::transaction(function () use ($workspace, $data, $department, $manager, $jobTitle, $roles) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'avatar_url' => $data['avatar_url'] ?? null,
                'password' => $data['password'],
                'timezone' => $data['timezone'] ?? $workspace->timezone,
                'locale' => $data['locale'] ?? ($workspace->preferences?->default_language ?: 'en'),
                'use_workspace_locale' => true,
                'status' => 'active',
                'force_password_change' => true,
                'password_changed_at' => now(),
            ]);

            $member = WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'employee_code' => $data['employee_code'] ?? null,
                'job_title_id' => $jobTitle?->id,
                'job_title' => $jobTitle?->name ?? ($data['job_title'] ?? null),
                'department_id' => $department?->id,
                'manager_id' => $manager?->id,
                'employment_type' => $data['employment_type'],
                'joining_date' => $data['joining_date'] ?? today(),
                'status' => $data['status'] ?? 'active',
                'timezone' => $data['timezone'] ?? $workspace->timezone,
            ]);

            if ($roles->isNotEmpty()) {
                $sync=[]; foreach($roles as $index=>$role)$sync[$role->id]=['is_primary'=>$index===0,'assigned_by'=>$workspace->owner_id];
                $member->roles()->sync($sync);
            }

            return $member;
        });

        return response()->json(['data' => $this->payload($member->load(['user', 'department', 'jobTitle', 'manager.user', 'roles']))], 201);
    }

    /** Updates update data for the requested resource. */ public function update(PersonRequest $request, WorkspaceMember $member): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureWorkspaceMember($workspace->id, $member);
        $data = $request->validated();

        if ($member->status->value !== 'active' && ($data['status'] ?? null) === 'active') {
            app(EntitlementService::class)->assertWithinLimit($workspace, 'members', $workspace->members()->where('status', 'active')->count());
        }

        $department = $this->departmentForWorkspace($workspace->id, $data['department_id'] ?? null);
        $manager = $this->managerForWorkspace($workspace->id, $data['manager_id'] ?? null);
        $jobTitle = $this->jobTitleForWorkspace($workspace->id, $data['job_title_id'] ?? null);
        $roles = isset($data['role_slugs']) ? $this->rolesForWorkspace($workspace->id, $data['role_slugs']) : null;
        $legacyRole = (! $roles && isset($data['role_slug']) && $member->roles()->count() <= 1) ? $this->roleForWorkspace($workspace->id, $data['role_slug']) : null;

        if ($manager?->id === $member->id) {
            throw ValidationException::withMessages(['manager_id' => ['An employee cannot be their own manager.']]);
        }

        DB::transaction(function () use ($member, $data, $department, $manager, $jobTitle, $roles, $legacyRole, $workspace) {
            $userData = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'avatar_url' => $data['avatar_url'] ?? null,
                'timezone' => $data['timezone'] ?? $member->timezone,
                'locale' => $data['locale'] ?? $member->user->locale,
            ];

            if (! empty($data['password'])) {
                $userData['password'] = $data['password'];
            }

            $member->user->update($userData);
            $member->update([
                'employee_code' => $data['employee_code'] ?? null,
                'job_title_id' => $jobTitle?->id,
                'job_title' => $jobTitle?->name ?? ($data['job_title'] ?? null),
                'department_id' => $department?->id,
                'manager_id' => $manager?->id,
                'employment_type' => $data['employment_type'],
                'joining_date' => $data['joining_date'] ?? null,
                'status' => $data['status'] ?? 'active',
                'timezone' => $data['timezone'] ?? $member->timezone,
            ]);

            if ($roles) {
                $sync=[]; foreach($roles as $index=>$role)$sync[$role->id]=['is_primary'=>$index===0,'assigned_by'=>$workspace->owner_id];
                $member->roles()->sync($sync);
            } elseif ($legacyRole) {
                $member->roles()->sync([$legacyRole->id => ['is_primary'=>true,'assigned_by'=>$workspace->owner_id]]);
            }
        });

        return response()->json(['data' => $this->payload($member->fresh()->load(['user', 'department', 'jobTitle', 'manager.user', 'roles']))]);
    }

    /** Removes destroy data from the requested resource. */ public function destroy(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $currentMember = $request->attributes->get('workspaceMember');
        $this->ensureWorkspaceMember($workspace->id, $member);

        abort_if($member->id === $currentMember->id, 422, 'You cannot deactivate your own workspace membership.');

        $member->update(['status' => 'archived']);

        return response()->json(['message' => 'Employee archived.']);
    }

    /** Handles the payload operation for the current WorkIntel workflow. */ private function payload(WorkspaceMember $member): array
    {
        return [
            'id' => $member->id,
            'first_name' => $member->user->first_name,
            'last_name' => $member->user->last_name,
            'name' => trim($member->user->first_name.' '.$member->user->last_name),
            'email' => $member->user->email,
            'phone' => $member->user->phone,
            'avatar_url' => $member->user->avatar_url,
            'locale' => $member->user->locale,
            'email_verified' => (bool) $member->user->email_verified_at,
            'force_password_change' => (bool) $member->user->force_password_change,
            'employee_code' => $member->employee_code,
            'job_title_id' => $member->job_title_id,
            'job_title' => $member->jobTitle?->name ?? $member->job_title,
            'department_id' => $member->department_id,
            'department' => $member->department?->name,
            'manager_id' => $member->manager_id,
            'manager' => $member->manager ? trim($member->manager->user->first_name.' '.$member->manager->user->last_name) : null,
            'employment_type' => $member->employment_type,
            'status' => $member->status->value,
            'roles' => $member->roles->pluck('slug')->values(),
            'joining_date' => $member->joining_date?->toDateString(),
            'timezone' => $member->timezone,
        ];
    }

    /** Handles the ensure workspace member operation for the current WorkIntel workflow. */ private function ensureWorkspaceMember(int $workspaceId, WorkspaceMember $member): void
    {
        abort_unless($member->workspace_id === $workspaceId, 404);
    }

    /** Handles the department for workspace operation for the current WorkIntel workflow. */ private function departmentForWorkspace(int $workspaceId, ?int $departmentId): ?Department
    {
        if (! $departmentId) return null;

        return Department::query()->where('workspace_id', $workspaceId)->findOrFail($departmentId);
    }


    /** Handles the job title for workspace operation for the current WorkIntel workflow. */ private function jobTitleForWorkspace(int $workspaceId, ?int $jobTitleId): ?JobTitle
    {
        if (! $jobTitleId) return null;

        return JobTitle::query()->where('workspace_id', $workspaceId)->findOrFail($jobTitleId);
    }

    /** Handles the manager for workspace operation for the current WorkIntel workflow. */ private function managerForWorkspace(int $workspaceId, ?int $managerId): ?WorkspaceMember
    {
        if (! $managerId) return null;

        return WorkspaceMember::query()->where('workspace_id', $workspaceId)->findOrFail($managerId);
    }

    /** Handles the roles for workspace operation for the current WorkIntel workflow. */ private function rolesForWorkspace(int $workspaceId, array $slugs)
    {
        $slugs = array_values(array_unique(array_filter($slugs)));
        $roles = Role::query()->where('workspace_id', $workspaceId)->where('status', 'active')->whereIn('slug', $slugs)->get();
        if ($roles->count() !== count($slugs)) {
            throw ValidationException::withMessages(['role_slugs' => ['One or more roles are invalid or archived.']]);
        }
        return $roles->sortBy(fn (Role $role) => array_search($role->slug, $slugs, true))->values();
    }

    /** Handles the role for workspace operation for the current WorkIntel workflow. */ private function roleForWorkspace(int $workspaceId, ?string $slug): ?Role
    {
        if (! $slug) return null;

        return Role::query()->where('workspace_id', $workspaceId)->where('status','active')->where('slug', $slug)->firstOrFail();
    }
}
