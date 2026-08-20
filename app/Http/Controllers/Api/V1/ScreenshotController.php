<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Billing\EntitlementService;
use App\Services\Access\WorkScopeService;
use App\Services\ScreenshotStorage\ScreenshotStorageService;
use App\Models\Screenshot;
use App\Models\ScreenshotSetting;
use App\Models\WorkspaceMember;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/** Provides screenshot controller behavior within the WorkIntel application. */ class ScreenshotController extends Controller
{
    /** Returns the requested resource collection. */ public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'member_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'flagged' => ['nullable', Rule::in(['0', '1'])],
        ]);

        $date = isset($data['date']) ? CarbonImmutable::parse($data['date']) : CarbonImmutable::today($workspace->timezone ?: config('app.timezone'));
        $memberIds = $this->visibleMemberIds($viewer);
        $settings = ScreenshotSetting::firstOrCreate(['workspace_id' => $workspace->id]);

        $query = Screenshot::query()
            ->with(['member.user', 'project', 'task', 'device', 'storageProvider'])
            ->where('workspace_id', $workspace->id)
            ->whereIn('member_id', $memberIds)
            ->whereNull('deleted_at')
            ->whereBetween('captured_at', [$date->startOfDay()->utc(), $date->endOfDay()->utc()]);

        if (! empty($data['member_id'])) $query->where('member_id', (int) $data['member_id']);
        if (! empty($data['project_id'])) $query->where('project_id', (int) $data['project_id']);
        if (array_key_exists('flagged', $data)) $query->where('flagged', (bool) ((int) $data['flagged']));

        $screenshots = $query->latest('captured_at')->limit(500)->get();

        $retentionLimit = (int) app(EntitlementService::class)->value($workspace, 'limit.screenshot_retention_days', 7);

        return response()->json([
            'date' => $date->toDateString(),
            'settings' => $settings,
            'limits' => [
                'interval_min' => 1,
                'interval_max' => 60,
                'randomize_max' => 15,
                'retention_days_max' => max(1, $retentionLimit),
                'max_upload_kb_min' => 256,
                'max_upload_kb_max' => 20480,
            ],
            'can_manage' => $viewer->hasPermission('screenshots.manage') || $viewer->hasPermission('screenshots.settings_manage'),
            'screenshots' => $screenshots->map(fn (Screenshot $screenshot) => $this->payload($screenshot))->values(),
            'members' => WorkspaceMember::query()->with('user')->where('workspace_id', $workspace->id)->whereIn('id', $memberIds)->where('status', 'active')->get()->map(fn ($member) => [
                'id' => $member->id,
                'name' => trim($member->user->first_name.' '.$member->user->last_name),
            ])->values(),
            'projects' => app(WorkScopeService::class)->scopeProjects(
                \App\Models\Project::query()->where('workspace_id', $workspace->id)->where('status', '!=', 'archived'),
                $viewer
            )->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Updates update settings data for the requested resource. */ public function updateSettings(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $planRetention = (int) app(EntitlementService::class)->value($workspace, 'limit.screenshot_retention_days', 7);
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'interval_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'randomize_minutes' => ['required', 'integer', 'min:0', 'max:15'],
            'capture_all_monitors' => ['required', 'boolean'],
            'blur_by_default' => ['required', 'boolean'],
            'quality' => ['required', Rule::in(['low', 'medium', 'high'])],
            'allow_employee_delete' => ['required', 'boolean'],
            'retention_days' => ['required', 'integer', 'min:1', 'max:'.max(1, $planRetention)],
            'max_upload_kb' => ['sometimes', 'integer', 'min:256', 'max:20480'],
            'capture_notification_mode' => ['sometimes', Rule::in(['always','first_session','silent'])],
            'notify_on_upload_failure' => ['sometimes', 'boolean'],
        ]);

        $settings = ScreenshotSetting::updateOrCreate(['workspace_id' => $workspace->id], $data);
        return response()->json(['message' => 'Screenshot settings saved.', 'settings' => $settings]);
    }

    /** Handles the upload from agent operation for the current WorkIntel workflow. */ public function uploadFromAgent(Request $request): JsonResponse
    {
        $device = $request->attributes->get('device');
        app(EntitlementService::class)->assertFeature(Workspace::findOrFail($device->workspace_id), 'feature.screenshots');
        $settings = ScreenshotSetting::firstOrCreate(['workspace_id' => $device->workspace_id]);
        abort_unless($settings->enabled, 409, 'Screenshot capture is disabled for this workspace.');
        abort_if($device->tracking_status === 'paused', 409, 'Tracking is paused on this device.');

        $maxKb = max(256, (int) $settings->max_upload_kb);
        $data = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.$maxKb],
            'captured_at' => ['required', 'date'],
            'monitor_index' => ['nullable', 'integer', 'min:1', 'max:16'],
            'project_id' => ['nullable', 'integer'],
            'task_id' => ['nullable', 'integer'],
            'app_name' => ['nullable', 'string', 'max:180'],
            'activity_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'blurred' => ['nullable', 'boolean'],
        ]);

        if ($settings->blur_by_default && ! ($data['blurred'] ?? false)) {
            abort(422, 'This workspace requires screenshots to be blurred before upload.');
        }

        if (! empty($data['project_id'])) {
            abort_unless(\App\Models\Project::query()->where('workspace_id', $device->workspace_id)->whereKey($data['project_id'])->exists(), 422, 'Project does not belong to this workspace.');
        }
        if (! empty($data['task_id'])) {
            $task = \App\Models\Task::query()->where('workspace_id', $device->workspace_id)->whereKey($data['task_id'])->first();
            abort_unless($task, 422, 'Task does not belong to this workspace.');
            if (! empty($data['project_id'])) abort_unless((int) $task->project_id === (int) $data['project_id'], 422, 'Task does not belong to the selected project.');
        }

        $uuid = (string) Str::uuid();
        $extension = strtolower($data['image']->extension() ?: 'jpg');
        $captured = CarbonImmutable::parse($data['captured_at']);
        // Agent ingestion always lands in the private local spool first. Remote storage is verified asynchronously.
        $disk = 'local';
        $path = sprintf('screenshots/%d/%s/%s.%s', $device->workspace_id, $captured->format('Y/m/d'), $uuid, $extension);
        $stored = Storage::disk($disk)->putFileAs(dirname($path), $data['image'], basename($path));
        abort_unless($stored, 503, 'Screenshot could not be written to the local safety spool.');

        $dimensions = @getimagesize($data['image']->getRealPath()) ?: [null, null];
        $screenshot = Screenshot::create([
            'uuid' => $uuid,
            'workspace_id' => $device->workspace_id,
            'member_id' => $device->member_id,
            'device_id' => $device->id,
            'project_id' => $data['project_id'] ?? null,
            'task_id' => $data['task_id'] ?? null,
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $data['image']->getMimeType() ?: 'image/jpeg',
            'size_bytes' => $data['image']->getSize(),
            'checksum_sha256' => hash_file('sha256', $data['image']->getRealPath()) ?: null,
            'storage_status' => 'local',
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
            'monitor_index' => $data['monitor_index'] ?? 1,
            'app_name' => $data['app_name'] ?? null,
            'activity_percent' => $data['activity_percent'] ?? null,
            'blurred' => (bool) ($data['blurred'] ?? false),
            'captured_at' => $captured,
        ]);

        app(ScreenshotStorageService::class)->enqueue($screenshot);
        return response()->json(['message' => 'Screenshot uploaded.', 'screenshot' => $this->payload($screenshot->load(['member.user', 'project', 'task', 'device', 'storageProvider']))], 201);
    }

    /** Handles the image operation for the current WorkIntel workflow. */ public function image(Request $request, Screenshot $screenshot): Response
    {
        $workspace = Workspace::findOrFail($screenshot->workspace_id);
        app(\App\Services\Modules\WorkspaceModuleService::class)->assertEnabled($workspace, 'screenshots');
        app(EntitlementService::class)->assertFeature($workspace, 'feature.screenshots');
        $membership = WorkspaceMember::query()->with('roles.permissions')->where('workspace_id', $screenshot->workspace_id)->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($membership && in_array($screenshot->member_id, $this->visibleMemberIds($membership), true), 403);
        abort_if($screenshot->deleted_at, 404);
        try { $contents = app(ScreenshotStorageService::class)->read($screenshot->load('storageProvider')); }
        catch (\Throwable $e) { abort(503, 'Screenshot storage is temporarily unavailable: '.$e->getMessage()); }

        return response($contents, 200, [
            'Content-Type' => $screenshot->mime_type,
            'Content-Disposition' => 'inline; filename="'.$screenshot->uuid.'.'.pathinfo($screenshot->path, PATHINFO_EXTENSION).'"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /** Updates update data for the requested resource. */ public function update(Request $request, Screenshot $screenshot): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int) $screenshot->workspace_id === (int) $workspace->id, 404);
        $data = $request->validate([
            'flagged' => ['sometimes', 'boolean'],
            'flag_reason' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:4000'],
        ]);
        $screenshot->update($data);
        return response()->json(['message' => 'Screenshot updated.', 'screenshot' => $this->payload($screenshot->fresh(['member.user', 'project', 'task', 'device', 'storageProvider']))]);
    }

    /** Removes destroy data from the requested resource. */ public function destroy(Request $request, Screenshot $screenshot): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $viewer = $request->attributes->get('workspaceMember');
        abort_unless((int) $screenshot->workspace_id === (int) $workspace->id, 404);
        $settings = ScreenshotSetting::firstOrCreate(['workspace_id' => $workspace->id]);
        $canManage = $viewer->hasPermission('screenshots.manage');
        $canDeleteOwn = $settings->allow_employee_delete && (int) $screenshot->member_id === (int) $viewer->id;
        abort_unless($canManage || $canDeleteOwn, 403, 'You cannot delete this screenshot.');

        if (! $screenshot->deleted_at) {
            try { app(ScreenshotStorageService::class)->deleteBinary($screenshot->load('storageProvider')); }
            catch (\Throwable $e) { abort(503, 'Screenshot could not be deleted from storage: '.$e->getMessage()); }
            $screenshot->update(['deleted_at' => now(), 'deleted_by' => $request->user()->id]);
        }
        return response()->json(['message' => 'Screenshot deleted.']);
    }

    /** Handles the payload operation for the current WorkIntel workflow. */ private function payload(Screenshot $screenshot): array
    {
        return [
            'id' => $screenshot->id,
            'uuid' => $screenshot->uuid,
            'member_id' => $screenshot->member_id,
            'employee' => trim(($screenshot->member?->user?->first_name ?? '').' '.($screenshot->member?->user?->last_name ?? '')),
            'device' => $screenshot->device?->name,
            'project' => $screenshot->project?->name,
            'task' => $screenshot->task?->title,
            'app_name' => $screenshot->app_name,
            'activity_percent' => $screenshot->activity_percent,
            'monitor_index' => $screenshot->monitor_index,
            'blurred' => $screenshot->blurred,
            'flagged' => $screenshot->flagged,
            'flag_reason' => $screenshot->flag_reason,
            'note' => $screenshot->note,
            'captured_at' => optional($screenshot->captured_at)->toIso8601String(),
            'image_url' => '/api/v1/screenshots/'.$screenshot->id.'/image',
            'size_bytes' => $screenshot->size_bytes,
            'width' => $screenshot->width,
            'height' => $screenshot->height,
            'storage_status' => $screenshot->storage_status,
            'storage_provider' => $screenshot->storageProvider?->name,
            'storage_verified_at' => optional($screenshot->storage_verified_at)->toIso8601String(),
            'storage_error' => $screenshot->storage_error,
        ];
    }

    /** @return array<int, int> */
    /** Handles the visible member ids operation for the current WorkIntel workflow. */ private function visibleMemberIds(WorkspaceMember $viewer): array
    {
        if ($viewer->hasPermission('screenshots.view_all') || $viewer->hasPermission('screenshots.manage')) {
            return WorkspaceMember::query()->where('workspace_id', $viewer->workspace_id)->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        if ($viewer->hasPermission('screenshots.view_team')) {
            $teamIds = $viewer->teams()->pluck('teams.id');
            $ids = WorkspaceMember::query()->where('workspace_id', $viewer->workspace_id)->where(function ($query) use ($viewer, $teamIds) {
                $query->whereKey($viewer->id)
                    ->orWhere('manager_id', $viewer->id)
                    ->orWhereHas('teams', fn ($team) => $team->whereIn('teams.id', $teamIds));
            })->pluck('id')->map(fn ($id) => (int) $id)->all();
            return array_values(array_unique($ids));
        }
        abort_unless($viewer->hasPermission('screenshots.view_own'), 403, 'You do not have screenshot access.');
        return [(int) $viewer->id];
    }
}
