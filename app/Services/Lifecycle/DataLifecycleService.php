<?php

namespace App\Services\Lifecycle;

use App\Models\Client;
use App\Models\DataLifecycleEvent;
use App\Models\MediaAsset;
use App\Models\MediaFolder;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\EntitlementService;
use App\Services\Media\MediaLibraryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Coordinates recoverable Trash, Restore and Permanent Delete operations for supported business entities. */
class DataLifecycleService
{
    /** Supported resource metadata keyed by stable API type. */
    private const TYPES = [
        'client' => ['model' => Client::class, 'permission' => 'clients.manage', 'label' => 'Client'],
        'project' => ['model' => Project::class, 'permission' => 'projects.manage', 'label' => 'Project'],
        'task' => ['model' => Task::class, 'permission' => 'tasks.manage', 'label' => 'Task'],
        'media' => ['model' => MediaAsset::class, 'permission' => 'media.manage', 'label' => 'Media'],
        'media-folder' => ['model' => MediaFolder::class, 'permission' => 'media.manage', 'label' => 'Media Folder'],
    ];

    /** Injects lifecycle dependencies used for entitlement and media purge rules. */
    public function __construct(
        private readonly EntitlementService $entitlements,
        private readonly MediaLibraryService $media,
    ) {}

    /** Returns all trashed resources the requesting member is allowed to manage. */
    public function trashItems(Workspace $workspace, WorkspaceMember $member, ?string $type = null): array
    {
        $types = $type ? [$type => self::TYPES[$type] ?? null] : self::TYPES;
        $items = collect();
        foreach ($types as $key => $definition) {
            if (! $definition || ! $this->canManage($member, $definition['permission'])) continue;
            /** @var class-string<Model> $model */
            $model = $definition['model'];
            $rows = $model::onlyTrashed()->where('workspace_id', $workspace->id)->latest('deleted_at')->limit(500)->get();
            $items = $items->merge($rows->map(fn (Model $row) => $this->payload($key, $definition['label'], $row)));
        }
        return $items->sortByDesc('deleted_at')->values()->all();
    }

    /** Moves one supported resource to Trash after workspace and dependency checks. */
    public function trash(Workspace $workspace, WorkspaceMember $member, string $type, int $id): array
    {
        [$definition, $row] = $this->resolveActive($workspace, $member, $type, $id);
        $this->assertTrashSafe($type, $row);
        $snapshot = $this->payload($type, $definition['label'], $row);
        $row->delete();
        $this->record($workspace, $member, $type, $id, 'trashed', $snapshot);
        return $this->payload($type, $definition['label'], $row);
    }

    /** Restores one trashed resource after checking parents, entitlements and permissions. */
    public function restore(Workspace $workspace, WorkspaceMember $member, string $type, int $id): array
    {
        [$definition, $row] = $this->resolveTrashed($workspace, $member, $type, $id);
        $this->assertRestoreSafe($workspace, $type, $row);
        $row->restore();
        $this->record($workspace, $member, $type, $id, 'restored', $this->payload($type, $definition['label'], $row));
        return $this->payload($type, $definition['label'], $row->fresh());
    }

    /** Permanently deletes one already-trashed resource after the stronger purge permission and dependency checks. */
    public function purge(Workspace $workspace, WorkspaceMember $member, string $type, int $id): void
    {
        abort_unless($member->hasPermission('trash.purge'), 403, 'You do not have permission to permanently delete trashed data.');
        [$definition, $row] = $this->resolveTrashed($workspace, $member, $type, $id);
        $this->assertPurgeSafe($type, $row);
        $snapshot = $this->payload($type, $definition['label'], $row);
        if ($row instanceof MediaAsset) {
            $this->media->purge($row);
        } elseif ($row instanceof Task) {
            foreach ($row->attachments()->get() as $attachment) {
                if (Storage::disk($attachment->disk)->exists($attachment->path)) Storage::disk($attachment->disk)->delete($attachment->path);
            }
            $row->forceDelete();
        } else {
            $row->forceDelete();
        }
        $this->record($workspace, $member, $type, $id, 'purged', $snapshot);
    }

    /** Returns supported lifecycle resource types for UI filters and policy explanation. */
    public function supportedTypes(): array
    {
        return collect(self::TYPES)->map(fn ($definition, $key) => ['key' => $key, 'label' => $definition['label']])->values()->all();
    }

    /** Resolves one non-trashed workspace resource and verifies management permission. */
    private function resolveActive(Workspace $workspace, WorkspaceMember $member, string $type, int $id): array
    {
        $definition = $this->definition($type);
        abort_unless($this->canManage($member, $definition['permission']), 403);
        $model = $definition['model'];
        $row = $model::query()->where('workspace_id', $workspace->id)->findOrFail($id);
        return [$definition, $row];
    }

    /** Resolves one trashed workspace resource and verifies management permission. */
    private function resolveTrashed(Workspace $workspace, WorkspaceMember $member, string $type, int $id): array
    {
        $definition = $this->definition($type);
        abort_unless($this->canManage($member, $definition['permission']), 403);
        $model = $definition['model'];
        $row = $model::onlyTrashed()->where('workspace_id', $workspace->id)->findOrFail($id);
        return [$definition, $row];
    }

