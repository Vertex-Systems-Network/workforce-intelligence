<?php

namespace App\Services\Enterprise;

use App\Models\DataGovernancePolicy;
use App\Models\DataRetentionRun;
use App\Models\Workspace;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Provides data retention service behavior within the WorkIntel application. */ class DataRetentionService
{
    private const DATASETS = [
        'audit_logs' => ['days' => 365, 'column' => 'created_at'],
        'webhook_deliveries' => ['days' => 90, 'column' => 'created_at'],
        'workspace_notifications' => ['days' => 180, 'column' => 'created_at'],
        'security_events' => ['days' => 730, 'column' => 'created_at'],
        'mobile_sync_events' => ['days' => 90, 'column' => 'created_at'],
        'field_work_order_events' => ['days' => 365, 'column' => 'created_at'],
        'workspace_access_sessions' => ['days' => 90, 'column' => 'created_at'],
        'automation_events' => ['days' => 180, 'column' => 'created_at'],
        'automation_runs' => ['days' => 365, 'column' => 'created_at'],
        'intelligence_runs' => ['days' => 365, 'column' => 'created_at'],
        'intelligence_insights' => ['days' => 730, 'column' => 'created_at'],
    ];

    /** Handles the run operation for the current WorkIntel workflow. */ public function run(?int $workspaceId = null): array
    {
        $result = ['workspaces' => 0, 'deleted' => 0, 'staged' => 0, 'datasets' => []];
        $workspaces = Workspace::query()->where('status', 'active')->when($workspaceId, fn ($q) => $q->whereKey($workspaceId));

        foreach ($workspaces->cursor() as $workspace) {
            $result['workspaces']++;
            foreach (self::DATASETS as $dataset => $config) {
                if (! Schema::hasTable($dataset)) continue;
                $policy = DataGovernancePolicy::where('workspace_id', $workspace->id)->where('dataset', $dataset)->first();
                if ($policy?->legal_hold) {
                    $result['datasets'][] = ['workspace_id' => $workspace->id, 'dataset' => $dataset, 'status' => 'legal_hold', 'staged' => 0, 'deleted' => 0];
                    continue;
                }

                $days = (int) ($policy?->retention_days ?? $config['days']);
                if ($days < 1) continue;
                $mode = $policy?->deletion_mode ?? 'hard_purge';
                $graceDays = max(1, min(365, (int) data_get($policy?->settings, 'purge_grace_days', 30)));
                $run = DataRetentionRun::create([
                    'uuid' => (string) Str::uuid(), 'workspace_id' => $workspace->id, 'dataset' => $dataset,
                    'started_at' => now(), 'status' => 'running', 'candidate_count' => 0, 'deleted_count' => 0,
                ]);

                try {
                    $candidateQuery = $this->candidateQuery($dataset, $workspace->id, $config['column'], $days);
                    $candidateIds = $candidateQuery->orderBy('id')->limit(5000)->pluck('id');
                    $candidateCount = $candidateIds->count();
                    $staged = 0;
                    $deleted = 0;

                    if ($mode === 'soft_then_purge' && Schema::hasTable('data_retention_tombstones')) {
                        foreach ($candidateIds as $recordId) {
                            $created = DB::table('data_retention_tombstones')->insertOrIgnore([
                                'workspace_id' => $workspace->id, 'dataset' => $dataset, 'record_id' => $recordId,
                                'staged_at' => now(), 'purge_after' => now()->addDays($graceDays), 'created_at' => now(), 'updated_at' => now(),
                            ]);
                            $staged += (int) $created;
                        }

                        $due = DB::table('data_retention_tombstones')
                            ->where('workspace_id', $workspace->id)->where('dataset', $dataset)
                            ->where('purge_after', '<=', now())->orderBy('id')->limit(5000)->get(['id', 'record_id']);
                        if ($due->isNotEmpty()) {
                            $dueRecordIds = $due->pluck('record_id');
                            $stillEligible = $this->candidateQuery($dataset, $workspace->id, $config['column'], $days)
                                ->whereIn('id', $dueRecordIds)->pluck('id');
                            if ($stillEligible->isNotEmpty()) {
                                $deleted = DB::table($dataset)->where('workspace_id', $workspace->id)->whereIn('id', $stillEligible)->delete();
                            }
                            // Due tombstones are consumed even if a changed retention policy made a record ineligible.
                            DB::table('data_retention_tombstones')->whereIn('id', $due->pluck('id'))->delete();
                        }
                    } else {
                        $deleted = $candidateCount
                            ? DB::table($dataset)->where('workspace_id', $workspace->id)->whereIn('id', $candidateIds)->delete()
                            : 0;
                    }

                    $run->update([
                        'candidate_count' => $candidateCount, 'deleted_count' => $deleted, 'status' => 'completed', 'completed_at' => now(),
                        'metadata' => ['retention_days' => $days, 'deletion_mode' => $mode, 'purge_grace_days' => $mode === 'soft_then_purge' ? $graceDays : 0, 'staged_count' => $staged, 'residency_region' => $policy?->residency_region],
                    ]);
                    $result['staged'] += $staged;
                    $result['deleted'] += $deleted;
                    $result['datasets'][] = ['workspace_id' => $workspace->id, 'dataset' => $dataset, 'status' => 'completed', 'staged' => $staged, 'deleted' => $deleted];
                } catch (\Throwable $e) {
                    $run->update(['status' => 'failed', 'completed_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 5000)]);
                    $result['datasets'][] = ['workspace_id' => $workspace->id, 'dataset' => $dataset, 'status' => 'failed', 'staged' => 0, 'deleted' => 0, 'error' => $e->getMessage()];
                }
            }
        }

        return $result;
    }

    /** Determines whether the candidate query condition is satisfied. */ private function candidateQuery(string $dataset, int $workspaceId, string $column, int $days): Builder
    {
        $query = DB::table($dataset)->where('workspace_id', $workspaceId)->where($column, '<', now()->subDays($days));
        if ($dataset === 'workspace_notifications') $query->whereNotNull('read_at');
        if ($dataset === 'security_events') $query->whereNotNull('resolved_at');
        if ($dataset === 'workspace_access_sessions') {
            $query->where(fn ($session) => $session->whereNotNull('revoked_at')->orWhere('expires_at', '<', now()));
        }
        if ($dataset === 'intelligence_insights') {
            $query->whereIn('status', ['resolved', 'dismissed']);
        }
        return $query;
    }
}
