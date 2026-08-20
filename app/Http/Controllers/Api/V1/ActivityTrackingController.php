<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityTrackingSetting;
use App\Models\ApplicationSession;
use App\Models\BrowserConnection;
use App\Models\BrowserEnrollment;
use App\Models\ProductivityRule;
use App\Models\TrackingExclusion;
use App\Models\WebsiteSession;
use App\Models\WorkspaceMember;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Provides activity tracking controller behavior within the WorkIntel application. */ class ActivityTrackingController extends Controller
{
    /** Returns the requested resource collection. */ public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        [$from, $to] = $this->dateRange($request);
        $memberIds = $this->visibleMemberIds($viewer);
        $canManage = $viewer->hasPermission('activity.manage');
        $canManageRules = $viewer->hasPermission('activity.rules_manage');

        $apps = ApplicationSession::query()
            ->with('member.teams')
            ->where('workspace_id', $workspace->id)
            ->whereIn('member_id', $memberIds)
            ->whereBetween('started_at', [$from, $to])
            ->get();
        $websites = WebsiteSession::query()
            ->with('member.teams')
            ->where('workspace_id', $workspace->id)
            ->whereIn('member_id', $memberIds)
            ->whereBetween('started_at', [$from, $to])
            ->get();

        $rules = ProductivityRule::query()->where('workspace_id', $workspace->id)->where('active', true)->get();
        $directory = collect();
        $productiveSeconds = 0;
        $classifiedSeconds = 0;

        foreach ($apps as $session) {
            [$classification, $category] = $this->classificationFor($rules, 'app', $session->app_key, $session->member, $session->project_id);
            $key = 'app:'.$session->app_key;
            $row = $directory->get($key, $this->emptyDirectoryRow('app', $session->app_name, $session->app_key));
            $row['seconds'] += $session->duration_seconds;
            $row['active_seconds'] += $session->active_seconds;
            $row['people'][$session->member_id] = true;
            $row['classification_counts'][$classification] = ($row['classification_counts'][$classification] ?? 0) + $session->duration_seconds;
            if ($category) $row['category_counts'][$category] = ($row['category_counts'][$category] ?? 0) + $session->duration_seconds;
            $directory->put($key, $row);
            if ($classification !== 'unclassified') $classifiedSeconds += $session->duration_seconds;
            if ($classification === 'productive') $productiveSeconds += $session->duration_seconds;
        }

        foreach ($websites as $session) {
            [$classification, $category] = $this->classificationFor($rules, 'domain', $session->domain, $session->member, $session->project_id);
            $key = 'domain:'.$session->domain;
            $row = $directory->get($key, $this->emptyDirectoryRow('domain', $session->domain, $session->domain));
            $row['seconds'] += $session->duration_seconds;
            $row['active_seconds'] += $session->active_seconds;
            $row['people'][$session->member_id] = true;
            $row['classification_counts'][$classification] = ($row['classification_counts'][$classification] ?? 0) + $session->duration_seconds;
            if ($category) $row['category_counts'][$category] = ($row['category_counts'][$category] ?? 0) + $session->duration_seconds;
            $directory->put($key, $row);
            if ($classification !== 'unclassified') $classifiedSeconds += $session->duration_seconds;
            if ($classification === 'productive') $productiveSeconds += $session->duration_seconds;
        }

        $totalSeconds = $apps->sum('duration_seconds') + $websites->sum('duration_seconds');
        $rows = $directory->values()->map(function (array $row) use ($totalSeconds) {
            arsort($row['classification_counts']);
            arsort($row['category_counts']);
            $classification = array_key_first($row['classification_counts']) ?: 'unclassified';
            $category = array_key_first($row['category_counts']) ?: $this->defaultCategory($row['type'], $row['name']);
            return [
                'type' => $row['type'],
                'name' => $row['name'],
                'target' => $row['target'],
                'category' => $category,
                'seconds' => $row['seconds'],
                'active_seconds' => $row['active_seconds'],
                'share' => $totalSeconds > 0 ? round(($row['seconds'] / $totalSeconds) * 100, 1) : 0,
                'people' => count($row['people']),
                'classification' => $classification,
            ];
        })->sortByDesc('seconds')->values();

        $settings = ActivityTrackingSetting::firstOrCreate(['workspace_id' => $workspace->id]);
        $connections = BrowserConnection::query()->with('member.user')->where('workspace_id', $workspace->id)
            ->when(! $canManage, fn ($query) => $query->where('member_id', $viewer->id))
            ->orderByDesc('last_seen_at')->get();

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'stats' => [
                'tracked_seconds' => $totalSeconds,
                'productive_percent' => $classifiedSeconds > 0 ? round(($productiveSeconds / $classifiedSeconds) * 100, 1) : 0,
                'applications' => $apps->pluck('app_key')->unique()->count(),
                'domains' => $websites->pluck('domain')->unique()->count(),
            ],
            'usage' => $rows,
            'rules' => $canManageRules ? $rules->map(fn ($rule) => $this->rulePayload($rule))->values() : [],
            'exclusions' => $canManage ? TrackingExclusion::query()->where('workspace_id', $workspace->id)->latest()->get() : [],
            'settings' => $settings,
            'browser_connections' => $connections->map(fn (BrowserConnection $connection) => [
                'id' => $connection->id,
                'uuid' => $connection->uuid,
                'employee' => trim(($connection->member?->user?->first_name ?? '').' '.($connection->member?->user?->last_name ?? '')),
                'browser_name' => $connection->browser_name,
                'browser_version' => $connection->browser_version,
                'extension_version' => $connection->extension_version,
                'status' => $connection->status,
                'last_seen_at' => optional($connection->last_seen_at)->toIso8601String(),
                'last_sync_at' => optional($connection->last_sync_at)->toIso8601String(),
                'revoked_at' => optional($connection->revoked_at)->toIso8601String(),
            ])->values(),
            'members' => WorkspaceMember::query()->with('user')->where('workspace_id', $workspace->id)->whereIn('id', $canManage ? WorkspaceMember::query()->where('workspace_id', $workspace->id)->pluck('id') : $memberIds)->where('status', 'active')->get()->map(fn ($member) => [
                'id' => $member->id,
                'name' => trim($member->user->first_name.' '.$member->user->last_name),
            ])->values(),
            'scope_options' => $canManage ? [
                'departments' => \App\Models\Department::query()->where('workspace_id', $workspace->id)->orderBy('name')->get(['id', 'name']),
                'teams' => \App\Models\Team::query()->where('workspace_id', $workspace->id)->orderBy('name')->get(['id', 'name']),
                'projects' => \App\Models\Project::query()->where('workspace_id', $workspace->id)->where('status', '!=', 'archived')->orderBy('name')->get(['id', 'name']),
            ] : ['departments' => [], 'teams' => [], 'projects' => []],
        ]);
    }

    /** Handles the sessions operation for the current WorkIntel workflow. */ public function sessions(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        [$from, $to] = $this->dateRange($request);
        $memberIds = $this->visibleMemberIds($viewer);
        $data = $request->validate([
            'type' => ['required', Rule::in(['app', 'domain'])],
            'target' => ['required', 'string', 'max:253'],
        ]);
        $target = $this->normalizeTarget($data['type'], $data['target']);

        if ($data['type'] === 'app') {
            $rows = ApplicationSession::query()
                ->with(['member.user', 'device', 'project', 'task'])
                ->where('workspace_id', $workspace->id)
                ->whereIn('member_id', $memberIds)
                ->where('app_key', $target)
                ->whereBetween('started_at', [$from, $to])
                ->latest('started_at')
                ->limit(200)
                ->get()
                ->map(fn (ApplicationSession $session) => [
                    'id' => $session->id,
                    'type' => 'app',
                    'name' => $session->app_name,
                    'employee' => trim(($session->member?->user?->first_name ?? '').' '.($session->member?->user?->last_name ?? '')),
                    'device' => $session->device?->name,
                    'project' => $session->project?->name,
                    'task' => $session->task?->title,
                    'started_at' => optional($session->started_at)->toIso8601String(),
                    'ended_at' => optional($session->ended_at)->toIso8601String(),
                    'duration_seconds' => $session->duration_seconds,
                    'active_seconds' => $session->active_seconds,
                    'idle_seconds' => $session->idle_seconds,
                    'source' => $session->source,
                    'window_title' => $session->window_title,
                ]);
        } else {
            $rows = WebsiteSession::query()
                ->with(['member.user', 'device', 'project', 'task', 'browserConnection'])
                ->where('workspace_id', $workspace->id)
                ->whereIn('member_id', $memberIds)
                ->where('domain', $target)
                ->whereBetween('started_at', [$from, $to])
                ->latest('started_at')
                ->limit(200)
                ->get()
                ->map(fn (WebsiteSession $session) => [
                    'id' => $session->id,
                    'type' => 'domain',
                    'name' => $session->domain,
                    'employee' => trim(($session->member?->user?->first_name ?? '').' '.($session->member?->user?->last_name ?? '')),
                    'device' => $session->device?->name,
                    'browser' => $session->browser_name ?? $session->browserConnection?->browser_name,
                    'project' => $session->project?->name,
                    'task' => $session->task?->title,
                    'started_at' => optional($session->started_at)->toIso8601String(),
                    'ended_at' => optional($session->ended_at)->toIso8601String(),
                    'duration_seconds' => $session->duration_seconds,
                    'active_seconds' => $session->active_seconds,
                    'idle_seconds' => $session->idle_seconds,
                    'source' => $session->source,
                    'page_title' => $session->page_title,
                ]);
        }

        return response()->json(['data' => $rows->values()]);
    }

    /** Handles the store rule operation for the current WorkIntel workflow. */ public function storeRule(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $this->validateRule($request, $workspace->id);
        $this->assertUniqueRule($workspace->id, $data);
        $rule = ProductivityRule::create([...$data, 'workspace_id' => $workspace->id, 'created_by' => $request->user()->id]);
        return response()->json(['data' => $this->rulePayload($rule)], 201);
    }

    /** Updates update rule data for the requested resource. */ public function updateRule(Request $request, ProductivityRule $rule): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($rule->workspace_id === $workspace->id, 404);
        $data = $this->validateRule($request, $workspace->id);
        $this->assertUniqueRule($workspace->id, $data, $rule->id);
        $rule->update($data);
        return response()->json(['data' => $this->rulePayload($rule->fresh())]);
    }

    /** Handles the destroy rule operation for the current WorkIntel workflow. */ public function destroyRule(Request $request, ProductivityRule $rule): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($rule->workspace_id === $workspace->id, 404);
        $rule->delete();
        return response()->json(null, 204);
    }

    /** Handles the store exclusion operation for the current WorkIntel workflow. */ public function storeExclusion(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $this->validateExclusion($request, $workspace->id);
        $this->assertUniqueExclusion($workspace->id, $data);
        $exclusion = TrackingExclusion::create([...$data, 'workspace_id' => $workspace->id, 'created_by' => $request->user()->id]);
        return response()->json(['data' => $exclusion], 201);
    }

    /** Updates update exclusion data for the requested resource. */ public function updateExclusion(Request $request, TrackingExclusion $exclusion): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($exclusion->workspace_id === $workspace->id, 404);
        $data = $this->validateExclusion($request, $workspace->id);
        $this->assertUniqueExclusion($workspace->id, $data, $exclusion->id);
        $exclusion->update($data);
        return response()->json(['data' => $exclusion->fresh()]);
    }

    /** Handles the destroy exclusion operation for the current WorkIntel workflow. */ public function destroyExclusion(Request $request, TrackingExclusion $exclusion): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($exclusion->workspace_id === $workspace->id, 404);
        $exclusion->delete();
        return response()->json(null, 204);
    }

    /** Updates update settings data for the requested resource. */ public function updateSettings(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'application_tracking_enabled' => ['required', 'boolean'],
            'website_tracking_enabled' => ['required', 'boolean'],
            'capture_window_titles' => ['required', 'boolean'],
            'capture_page_titles' => ['required', 'boolean'],
            'minimum_session_seconds' => ['required', 'integer', 'min:1', 'max:300'],
            'idle_threshold_seconds' => ['required', 'integer', 'min:60', 'max:3600'],
        ]);
        // Full URLs are intentionally disabled in this product version.
        $data['store_full_urls'] = false;
        $settings = ActivityTrackingSetting::updateOrCreate(['workspace_id' => $workspace->id], $data);
        return response()->json(['data' => $settings]);
    }

    /** Creates create browser enrollment data for the requested workflow. */ public function createBrowserEnrollment(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate(['member_id' => ['required', 'integer'], 'expires_minutes' => ['nullable', 'integer', Rule::in([5, 10, 15, 30, 60])]]);
        $member = WorkspaceMember::query()->where('workspace_id', $workspace->id)->whereKey($data['member_id'])->firstOrFail();
        $plainCode = 'WB-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
        $expires = now()->addMinutes($data['expires_minutes'] ?? 10);
        BrowserEnrollment::create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'created_by' => $request->user()->id,
            'code_hash' => hash('sha256', preg_replace('/[^A-Z0-9]/', '', strtoupper($plainCode)) ?? ''),
            'expires_at' => $expires,
        ]);
        return response()->json([
            'enrollment_code' => $plainCode,
            'expires_at' => $expires->toIso8601String(),
            'member_id' => $member->id,
            'enrollment_endpoint' => url('/api/v1/browser/enroll'),
        ], 201);
    }

    /** Handles the revoke browser connection operation for the current WorkIntel workflow. */ public function revokeBrowserConnection(Request $request, BrowserConnection $connection): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($connection->workspace_id === $workspace->id, 404);
        DB::transaction(function () use ($connection) {
            $connection->tokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $connection->update(['status' => 'revoked', 'revoked_at' => now()]);
        });
        return response()->json(['message' => 'Browser connection revoked.']);
    }

    /** Validates validate rule input before it is processed. */ private function validateRule(Request $request, int $workspaceId): array
    {
        $data = $request->validate([
            'scope_type' => ['required', Rule::in(['workspace', 'department', 'team', 'member', 'project'])],
            'scope_id' => ['nullable', 'integer'],
            'target_type' => ['required', Rule::in(['app', 'domain'])],
            'target' => ['required', 'string', 'max:253'],
            'classification' => ['required', Rule::in(['productive', 'neutral', 'unproductive', 'unclassified'])],
            'category' => ['nullable', 'string', 'max:80'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $this->assertScope($workspaceId, $data['scope_type'], $data['scope_id'] ?? null);
        $data['scope_id'] = $data['scope_type'] === 'workspace' ? null : $data['scope_id'];
        $data['target'] = $this->normalizeTarget($data['target_type'], $data['target']);
        return $data;
    }

    /** Validates validate exclusion input before it is processed. */ private function validateExclusion(Request $request, int $workspaceId): array
    {
        $data = $request->validate([
            'scope_type' => ['required', Rule::in(['workspace', 'department', 'team', 'member'])],
            'scope_id' => ['nullable', 'integer'],
            'target_type' => ['required', Rule::in(['app', 'domain'])],
            'pattern' => ['required', 'string', 'max:253'],
            'reason' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $this->assertScope($workspaceId, $data['scope_type'], $data['scope_id'] ?? null);
        $data['scope_id'] = $data['scope_type'] === 'workspace' ? null : $data['scope_id'];
        $data['pattern'] = $this->normalizeTarget($data['target_type'], $data['pattern']);
        return $data;
    }

    /** Handles the assert unique rule operation for the current WorkIntel workflow. */ private function assertUniqueRule(int $workspaceId, array $data, ?int $ignoreId = null): void
    {
        $query = ProductivityRule::query()
            ->where('workspace_id', $workspaceId)
            ->where('scope_type', $data['scope_type'])
            ->where('target_type', $data['target_type'])
            ->where('target', $data['target']);
        $data['scope_id'] === null ? $query->whereNull('scope_id') : $query->where('scope_id', $data['scope_id']);
        if ($ignoreId) $query->where('id', '!=', $ignoreId);
        if ($query->exists()) throw ValidationException::withMessages(['target' => ['A classification rule already exists for this target and scope.']]);
    }

    /** Handles the assert unique exclusion operation for the current WorkIntel workflow. */ private function assertUniqueExclusion(int $workspaceId, array $data, ?int $ignoreId = null): void
    {
        $query = TrackingExclusion::query()
            ->where('workspace_id', $workspaceId)
            ->where('scope_type', $data['scope_type'])
            ->where('target_type', $data['target_type'])
            ->where('pattern', $data['pattern']);
        $data['scope_id'] === null ? $query->whereNull('scope_id') : $query->where('scope_id', $data['scope_id']);
        if ($ignoreId) $query->where('id', '!=', $ignoreId);
        if ($query->exists()) throw ValidationException::withMessages(['pattern' => ['A privacy exclusion already exists for this target and scope.']]);
    }

    /** Handles the assert scope operation for the current WorkIntel workflow. */ private function assertScope(int $workspaceId, string $scopeType, ?int $scopeId): void
    {
        if ($scopeType === 'workspace') return;
        if (! $scopeId) throw ValidationException::withMessages(['scope_id' => ['A scope target is required.']]);
        $model = match ($scopeType) {
            'department' => \App\Models\Department::class,
            'team' => \App\Models\Team::class,
            'member' => WorkspaceMember::class,
            'project' => \App\Models\Project::class,
            default => null,
        };
        abort_unless($model && $model::query()->where('workspace_id', $workspaceId)->whereKey($scopeId)->exists(), 422, 'The selected scope does not belong to this workspace.');
    }

    /** Handles the visible member ids operation for the current WorkIntel workflow. */ private function visibleMemberIds(WorkspaceMember $viewer): array
    {
        if ($viewer->hasPermission('activity.view_all') || $viewer->hasPermission('activity.manage')) {
            return WorkspaceMember::query()->where('workspace_id', $viewer->workspace_id)->pluck('id')->all();
        }
        if ($viewer->hasPermission('activity.view_team')) {
            $teamMemberIds = DB::table('team_members')->whereIn('team_id', $viewer->teams()->pluck('teams.id'))->pluck('member_id');
            $reportIds = WorkspaceMember::query()->where('manager_id', $viewer->id)->pluck('id');
            return collect([$viewer->id])->merge($teamMemberIds)->merge($reportIds)->unique()->values()->all();
        }
        abort_unless($viewer->hasPermission('activity.view_own'), 403, 'You do not have permission to view activity.');
        return [$viewer->id];
    }

    /** Handles the classification for operation for the current WorkIntel workflow. */ private function classificationFor(Collection $rules, string $targetType, string $target, WorkspaceMember $member, ?int $projectId): array
    {
        $candidates = $rules->filter(fn (ProductivityRule $rule) => $rule->target_type === $targetType && $this->targetMatches($targetType, $target, $rule->target));
        $teamIds = $member->teams->pluck('id')->all();
        $priorities = [
            ['project', $projectId, 50],
            ['member', $member->id, 40],
            ['team', $teamIds, 30],
            ['department', $member->department_id, 20],
            ['workspace', null, 10],
        ];
        foreach ($priorities as [$scope, $id, $priority]) {
            if ($id === null || $id === []) continue;
            $rule = $candidates->first(function (ProductivityRule $rule) use ($scope, $id) {
                if ($rule->scope_type !== $scope) return false;
                return is_array($id) ? in_array((int) $rule->scope_id, $id, true) : (int) ($rule->scope_id ?? 0) === (int) $id;
            });
            if ($rule) return [$rule->classification, $rule->category];
        }
        $workspaceRule = $candidates->first(fn (ProductivityRule $rule) => $rule->scope_type === 'workspace');
        return $workspaceRule ? [$workspaceRule->classification, $workspaceRule->category] : ['unclassified', null];
    }

    /** Handles the target matches operation for the current WorkIntel workflow. */ private function targetMatches(string $type, string $value, string $ruleTarget): bool
    {
        $value = Str::lower($value); $ruleTarget = Str::lower($ruleTarget);
        return $type === 'domain' ? ($value === $ruleTarget || str_ends_with($value, '.'.$ruleTarget)) : ($value === $ruleTarget || str_contains($value, $ruleTarget));
    }

    /** Handles the empty directory row operation for the current WorkIntel workflow. */ private function emptyDirectoryRow(string $type, string $name, string $target): array
    {
        return ['type'=>$type,'name'=>$name,'target'=>$target,'seconds'=>0,'active_seconds'=>0,'people'=>[],'classification_counts'=>[],'category_counts'=>[]];
    }

    /** Handles the default category operation for the current WorkIntel workflow. */ private function defaultCategory(string $type, string $name): string
    {
        if ($type === 'domain') return 'Web';
        return str_contains(Str::lower($name), 'code') ? 'Development' : 'Application';
    }

    /** Handles the date range operation for the current WorkIntel workflow. */ private function dateRange(Request $request): array
    {
        $from = $request->filled('from') ? CarbonImmutable::parse((string) $request->string('from'))->startOfDay() : now()->startOfMonth()->toImmutable();
        $to = $request->filled('to') ? CarbonImmutable::parse((string) $request->string('to'))->endOfDay() : now()->endOfDay()->toImmutable();
        if ($to->lt($from) || $from->diffInDays($to) > 366) throw ValidationException::withMessages(['from' => ['Choose a valid date range of 366 days or less.']]);
        return [$from, $to];
    }

    /** Handles the normalize target operation for the current WorkIntel workflow. */ private function normalizeTarget(string $type, string $value): string
    {
        $value = trim(Str::lower($value));
        if ($type === 'domain' && str_contains($value, '://')) $value = (string) parse_url($value, PHP_URL_HOST);
        return mb_substr(preg_replace('/^www\./', '', $value) ?? '', 0, 253);
    }

    /** Handles the rule payload operation for the current WorkIntel workflow. */ private function rulePayload(ProductivityRule $rule): array
    {
        return ['id'=>$rule->id,'scope_type'=>$rule->scope_type,'scope_id'=>$rule->scope_id,'target_type'=>$rule->target_type,'target'=>$rule->target,'classification'=>$rule->classification,'category'=>$rule->category,'active'=>$rule->active];
    }
}