    /** Returns one lifecycle definition or rejects an unknown public resource type. */
    private function definition(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404, 'Unknown Trash resource type.');
        return self::TYPES[$type];
    }

    /** Returns whether the member can manage a type including team-scoped task managers. */
    private function canManage(WorkspaceMember $member, string $permission): bool
    {
        if ($permission === 'tasks.manage') return $member->hasPermission('tasks.manage') || $member->hasPermission('tasks.manage_team');
        return $member->hasPermission($permission);
    }

    /** Blocks trashing records whose immutable or financial dependencies would lose context. */
    private function assertTrashSafe(string $type, Model $row): void
    {
        if ($row instanceof Client) {
            abort_if($row->projects()->withTrashed()->exists() || $row->invoices()->exists() || $row->payments()->exists() || $row->portalAccounts()->exists() || $row->reports()->exists(), 409, 'Archive this client instead. Clients with projects, invoices, payments, reports or portal accounts cannot be moved to Trash.');
        }
        if ($row instanceof Project) {
            abort_if($row->tasks()->withTrashed()->exists() || $row->timeEntries()->exists() || $row->expenses()->exists(), 409, 'Archive this project instead. Projects with tasks, tracked time or expenses cannot be moved to Trash.');
        }
        if ($row instanceof Task) {
            abort_if($row->timeEntries()->exists() || $row->subtasks()->withTrashed()->exists(), 409, 'Tasks with tracked time or subtasks cannot be moved to Trash. Complete or restructure the task instead.');
        }
        if ($row instanceof MediaAsset) {
            abort_if($row->usages()->exists(), 409, 'This media asset is in use. Remove its usages before moving it to Trash.');
        }
        if ($row instanceof MediaFolder) {
            abort_if($row->assets()->withTrashed()->exists() || $row->children()->withTrashed()->exists(), 409, 'Empty this media folder before moving it to Trash.');
        }
    }

    /** Blocks restores that would reference a trashed parent or exceed a plan limit. */
    private function assertRestoreSafe(Workspace $workspace, string $type, Model $row): void
    {
        if ($row instanceof Client) {
            $this->entitlements->assertWithinLimit($workspace, 'clients', $workspace->clients()->where('status', '!=', 'archived')->count());
        }
        if ($row instanceof Project) {
            $this->entitlements->assertWithinLimit($workspace, 'projects', $workspace->projects()->where('status', '!=', 'archived')->count());
            if ($row->client_id) abort_if(Client::withTrashed()->where('workspace_id', $workspace->id)->whereKey($row->client_id)->whereNotNull('deleted_at')->exists(), 409, 'Restore the client before restoring this project.');
        }
        if ($row instanceof Task) {
            abort_if(Project::withTrashed()->where('workspace_id', $workspace->id)->whereKey($row->project_id)->whereNotNull('deleted_at')->exists(), 409, 'Restore the project before restoring this task.');
            if ($row->parent_id) abort_if(Task::withTrashed()->whereKey($row->parent_id)->whereNotNull('deleted_at')->exists(), 409, 'Restore the parent task before restoring this task.');
        }
        if ($row instanceof MediaAsset && $row->folder_id) {
            abort_if(MediaFolder::withTrashed()->where('workspace_id', $workspace->id)->whereKey($row->folder_id)->whereNotNull('deleted_at')->exists(), 409, 'Restore the media folder before restoring this asset.');
        }
        if ($row instanceof MediaFolder && $row->parent_id) {
            abort_if(MediaFolder::withTrashed()->where('workspace_id', $workspace->id)->whereKey($row->parent_id)->whereNotNull('deleted_at')->exists(), 409, 'Restore the parent media folder before restoring this folder.');
        }
    }

    /** Applies the same dependency guarantees before irreversible deletion. */
    private function assertPurgeSafe(string $type, Model $row): void
    {
        $this->assertTrashSafe($type, $row);
    }

    /** Shapes one trashed or active model into the common Trash Center contract. */
    private function payload(string $type, string $typeLabel, Model $row): array
    {
        $name = match (true) {
            $row instanceof Client => $row->company_name ?: $row->name,
            $row instanceof Project => $row->name,
            $row instanceof Task => $row->title,
            $row instanceof MediaAsset => $row->name,
            $row instanceof MediaFolder => $row->name,
            default => '#'.$row->getKey(),
        };
        $description = match (true) {
            $row instanceof Client => $row->email ?: 'Client record',
            $row instanceof Project => $row->code ?: 'Project record',
            $row instanceof Task => $row->project?->name ?: 'Task record',
            $row instanceof MediaAsset => trim(($row->mime_type ?: 'File').' · '.$this->bytes((int) $row->size_bytes)),
            $row instanceof MediaFolder => 'Media folder',
            default => null,
        };
        return [
            'type' => $type,
            'type_label' => $typeLabel,
            'id' => (int) $row->getKey(),
            'name' => $name,
            'description' => $description,
            'deleted_at' => $row->deleted_at?->toIso8601String(),
            'can_restore' => true,
        ];
    }

    /** Writes one lightweight lifecycle audit event without storing sensitive full records. */
    private function record(Workspace $workspace, WorkspaceMember $member, string $type, int $id, string $action, array $snapshot): void
    {
        DataLifecycleEvent::create([
            'workspace_id' => $workspace->id,
            'actor_member_id' => $member->id,
            'resource_type' => $type,
            'resource_id' => $id,
            'action' => $action,
            'snapshot' => collect($snapshot)->only(['type_label', 'name', 'description'])->all(),
            'created_at' => now(),
        ]);
    }

    /** Formats byte counts into compact human-readable values for Trash metadata. */
    private function bytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes.' B';
        if ($bytes < 1024 ** 2) return round($bytes / 1024, 1).' KB';
        if ($bytes < 1024 ** 3) return round($bytes / 1024 ** 2, 1).' MB';
        return round($bytes / 1024 ** 3, 1).' GB';
    }
}
