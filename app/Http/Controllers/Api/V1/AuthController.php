<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\JobTitle;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\PermissionCatalog;
use App\Services\Billing\SubscriptionService;
use App\Services\Access\RoleAccessService;
use App\Services\Attendance\AttendancePolicyService;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Security\SecurityEventService;
use App\Services\Enterprise\EnterpriseSecurityService;
use App\Services\Enterprise\TotpService;
use App\Services\Modules\WorkspaceModuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Provides auth controller behavior within the WorkIntel application. */ class AuthController extends Controller
{
    /** Handles the demo accounts operation for the current WorkIntel workflow. */ public function demoAccounts(): JsonResponse
    {
        if (! config('workintel.demo_accounts')) return response()->json(['data' => []]);
        $workspace = Workspace::query()->where('slug', 'acme-corp')->first();
        if (! $workspace) return response()->json(['data' => []]);
        $rows = WorkspaceMember::query()->with(['user:id,first_name,last_name,email','roles:id,name,slug,status'])
            ->where('workspace_id', $workspace->id)->where('status', 'active')
            ->whereHas('user', fn ($q) => $q->where('email', 'like', '%@acme.test'))
            ->orderBy('id')->get()->map(fn (WorkspaceMember $member) => [
                'email' => $member->user->email,
                'password' => 'password',
                'name' => trim($member->user->first_name.' '.$member->user->last_name),
                'role' => app(RoleAccessService::class)->primaryRoleSlug($member),
                'role_name' => $member->roles->firstWhere('slug', app(RoleAccessService::class)->primaryRoleSlug($member))?->name ?? 'Employee',
            ])->values();
        return response()->json(['data' => $rows]);
    }

    /** Handles the login operation for the current WorkIntel workflow. */ public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $candidate = User::query()->where('email', strtolower((string) $request->input('email')))->first();
            $workspace = $candidate?->memberships()->where('status', 'active')->with('workspace')->first()?->workspace;
            app(SecurityEventService::class)->record($workspace, $candidate, 'auth.login_failed', 'warning', $request);
            throw ValidationException::withMessages([
                'email' => ['The provided email or password is incorrect.'],
            ]);
        }

        $user = $request->user();
        if ($user->status !== 'active') {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages(['email' => ['This account is not active. Contact an administrator.']]);
        }
        if (! $user->memberships()->where('status', 'active')->exists()) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages(['email' => ['No active workspace access is available. Complete email verification or contact a workspace administrator.']]);
        }
        if (Schema::hasTable('workspace_security_policies') && app(EnterpriseSecurityService::class)->requiresMfa($user)) {
            $totp = app(TotpService::class);
            if (! $totp->enabled($user)) {
                Auth::guard('web')->logout();
                throw ValidationException::withMessages(['mfa_code' => ['MFA enrollment is required by workspace policy. An administrator must resolve enrollment before enforcing MFA for this account.']]);
            }
            if (! $totp->verify($user, (string) $request->input('mfa_code'))) {
                $workspace = $user->memberships()->where('status', 'active')->with('workspace')->first()?->workspace;
                app(SecurityEventService::class)->record($workspace, $user, 'auth.mfa_failed', 'warning', $request);
                Auth::guard('web')->logout();
                throw ValidationException::withMessages(['mfa_code' => ['A valid authenticator or recovery code is required.']]);
            }
        }
        if ($request->hasSession()) {
            $request->session()->regenerate();
            if (Schema::hasTable('workspace_security_policies') && app(EnterpriseSecurityService::class)->requiresMfa($user)) {
                $request->session()->put('mfa_verified_at', now()->toIso8601String());
            }
        }
        $user->forceFill(['last_login_at' => now()])->save();
        $workspace = $user->memberships()->where('status', 'active')->with('workspace')->first()?->workspace;
        app(SecurityEventService::class)->record($workspace, $user, 'auth.login_succeeded', 'info', $request);

        return response()->json(['user' => $this->userPayload($user)]);
    }

    /** Handles the register operation for the current WorkIntel workflow. */ public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'timezone' => $data['timezone'] ?? 'UTC',
                'status' => 'active',
            ]);

            $workspace = Workspace::create([
                'owner_id' => $user->id,
                'name' => $data['company_name'],
                'slug' => $this->uniqueWorkspaceSlug($data['company_name']),
                'timezone' => $data['timezone'] ?? 'UTC',
                'currency' => 'USD',
                'country' => $data['country'] ?? null,
                'week_starts_on' => 1,
                'status' => 'active',
            ]);

            $ownerJobTitle = JobTitle::create([
                'workspace_id' => $workspace->id,
                'name' => 'Workspace Owner',
                'code' => 'OWNER',
                'status' => 'active',
            ]);

            $membership = WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'job_title_id' => $ownerJobTitle->id,
                'job_title' => $ownerJobTitle->name,
                'employment_type' => 'full_time',
                'joining_date' => today(),
                'status' => 'active',
                'timezone' => $user->timezone,
            ]);

            $roles = $this->createDefaultRoles($workspace);
            app(WorkspaceModuleService::class)->initializeWorkspace($workspace);
            $membership->roles()->attach($roles['owner']);
            app(SubscriptionService::class)->ensureDefault($workspace, 'free');
            if (Schema::hasTable('attendance_policies')) app(AttendancePolicyService::class)->policy($workspace);
            if (Schema::hasTable('approval_workflows')) app(ApprovalEngine::class)->ensureDefaultWorkflows($workspace, $user->id);

            return $user;
        });

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json(['user' => $this->userPayload($user)], 201);
    }

    /** Handles the me operation for the current WorkIntel workflow. */ public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    /** Handles the logout operation for the current WorkIntel workflow. */ public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspace = $user?->memberships()->where('status', 'active')->with('workspace')->first()?->workspace;
        if ($user) app(SecurityEventService::class)->record($workspace, $user, 'auth.logout', 'info', $request);
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Signed out.']);
    }

    /** Handles the user payload operation for the current WorkIntel workflow. */ private function userPayload(User $user): array
    {
        $relations = ['workspace.subscription.plan.entitlements', 'workspace.branding', 'roles.permissions'];
        if (Schema::hasTable('workspace_preferences')) $relations[] = 'workspace.preferences';
        $memberships = $user->memberships()
            ->with($relations)
            ->where('status', 'active')
            ->whereHas('workspace', fn ($query) => $query->where('status', 'active'))
            ->get();

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'timezone' => $user->timezone,
            'locale' => $user->locale,
            'use_workspace_locale' => (bool) ($user->use_workspace_locale ?? true),
            'email_verified' => (bool) $user->email_verified_at,
            'force_password_change' => (bool) $user->force_password_change,
            'platform_operator' => app(\App\Services\Commerce\PlatformOperatorService::class)->isOperator($user),
            'mfa_enabled' => Schema::hasTable('user_mfa_methods') ? app(TotpService::class)->enabled($user) : false,
            'workspaces' => $memberships->map(fn (WorkspaceMember $member) => [
                'id' => $member->workspace->id,
                'name' => $member->workspace->name,
                'slug' => $member->workspace->slug,
                'plan' => $member->workspace->subscription?->plan?->name ?? 'Free',
                'entitlements' => $member->workspace->subscription?->plan?->entitlements?->mapWithKeys(fn ($item) => [$item->key => $item->resolvedValue()])->all() ?? [],
                'role' => app(RoleAccessService::class)->primaryRoleSlug($member),
                'roles' => app(RoleAccessService::class)->effectiveRoles($member),
                'permissions' => app(RoleAccessService::class)->effectivePermissions($member),
                'member_id' => $member->id,
                'workspace_type' => $member->workspace->workspace_type ?? 'production',
                'branding' => $member->workspace->branding ? [
                    'product_name' => $member->workspace->branding->product_name,
                    'accent_color' => $member->workspace->branding->accent_color,
                    'hide_powered_by' => (bool) $member->workspace->branding->hide_powered_by,
                ] : null,
                'settings' => ($preference = (Schema::hasTable('workspace_preferences') ? $member->workspace->preferences : null)) ? [
                    'app_title' => $preference->app_title ?: $member->workspace->name,
                    'accent_color' => $preference->accent_color,
                    'logo_url' => $preference->logo_path ? '/api/v1/settings/assets/'.$preference->uuid.'/logo' : null,
                    'favicon_url' => $preference->favicon_path ? '/api/v1/settings/assets/'.$preference->uuid.'/favicon' : null,
                    'default_theme' => $preference->default_theme,
                    'sidebar_density' => $preference->sidebar_density,
                    'default_language' => $preference->default_language,
                    'date_format' => $preference->date_format,
                    'time_format' => $preference->time_format,
                    'number_format' => $preference->number_format,
                    'week_starts_on' => (int) $member->workspace->week_starts_on,
                    'fiscal_year_start_month' => (int) $preference->fiscal_year_start_month,
                    'currency' => $member->workspace->currency,
                    'timezone' => $member->workspace->timezone,
                ] : null,
                'modules' => app(WorkspaceModuleService::class)->authMap($member->workspace),
            ])->values(),
        ];
    }

    /**
     * Create the standard workspace roles with practical starting permissions.
     * Custom roles can be added later without changing application code.
     *
     * @return array<string, Role>
     */
    /** Creates create default roles data for the requested workflow. */ private function createDefaultRoles(Workspace $workspace): array
    {
        $allPermissionIds = PermissionCatalog::sync();

        $definitions = [
            'owner' => [
                'name' => 'Owner',
                'permissions' => null,
            ],
            'admin' => [
                'name' => 'Admin',
                'permissions' => null,
            ],
            'hr' => [
                'name' => 'HR',
                'permissions' => [
                    'people.view_all', 'people.manage', 'organization.view', 'organization.manage',
                    'attendance.view_own', 'attendance.view_team', 'attendance.manage', 'attendance.policy_manage', 'scheduling.view_own', 'scheduling.view_team', 'scheduling.manage',
                    'time.view_own', 'time.view_team', 'reports.view', 'reports.manage', 'documents.view', 'documents.generate', 'devices.view', 'approvals.view_own', 'approvals.review', 'approvals.audit',
                    'hris.view_own', 'hris.view_team', 'hris.view_all', 'hris.manage', 'hris.documents.manage', 'hris.assets.manage', 'hris.policies.manage', 'hris.lifecycle.manage',
                    'performance.view_own', 'performance.view_team', 'performance.view_all', 'performance.manage', 'performance.reviews.manage', 'performance.skills.manage', 'performance.learning.manage', 'performance.surveys.manage', 'performance.compensation.manage',
                    'expenses.view_own', 'expenses.view_team', 'field.view_own', 'field.view_team', 'field.incidents.manage', 'intelligence.view_own', 'intelligence.view_team', 'intelligence.view_all',
                ],
            ],
            'manager' => [
                'name' => 'Manager',
                'permissions' => [
                    'people.view_team', 'organization.view', 'projects.view_all', 'projects.manage',
                    'tasks.view_all', 'tasks.manage', 'time.view_own', 'time.view_team', 'time.manage',
                    'attendance.view_own', 'attendance.view_team', 'attendance.manage', 'scheduling.view_own', 'scheduling.view_team', 'scheduling.manage',
                    'activity.view_own', 'activity.view_team', 'screenshots.view_team', 'reports.view', 'reports.manage', 'documents.view', 'approvals.view_own', 'approvals.review',
                    'hris.view_own', 'hris.view_team', 'performance.view_own', 'performance.view_team', 'performance.manage', 'performance.reviews.manage',
                    'expenses.view_own', 'expenses.view_team', 'procurement.view', 'procurement.request', 'procurement.manage', 'job_costing.view',
                    'field.view_own', 'field.view_team', 'field.manage', 'field.incidents.manage', 'intelligence.view_own', 'intelligence.view_team',
                ],
            ],
            'team-lead' => [
                'name' => 'Team Lead',
                'permissions' => [
                    'people.view_team', 'organization.view', 'projects.view_assigned', 'tasks.view_team', 'tasks.manage_team',
                    'time.view_own', 'time.view_team', 'attendance.view_own', 'attendance.view_team', 'scheduling.view_own', 'scheduling.view_team',
                    'activity.view_own', 'activity.view_team', 'screenshots.view_team', 'reports.view', 'documents.view', 'approvals.view_own', 'approvals.review',
                    'hris.view_own', 'hris.view_team', 'performance.view_own', 'performance.view_team',
                    'expenses.view_own', 'expenses.view_team', 'procurement.view', 'procurement.request',
                    'field.view_own', 'field.view_team', 'field.incidents.manage', 'intelligence.view_own', 'intelligence.view_team',
                ],
            ],
            'payroll-manager' => [
                'name' => 'Payroll Manager',
                'permissions' => [
                    'people.view_all', 'organization.view', 'time.view_all', 'attendance.view_own', 'attendance.view_team', 'scheduling.view_own', 'scheduling.view_team',
                    'payroll.view_own', 'payroll.view_all', 'payroll.manage', 'reports.view', 'reports.manage', 'documents.view', 'documents.generate', 'approvals.view_own', 'approvals.review', 'approvals.audit',
                    'performance.view_own', 'performance.view_all', 'performance.compensation.manage', 'expenses.view_own', 'expenses.manage', 'job_costing.view',
                    'payroll.compliance.view', 'payroll.compliance.manage', 'payroll.exports.manage', 'payroll.contractors.manage', 'intelligence.view_own', 'intelligence.view_all',
                ],
            ],
            'employee' => [
                'name' => 'Employee',
                'permissions' => [
                    'projects.view_assigned', 'tasks.view_own', 'time.view_own',
                    'attendance.view_own', 'scheduling.view_own', 'activity.view_own', 'screenshots.view_own',
                    'payroll.view_own', 'approvals.view_own', 'hris.view_own', 'performance.view_own', 'expenses.view_own', 'procurement.request', 'field.view_own', 'intelligence.view_own',
                ],
            ],
            'client' => [
                'name' => 'Client (Legacy)',
                'permissions' => [],
            ],
        ];

        $roles = [];

        foreach ($definitions as $slug => $definition) {
            $role = Role::create([
                'workspace_id' => $workspace->id,
                'name' => $definition['name'],
                'slug' => $slug,
                'is_system' => true,
            ]);

            $permissionIds = $definition['permissions'] === null
                ? $allPermissionIds
                : Permission::query()->whereIn('slug', $definition['permissions'])->pluck('id');

            $role->permissions()->sync($permissionIds);
            $roles[$slug] = $role;
        }

        return $roles;
    }

    /** Handles the unique workspace slug operation for the current WorkIntel workflow. */ private function uniqueWorkspaceSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $suffix = 2;

        while (Workspace::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
