<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatExportJob;
use App\Models\ChatMessage;
use App\Models\DataGovernancePolicy;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** Applies external-access expiry, chat retention and export cleanup while respecting legal holds. */
class ChatEnterpriseMaintenanceService
{
    /** Injects legal-hold evaluation used by retention cleanup. */
    public function __construct(private readonly ChatEnterpriseCollaborationService $enterprise) {}

    /** Runs bounded maintenance for all active workspaces or one explicit workspace. */
    public function run(?int $workspaceId = null): array
    {
        $result = ['workspaces' => 0, 'external_expired' => 0, 'messages_purged' => 0, 'exports_expired' => 0, 'held_conversations' => 0];
        $workspaces = Workspace::query()->where('status', 'active')->when($workspaceId, fn ($query) => $query->whereKey($workspaceId));

        foreach ($workspaces->cursor() as $workspace) {
            $result['workspaces']++;
            $result['external_expired'] += $this->expireExternalMembers($workspace);
            $retentionPolicy = Schema::hasTable('data_governance_policies')
                ? DataGovernancePolicy::where(['workspace_id' => $workspace->id, 'dataset' => 'chat_messages'])->first()
                : null;
            $defaultDays = max(1, (int) ($retentionPolicy?->retention_days ?? 3650));

            ChatConversation::query()->where('workspace_id', $workspace->id)->orderBy('id')->chunkById(100, function ($conversations) use (&$result, $defaultDays) {
                foreach ($conversations as $conversation) {
                    if ($this->enterprise->isHeld($conversation)) { $result['held_conversations']++; continue; }
                    $days = max(1, (int) ($conversation->retention_days ?: $defaultDays));
                    $messageIds = ChatMessage::query()->where('conversation_id', $conversation->id)->where('created_at', '<', now()->subDays($days))->orderBy('id')->limit(5000)->pluck('id');
                    if ($messageIds->isEmpty()) continue;
                    $attachments = DB::table('chat_message_attachments')->whereIn('message_id', $messageIds)->get(['disk', 'path']);
                    foreach ($attachments as $attachment) {
                        try { if ($attachment->path && Storage::disk($attachment->disk ?: 'local')->exists($attachment->path)) Storage::disk($attachment->disk ?: 'local')->delete($attachment->path); } catch (\Throwable $exception) { report($exception); }
                    }
                    $purged = ChatMessage::whereIn('id', $messageIds)->delete();
                    $result['messages_purged'] += $purged;
                    if ($purged > 0) $this->enterprise->audit($conversation->workspace, $conversation, null, 'retention.messages_purged', null, null, ['count' => $purged, 'retention_days' => $days]);
                }
            });

            $result['exports_expired'] += $this->expireExports($workspace);
        }
        return $result;
    }

    /** Suspends expired external collaborators and revokes their workspace sessions without deleting message attribution. */
    private function expireExternalMembers(Workspace $workspace): int
    {
        if (! Schema::hasColumn('workspace_members', 'external_expires_at')) return 0;
        $members = WorkspaceMember::query()->where('workspace_id', $workspace->id)->where('status', 'active')
            ->whereIn('collaboration_type', ['guest', 'client', 'vendor'])->whereNotNull('external_expires_at')->where('external_expires_at', '<=', now())->get();
        foreach ($members as $member) {
            $member->update(['status' => 'suspended']);
            if (Schema::hasTable('workspace_access_sessions')) DB::table('workspace_access_sessions')->where('workspace_id', $workspace->id)->where('user_id', $member->user_id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'revoke_reason' => 'External collaboration access expired.']);
            $this->enterprise->audit($workspace, null, null, 'external.member_expired', $member, null, ['expired_at' => $member->external_expires_at?->toIso8601String()]);
        }
        return $members->count();
    }

    /** Deletes expired private export files while retaining an auditable expired job record. */
    private function expireExports(Workspace $workspace): int
    {
        if (! Schema::hasTable('chat_export_jobs')) return 0;
        $jobs = ChatExportJob::query()->where('workspace_id', $workspace->id)->where('status', 'completed')->whereNotNull('expires_at')->where('expires_at', '<=', now())->get();
        foreach ($jobs as $job) {
            try { if ($job->path && $job->disk && Storage::disk($job->disk)->exists($job->path)) Storage::disk($job->disk)->delete($job->path); } catch (\Throwable $exception) { report($exception); }
            $job->forceFill(['status' => 'expired', 'disk' => null, 'path' => null])->save();
        }
        return $jobs->count();
    }
}
