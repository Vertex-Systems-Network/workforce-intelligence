<?php

namespace App\Services\Modules;

use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceModule;
use App\Models\WorkspaceModuleEvent;
use App\Services\Billing\EntitlementService;
use App\Support\ModuleCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Provides workspace module service behavior within the WorkIntel application. */ class WorkspaceModuleService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly EntitlementService $entitlements) {}

    /** Handles the installed operation for the current WorkIntel workflow. */ public function installed(): bool
    {
        return Schema::hasTable('workspace_modules');
    }

    /** Handles the initialize workspace operation for the current WorkIntel workflow. */ public function initializeWorkspace(Workspace $workspace, ?Workspace $source = null): void
    {
        if (! $this->installed()) return;

        $sourceRows = $source
            ? WorkspaceModule::query()->where('workspace_id', $source->id)->get()->keyBy('module_key')
            : collect();

        foreach (ModuleCatalog::DEFINITIONS as $key => $definition) {
            $sourceRow = $sourceRows->get($key);
            WorkspaceModule::query()->firstOrCreate(
                ['workspace_id' => $workspace->id, 'module_key' => $key],
                [
                    'is_enabled' => $sourceRow?->is_enabled ?? (bool) ($definition['default_enabled'] ?? true),
                    'navigation_visible' => $sourceRow?->navigation_visible ?? true,
                    'background_processing' => $sourceRow?->background_processing ?? true,
                    'label_override' => $sourceRow?->label_override,
                    'settings' => $sourceRow?->settings ?? [],
                    'enabled_at' => ($sourceRow?->is_enabled ?? ($definition['default_enabled'] ?? true)) ? now() : null,
                ]
            );
        }
    }

    /** Handles the row operation for the current WorkIntel workflow. */ public function row(Workspace $workspace, string $moduleKey): ?WorkspaceModule
    {
        if (! $this->installed()) return null;
        return WorkspaceModule::query()->where('workspace_id', $workspace->id)->where('module_key', $moduleKey)->first();
    }

    /** Determines whether the is plan available condition is satisfied. */ public function isPlanAvailable(Workspace $workspace, string $moduleKey): bool
    {
        $definition = ModuleCatalog::definition($moduleKey);
        if (! $definition) return false;
        $entitlement = $definition['entitlement'] ?? null;
        return ! $entitlement || $this->entitlements->allows($workspace, $entitlement);
    }

    /** Determines whether the is enabled condition is satisfied. */ public function isEnabled(Workspace $workspace, string $moduleKey): bool
    {
        $definition = ModuleCatalog::definition($moduleKey);
        if (! $definition) return true;
        if (! $this->installed()) return (bool) ($definition['default_enabled'] ?? true);
        $row = $this->row($workspace, $moduleKey);
        $workspaceEnabled = $row ? (bool) $row->is_enabled : (bool) ($definition['default_enabled'] ?? true);
        return $workspaceEnabled && $this->isPlanAvailable($workspace, $moduleKey);
    }

    /** Handles the should process background operation for the current WorkIntel workflow. */ public function shouldProcessBackground(Workspace $workspace, string $moduleKey): bool
    {
        if (! $this->isEnabled($workspace, $moduleKey)) return false;
        $row = $this->row($workspace, $moduleKey);
        return $row ? (bool) $row->background_processing : true;
    }

    /** Handles the assert enabled operation for the current WorkIntel workflow. */ public function assertEnabled(Workspace $workspace, string $moduleKey): void
    {
        if ($this->isEnabled($workspace, $moduleKey)) return;
        $definition = ModuleCatalog::definition($moduleKey);
        $planAvailable = $this->isPlanAvailable($workspace, $moduleKey);
        throw new HttpResponseException(response()->json([
            'message' => $planAvailable
                ? (($definition['label'] ?? $moduleKey).' is disabled for this workspace.')
                : (($definition['label'] ?? $moduleKey).' is not available on the current plan.'),
            'code' => $planAvailable ? 'WORKSPACE_MODULE_DISABLED' : 'PLAN_FEATURE_REQUIRED',
            'module' => $moduleKey,
        ], $planAvailable ? 423 : 402));
    }

    /** @return array<int,array<string,mixed>> */
    /** Handles the catalog operation for the current WorkIntel workflow. */ public function catalog(Workspace $workspace): array
    {
        $this->initializeWorkspace($workspace);
        $rows = $this->installed()
            ? WorkspaceModule::query()->where('workspace_id', $workspace->id)->get()->keyBy('module_key')
            : collect();

        return collect(ModuleCatalog::DEFINITIONS)->map(function (array $definition, string $key) use ($workspace, $rows) {
            /** @var WorkspaceModule|null $row */
            $row = $rows->get($key);
            $planAvailable = $this->isPlanAvailable($workspace, $key);
            $workspaceEnabled = $row ? (bool) $row->is_enabled : (bool) ($definition['default_enabled'] ?? true);
            return [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'category' => $definition['category'],
                'dependencies' => ModuleCatalog::dependencies($key),
                'dependents' => ModuleCatalog::dependents($key),
                'entitlement' => $definition['entitlement'] ?? null,
                'plan_available' => $planAvailable,
                'workspace_enabled' => $workspaceEnabled,
                'enabled' => $workspaceEnabled && $planAvailable,
                'navigation_visible' => $row ? (bool) $row->navigation_visible : true,
                'background_processing' => $row ? (bool) $row->background_processing : true,
                'label_override' => $row?->label_override,
                'settings' => $row?->settings ?? [],
                'page' => $definition['page'] ?? null,
            ];
        })->values()->all();
    }

    /** @return array<string,array<string,mixed>> */
    /** Handles the auth map operation for the current WorkIntel workflow. */ public function authMap(Workspace $workspace): array
    {
        return collect($this->catalog($workspace))->mapWithKeys(fn (array $module) => [
            $module['key'] => [
                'enabled' => (bool) $module['enabled'],
                'workspace_enabled' => (bool) $module['workspace_enabled'],
                'plan_available' => (bool) $module['plan_available'],
                'navigation_visible' => (bool) $module['navigation_visible'],
                'label' => $module['label_override'] ?: $module['label'],
            ],
        ])->all();
    }

    /** Updates update data for the requested resource. */ public function update(Workspace $workspace, string $moduleKey, array $data, WorkspaceMember $actor): WorkspaceModule
    {
        $definition = ModuleCatalog::definition($moduleKey);
        if (! $definition) throw ValidationException::withMessages(['module' => ['Unknown workspace module.']]);
        $this->initializeWorkspace($workspace);

        return DB::transaction(function () use ($workspace, $moduleKey, $data, $actor) {
            /** @var WorkspaceModule $row */
            $row = WorkspaceModule::query()->where('workspace_id', $workspace->id)->where('module_key', $moduleKey)->lockForUpdate()->firstOrFail();
            $before = $this->state($row);

            if (array_key_exists('is_enabled', $data)) {
                $enabled = (bool) $data['is_enabled'];
                if ($enabled && ! $row->is_enabled) {
                    $this->enableDependencies($workspace, $moduleKey, $actor, (bool) ($data['enable_dependencies'] ?? true));
                    $row->is_enabled = true;
                    $row->enabled_at = now();
                    $row->enabled_by = $actor->id;
                    $row->disabled_at = null;
                    $row->disabled_by = null;
                }
                if (! $enabled && $row->is_enabled) {
                    $this->assertCanDisable($workspace, $moduleKey, (bool) ($data['cascade_dependents'] ?? false), $actor);
                    $row->is_enabled = false;
                    $row->disabled_at = now();
                    $row->disabled_by = $actor->id;
                }
            }

            if (array_key_exists('navigation_visible', $data)) $row->navigation_visible = (bool) $data['navigation_visible'];
            if (array_key_exists('background_processing', $data)) $row->background_processing = (bool) $data['background_processing'];
            if (array_key_exists('label_override', $data)) $row->label_override = filled($data['label_override']) ? trim((string) $data['label_override']) : null;
            if (array_key_exists('settings', $data)) $row->settings = $data['settings'] ?? [];
            $row->save();

            $after = $this->state($row->fresh());
            if ($before !== $after) $this->event($workspace, $moduleKey, $actor, 'updated', $before, $after, ['source' => 'module_manager']);
            return $row->fresh();
        });
    }

    /** Handles the reset defaults operation for the current WorkIntel workflow. */ public function resetDefaults(Workspace $workspace, WorkspaceMember $actor): void
    {
        $this->initializeWorkspace($workspace);
        DB::transaction(function () use ($workspace, $actor) {
            foreach (ModuleCatalog::DEFINITIONS as $key => $definition) {
                $row = WorkspaceModule::query()->where('workspace_id', $workspace->id)->where('module_key', $key)->lockForUpdate()->first();
                if (! $row) continue;
                $before = $this->state($row);
                $enabled = (bool) ($definition['default_enabled'] ?? true);
                $row->update([
                    'is_enabled' => $enabled,
                    'navigation_visible' => true,
                    'background_processing' => true,
                    'label_override' => null,
                    'settings' => [],
                    'enabled_at' => $enabled ? now() : null,
                    'enabled_by' => $enabled ? $actor->id : null,
                    'disabled_at' => $enabled ? null : now(),
                    'disabled_by' => $enabled ? null : $actor->id,
                ]);
                $after = $this->state($row->fresh());
                if ($before !== $after) $this->event($workspace, $key, $actor, 'reset', $before, $after);
            }
        });
    }

    /** Handles the enable dependencies operation for the current WorkIntel workflow. */ private function enableDependencies(Workspace $workspace, string $moduleKey, WorkspaceMember $actor, bool $allow): void
    {
        foreach (ModuleCatalog::dependencies($moduleKey) as $dependency) {
            $row = WorkspaceModule::query()->where('workspace_id', $workspace->id)->where('module_key', $dependency)->lockForUpdate()->first();
            if (! $row || $row->is_enabled) continue;
            if (! $allow) throw ValidationException::withMessages(['module' => ["Enable dependency '{$dependency}' first."]]);
            $before = $this->state($row);
            $row->update(['is_enabled' => true, 'enabled_at' => now(), 'enabled_by' => $actor->id, 'disabled_at' => null, 'disabled_by' => null]);
            $this->event($workspace, $dependency, $actor, 'dependency_enabled', $before, $this->state($row->fresh()), ['required_by' => $moduleKey]);
            $this->enableDependencies($workspace, $dependency, $actor, true);
        }
    }

    /** Handles the assert can disable operation for the current WorkIntel workflow. */ private function assertCanDisable(Workspace $workspace, string $moduleKey, bool $cascade, WorkspaceMember $actor): void
    {
        $enabledDependents = collect(ModuleCatalog::dependents($moduleKey))->filter(function (string $dependent) use ($workspace) {
            return (bool) WorkspaceModule::query()->where('workspace_id', $workspace->id)->where('module_key', $dependent)->where('is_enabled', true)->exists();
        })->values();

        if ($enabledDependents->isEmpty()) return;
        if (! $cascade) {
            throw ValidationException::withMessages(['module' => ['Disable dependent modules first: '.$enabledDependents->implode(', ').'.']]);
        }

        foreach ($enabledDependents as $dependent) {
            $this->assertCanDisable($workspace, $dependent, true, $actor);
            $row = WorkspaceModule::query()->where('workspace_id', $workspace->id)->where('module_key', $dependent)->lockForUpdate()->first();
            if (! $row || ! $row->is_enabled) continue;
            $before = $this->state($row);
            $row->update(['is_enabled' => false, 'disabled_at' => now(), 'disabled_by' => $actor->id]);
            $this->event($workspace, $dependent, $actor, 'dependency_disabled', $before, $this->state($row->fresh()), ['disabled_with' => $moduleKey]);
        }
    }

    /** @return array<string,mixed> */
    /** Handles the state operation for the current WorkIntel workflow. */ private function state(WorkspaceModule $row): array
    {
        return [
            'is_enabled' => (bool) $row->is_enabled,
            'navigation_visible' => (bool) $row->navigation_visible,
            'background_processing' => (bool) $row->background_processing,
            'label_override' => $row->label_override,
            'settings' => $row->settings ?? [],
        ];
    }

    /** Handles the event operation for the current WorkIntel workflow. */ private function event(Workspace $workspace, string $key, WorkspaceMember $actor, string $action, array $before, array $after, array $metadata = []): void
    {
        if (! Schema::hasTable('workspace_module_events')) return;
        WorkspaceModuleEvent::create([
            'workspace_id' => $workspace->id,
            'module_key' => $key,
            'actor_member_id' => $actor->id,
            'action' => $action,
            'before_state' => $before,
            'after_state' => $after,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
