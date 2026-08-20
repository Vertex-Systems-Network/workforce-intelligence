<?php

namespace App\Services\Chat;

use App\Events\ChatMessageChanged;
use App\Models\ChatConversation;
use App\Models\ChatDlpEvent;
use App\Models\ChatDlpPolicy;
use App\Models\ChatExportJob;
use App\Models\ChatLegalHold;
use App\Models\ChatMessage;
use App\Models\ChatMessageEditHistory;
use App\Models\ChatModerationEvent;
use App\Models\DataGovernancePolicy;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Services\Identity\WorkspaceRegistrationService;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;

/** Owns guest collaboration, legal hold, eDiscovery, retention policy and DLP administration for chat. */
class ChatEnterpriseCollaborationService
{
    /** Injects identity and core chat services reused by enterprise collaboration workflows. */
    public function __construct(
        private readonly WorkspaceRegistrationService $registration,
        private readonly ChatService $chat,
    ) {}

    /** Returns only enterprise collaboration sections authorized for the requesting administrator. */
    public function overview(Workspace $workspace, WorkspaceMember $actor): array
    {
        $canManage = $actor->hasPermission('chat.manage');
        $canGuests = $canManage || $actor->hasPermission('chat.guests_manage');
        $canRetention = $canManage || $actor->hasPermission('chat.retention_manage');
        $canExport = $canManage || $actor->hasPermission('chat.export');
        $canLegalHold = $canManage || $actor->hasPermission('chat.legal_hold_manage');
        $canDlp = $canManage || $actor->hasPermission('chat.dlp_manage');
        $canModerate = $canManage || $actor->hasPermission('chat.moderate');

        $externalMembers = $canGuests
            ? WorkspaceMember::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('collaboration_type', ['guest', 'client', 'vendor'])
                ->with('user:id,first_name,last_name,email,status')
                ->orderByDesc('id')->limit(100)->get()
                ->map(fn (WorkspaceMember $member) => $this->externalMemberPayload($member))->all()
            : [];

        $invitations = $canGuests
            ? WorkspaceInvitation::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('collaboration_type', ['guest', 'client', 'vendor'])
                ->whereNull('accepted_at')->orderByDesc('id')->limit(100)->get()
                ->map(fn (WorkspaceInvitation $invite) => [
                    'id' => $invite->id, 'uuid' => $invite->uuid, 'email' => $invite->email,
                    'collaboration_type' => $invite->collaboration_type, 'external_company' => $invite->external_company,
                    'external_expires_at' => $invite->external_expires_at?->toIso8601String(),
                    'conversation_id' => $invite->chat_conversation_id, 'expires_at' => $invite->expires_at?->toIso8601String(),
                ])->all()
            : [];

        return [
            'external_members' => $externalMembers,
            'pending_external_invitations' => $invitations,
            'legal_holds' => $canLegalHold ? ChatLegalHold::where('workspace_id', $workspace->id)->orderByDesc('id')->limit(100)->get() : [],
            'exports' => $canExport ? ChatExportJob::where('workspace_id', $workspace->id)->where('requested_by_member_id', $actor->id)->orderByDesc('id')->limit(50)->get() : [],
            'dlp_policies' => $canDlp ? ChatDlpPolicy::where('workspace_id', $workspace->id)->orderBy('name')->get() : [],
            'dlp_events' => $canDlp ? ChatDlpEvent::where('workspace_id', $workspace->id)->orderByDesc('id')->limit(100)->get() : [],
            'moderation_events' => $canModerate ? ChatModerationEvent::where('workspace_id', $workspace->id)->orderByDesc('id')->limit(100)->get() : [],
            'workspace_chat_retention' => $canRetention ? DataGovernancePolicy::where(['workspace_id' => $workspace->id, 'dataset' => 'chat_messages'])->first() : null,
        ];
    }

    /** Creates a single-conversation external collaborator invitation with a hard access expiry. */
    public function inviteExternal(Workspace $workspace, WorkspaceMember $actor, ChatConversation $conversation, array $data): array
    {
        abort_unless($conversation->workspace_id === $workspace->id, 404);
        abort_unless((bool) $conversation->external_access, 422, 'Enable external access on this conversation before inviting guests.');
        abort_if($actor->isExternal(), 403, 'External collaborators cannot invite other external users.');

        $type = $data['collaboration_type'];
        abort_unless(in_array($type, ['guest', 'client', 'vendor'], true), 422, 'Invalid external collaboration type.');
        $expires = CarbonImmutable::parse($data['external_expires_at']);
        abort_unless($expires->isFuture() && $expires->lte(now()->addYear()), 422, 'External access expiry must be within the next year.');

        $role = $this->ensureExternalRole($workspace);
        $result = $this->registration->createInvitation($workspace, [
            'email' => strtolower($data['email']),
            'role_slug' => $role->slug,
            'employment_type' => 'contractor',
            'collaboration_type' => $type,
            'external_company' => trim((string) ($data['external_company'] ?? '')) ?: null,
            'external_expires_at' => $expires,
            'chat_conversation_id' => $conversation->id,
            'invitation_expires_at' => $expires->lt(now()->addDays(14)) ? $expires : now()->addDays(14),
        ], $actor->user);

        $this->audit($workspace, $conversation, $actor, 'external.invitation_created', null, null, [
            'email' => strtolower($data['email']), 'collaboration_type' => $type, 'expires_at' => $expires->toIso8601String(),
        ]);
        return $result;
    }

    /** Extends or revokes an external collaborator without deleting historical message attribution. */
    public function updateExternalMember(Workspace $workspace, WorkspaceMember $actor, WorkspaceMember $target, array $data): WorkspaceMember
    {
        abort_unless($target->workspace_id === $workspace->id && $target->isExternal(), 404);
        abort_if($actor->id === $target->id, 422, 'Use another administrator to manage your own external access.');
        $updates = [];
        if (array_key_exists('external_company', $data)) $updates['external_company'] = trim((string) $data['external_company']) ?: null;
        if (! empty($data['external_expires_at'])) {
            $expires = CarbonImmutable::parse($data['external_expires_at']);
            abort_unless($expires->isFuture() && $expires->lte(now()->addYear()), 422, 'External access expiry must be within the next year.');
            $updates['external_expires_at'] = $expires;
            DB::table('chat_conversation_members')->where('member_id', $target->id)->update(['guest_expires_at' => $expires]);
        }
        if (($data['action'] ?? null) === 'revoke') $updates['status'] = 'suspended';
        if (($data['action'] ?? null) === 'restore') {
            abort_if($target->external_expires_at?->isPast(), 422, 'Extend the expiry before restoring this collaborator.');
            $updates['status'] = 'active';
        }
        $target->update($updates);
        $this->audit($workspace, null, $actor, 'external.member_'.($data['action'] ?? 'updated'), $target, null, ['expires_at' => $target->fresh()->external_expires_at?->toIso8601String()]);
        return $target->fresh('user');
    }

    /** Updates enterprise policy for a governed conversation while legal holds remain separately auditable. */
    public function updateConversationPolicy(ChatConversation $conversation, WorkspaceMember $actor, array $data): ChatConversation
    {
        abort_unless($conversation->workspace_id === $actor->workspace_id, 404);
        $canManage = $actor->hasPermission('chat.manage');
        $updates = [];
        if (array_key_exists('external_access', $data)) {
            abort_unless($canManage || $actor->hasPermission('chat.guests_manage'), 403, 'You cannot change external collaboration access.');
            $updates['external_access'] = $data['external_access'];
        }
        if (array_key_exists('retention_days', $data)) {
            abort_unless($canManage || $actor->hasPermission('chat.retention_manage'), 403, 'You cannot change chat retention.');
            $updates['retention_days'] = $data['retention_days'];
        }
        if (array_key_exists('export_policy', $data)) {
            abort_unless($canManage || $actor->hasPermission('chat.export'), 403, 'You cannot change conversation export policy.');
            $updates['export_policy'] = $data['export_policy'];
        }
        if (array_key_exists('dlp_mode', $data)) {
            abort_unless($canManage || $actor->hasPermission('chat.dlp_manage'), 403, 'You cannot change conversation DLP mode.');
            $updates['dlp_mode'] = $data['dlp_mode'];
        }
        $conversation->update($updates);
        $this->audit($conversation->workspace, $conversation, $actor, 'conversation.enterprise_policy_updated', null, null, $updates);
        return $conversation->fresh(['members.user', 'resources']);
    }

    /** Creates a workspace-wide or conversation-specific legal hold that overrides retention deletion. */
    public function createLegalHold(Workspace $workspace, WorkspaceMember $actor, array $data): ChatLegalHold
    {
        $conversation = null;
        if (! empty($data['conversation_id'])) {
            $conversation = ChatConversation::where('workspace_id', $workspace->id)->findOrFail((int) $data['conversation_id']);
        }
        $hold = ChatLegalHold::create([
            'uuid' => (string) Str::uuid(), 'workspace_id' => $workspace->id, 'conversation_id' => $conversation?->id,
            'name' => trim($data['name']), 'reason' => trim((string) ($data['reason'] ?? '')) ?: null,
            'status' => 'active', 'created_by_member_id' => $actor->id, 'created_at' => now(),
            'metadata' => ['source' => 'chat_v2_4'],
        ]);
        if ($conversation) $conversation->update(['legal_hold' => true]);
        $this->audit($workspace, $conversation, $actor, 'legal_hold.created', null, null, ['hold_id' => $hold->id]);
        return $hold;
    }

    /** Releases one legal hold while preserving its immutable lifecycle record. */
    public function releaseLegalHold(Workspace $workspace, WorkspaceMember $actor, ChatLegalHold $hold): ChatLegalHold
    {
        abort_unless($hold->workspace_id === $workspace->id, 404);
        abort_if($hold->status !== 'active', 422, 'This legal hold is already released.');
        $hold->update(['status' => 'released', 'released_by_member_id' => $actor->id, 'released_at' => now()]);
        if ($hold->conversation_id) {
            $stillHeld = ChatLegalHold::where('workspace_id', $workspace->id)->where('conversation_id', $hold->conversation_id)->where('status', 'active')->exists()
                || ChatLegalHold::where('workspace_id', $workspace->id)->whereNull('conversation_id')->where('status', 'active')->exists();
            if (! $stillHeld) ChatConversation::whereKey($hold->conversation_id)->update(['legal_hold' => false]);
        }
        $this->audit($workspace, $hold->conversation_id ? ChatConversation::find($hold->conversation_id) : null, $actor, 'legal_hold.released', null, null, ['hold_id' => $hold->id]);
        return $hold->fresh();
    }

    /** Returns true when conversation data must be preserved regardless of normal retention settings. */
    public function isHeld(ChatConversation $conversation): bool
    {
        if ((bool) $conversation->legal_hold) return true;
        if (Schema::hasTable('data_governance_policies') && DataGovernancePolicy::where(['workspace_id' => $conversation->workspace_id, 'dataset' => 'chat_messages', 'legal_hold' => true])->exists()) return true;
        if (! Schema::hasTable('chat_legal_holds')) return false;
        return ChatLegalHold::where('workspace_id', $conversation->workspace_id)->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('conversation_id')->orWhere('conversation_id', $conversation->id))->exists();
    }

    /** Generates an expiring private JSON or CSV eDiscovery export for one conversation. */
    public function exportConversation(ChatConversation $conversation, WorkspaceMember $actor, string $format): ChatExportJob
    {
        abort_unless($conversation->workspace_id === $actor->workspace_id, 404);
        abort_if($actor->isExternal(), 403, 'External collaborators cannot export conversations.');
        $this->assertExportPolicy($conversation, $actor);
        $format = in_array($format, ['json', 'csv'], true) ? $format : 'json';
        $job = ChatExportJob::create([
            'uuid' => (string) Str::uuid(), 'workspace_id' => $conversation->workspace_id, 'conversation_id' => $conversation->id,
            'requested_by_member_id' => $actor->id, 'format' => $format, 'status' => 'running', 'created_at' => now(), 'expires_at' => now()->addDays(7),
        ]);

        try {
            $messages = ChatMessage::query()->where('conversation_id', $conversation->id)
                ->with(['sender.user:id,first_name,last_name,email', 'senderBot', 'attachments', 'editHistory', 'reactions', 'pins'])
                ->orderBy('id')->get();
            $payload = [
                'exported_at' => now()->toIso8601String(), 'workspace_id' => $conversation->workspace_id,
                'conversation' => [
                    'id' => $conversation->id, 'uuid' => $conversation->uuid, 'type' => $conversation->type, 'name' => $conversation->name,
                    'legal_hold' => $this->isHeld($conversation), 'retention_days' => $conversation->retention_days,
                ],
                'members' => $conversation->members()->with('user:id,first_name,last_name,email')->get()->map(fn ($member) => [
                    'id' => $member->id, 'name' => trim(($member->user?->first_name ?? '').' '.($member->user?->last_name ?? '')),
                    'email' => $member->user?->email, 'role' => $member->pivot?->role, 'collaboration_type' => $member->collaboration_type ?? 'internal',
                ])->all(),
                'messages' => $messages->map(fn (ChatMessage $message) => [
                    'id' => $message->id, 'uuid' => $message->uuid, 'parent_id' => $message->parent_id,
                    'sender_member_id' => $message->sender_member_id, 'sender_bot_id' => $message->sender_bot_id,
                    'body' => $message->body, 'mentions' => $message->mentions ?? [], 'created_at' => $message->created_at?->toIso8601String(),
                    'edited_at' => $message->edited_at?->toIso8601String(), 'deleted_at' => $message->deleted_at?->toIso8601String(),
                    'attachments' => $message->attachments->map(fn ($attachment) => $attachment->only(['id', 'filename', 'mime_type', 'size_bytes', 'checksum_sha256', 'security_status']))->all(),
                    'edit_history' => $message->editHistory->map(fn ($row) => ['id' => $row->id, 'body' => $row->body, 'mentions' => $row->mentions, 'edited_at' => $row->edited_at?->toIso8601String()])->all(),
                    'reactions' => $message->reactions->map(fn ($row) => $row->only(['member_id', 'emoji', 'created_at']))->all(),
                    'pinned' => $message->pins->isNotEmpty(),
                ])->all(),
            ];

            $contents = $format === 'csv' ? $this->csv($payload['messages']) : json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $path = 'private/chat-exports/'.$conversation->workspace_id.'/'.$job->uuid.'.'.$format;
            Storage::disk('local')->put($path, (string) $contents);
            $fullPath = Storage::disk('local')->path($path);
            $job->update([
                'status' => 'completed', 'disk' => 'local', 'path' => $path,
                'checksum_sha256' => hash_file('sha256', $fullPath), 'size_bytes' => filesize($fullPath), 'completed_at' => now(),
            ]);
            $this->audit($conversation->workspace, $conversation, $actor, 'ediscovery.export_generated', null, null, ['export_id' => $job->id, 'format' => $format]);
        } catch (\Throwable $exception) {
            $job->update(['status' => 'failed', 'error' => Str::limit($exception->getMessage(), 5000), 'completed_at' => now()]);
            throw $exception;
        }
        return $job->fresh();
    }

    /** Streams a completed private export after ownership and expiry checks. */
    public function downloadExport(ChatExportJob $job, WorkspaceMember $actor)
    {
        abort_unless($job->workspace_id === $actor->workspace_id && $job->requested_by_member_id === $actor->id, 403);
        abort_unless($job->status === 'completed' && $job->path && $job->disk, 404);
        abort_if($job->expires_at?->isPast(), 410, 'This export has expired.');
        abort_unless(Storage::disk($job->disk)->exists($job->path), 404);
        return Storage::disk($job->disk)->download($job->path, 'workintel-chat-export-'.$job->uuid.'.'.$job->format);
    }

    /** Creates a DLP policy using simple deterministic keyword and attachment metadata rules. */
    public function createDlpPolicy(Workspace $workspace, WorkspaceMember $actor, array $data): ChatDlpPolicy
    {
        $policy = ChatDlpPolicy::create([
            'uuid' => (string) Str::uuid(), 'workspace_id' => $workspace->id, 'name' => trim($data['name']),
            'mode' => $data['mode'], 'keywords' => array_values(array_unique(array_filter(array_map('trim', $data['keywords'] ?? [])))),
            'file_extensions' => array_values(array_unique(array_filter(array_map(fn ($value) => Str::lower(ltrim(trim((string) $value), '.')), $data['file_extensions'] ?? [])))),
            'max_file_bytes' => $data['max_file_bytes'] ?? null, 'active' => $data['active'] ?? true, 'created_by_member_id' => $actor->id,
        ]);
        $this->audit($workspace, null, $actor, 'dlp.policy_created', null, null, ['policy_id' => $policy->id, 'mode' => $policy->mode]);
        return $policy;
    }

    /** Updates or disables a workspace DLP policy without exposing historical DLP event contents. */
    public function updateDlpPolicy(Workspace $workspace, WorkspaceMember $actor, ChatDlpPolicy $policy, array $data): ChatDlpPolicy
    {
        abort_unless($policy->workspace_id === $workspace->id, 404);
        foreach (['name', 'mode', 'keywords', 'file_extensions', 'max_file_bytes', 'active'] as $field) if (array_key_exists($field, $data)) $policy->{$field} = $data[$field];
        if (is_array($policy->file_extensions)) $policy->file_extensions = array_values(array_unique(array_map(fn ($value) => Str::lower(ltrim(trim((string) $value), '.')), $policy->file_extensions)));
        $policy->save();
        $this->audit($workspace, null, $actor, 'dlp.policy_updated', null, null, ['policy_id' => $policy->id, 'mode' => $policy->mode, 'active' => (bool) $policy->active]);
        return $policy->fresh();
    }

    /** Moderates a message with an immutable audit event and legal-hold-safe edit snapshot. */
    public function moderateMessage(ChatMessage $message, WorkspaceMember $actor, string $action, ?string $reason): array
    {
        $this->chat->assertMember($message->conversation, $actor);
        abort_unless($this->chat->canModerateConversation($message->conversation, $actor), 403, 'Conversation moderator access is required.');
        abort_unless(in_array($action, ['flag', 'redact'], true), 422, 'Unsupported moderation action.');
        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $moderation = is_array($metadata['moderation'] ?? null) ? $metadata['moderation'] : [];
        if ($action === 'flag') {
            $moderation['flagged_at'] = now()->toIso8601String();
            $moderation['flagged_by_member_id'] = $actor->id;
            $message->update(['metadata' => array_merge($metadata, ['moderation' => $moderation])]);
            try { broadcast(new ChatMessageChanged($message, 'updated'))->toOthers(); } catch (\Throwable $exception) { report($exception); }
        }
        if ($action === 'redact' && ! $message->deleted_at) {
            ChatMessageEditHistory::create([
                'message_id' => $message->id, 'edited_by_member_id' => $actor->id, 'body' => $message->body,
                'mentions' => $message->mentions ?? [], 'edited_at' => now(),
            ]);
            $moderation['redacted_at'] = now()->toIso8601String();
            $moderation['redacted_by_member_id'] = $actor->id;
            $message->update(['body' => null, 'mentions' => [], 'deleted_at' => now(), 'metadata' => array_merge($metadata, ['moderation' => $moderation])]);
            try { broadcast(new ChatMessageChanged($message, 'deleted'))->toOthers(); } catch (\Throwable $exception) { report($exception); }
        }
        $event = $this->audit($message->conversation->workspace, $message->conversation, $actor, 'message.'.$action, null, $message, null, $reason);
        return ['action' => $action, 'event_id' => $event?->id, 'message_id' => $message->id];
    }

    /** Records an enterprise moderation event and returns it when the audit table exists. */
    public function audit(Workspace $workspace, ?ChatConversation $conversation, ?WorkspaceMember $actor, string $action, ?WorkspaceMember $target = null, ?ChatMessage $message = null, ?array $metadata = null, ?string $reason = null): ?ChatModerationEvent
    {
        if (! Schema::hasTable('chat_moderation_events')) return null;
        return ChatModerationEvent::create([
            'workspace_id' => $workspace->id, 'conversation_id' => $conversation?->id, 'message_id' => $message?->id,
            'actor_member_id' => $actor?->id, 'target_member_id' => $target?->id, 'action' => $action,
            'reason' => $reason, 'metadata' => $metadata, 'created_at' => now(),
        ]);
    }

    /** Ensures every workspace has a restrictive system role dedicated to external chat-only collaborators. */
    private function ensureExternalRole(Workspace $workspace): Role
    {
        PermissionCatalog::sync();
        $role = Role::firstOrCreate(
            ['workspace_id' => $workspace->id, 'slug' => 'external-collaborator'],
            ['name' => 'External Collaborator', 'description' => 'Chat-only guest/client/vendor access.', 'is_system' => true, 'status' => 'active'],
        );
        $role->permissions()->sync(Permission::whereIn('slug', ['chat.view', 'chat.create'])->pluck('id'));
        return $role;
    }

    /** Shapes an external member without returning credentials or unrelated HR fields. */
    private function externalMemberPayload(WorkspaceMember $member): array
    {
        return [
            'id' => $member->id, 'name' => trim(($member->user?->first_name ?? '').' '.($member->user?->last_name ?? '')),
            'email' => $member->user?->email, 'status' => $member->status->value, 'collaboration_type' => $member->collaboration_type,
            'external_company' => $member->external_company, 'external_expires_at' => $member->external_expires_at?->toIso8601String(),
            'expired' => $member->externalExpired(), 'external_scope' => $member->external_scope,
        ];
    }

    /** Enforces conversation export policy in addition to the workspace chat.export permission. */
    private function assertExportPolicy(ChatConversation $conversation, WorkspaceMember $actor): void
    {
        $policy = $conversation->export_policy ?? 'admins';
        abort_if($policy === 'disabled', 403, 'Exports are disabled for this conversation.');
        if ($actor->hasPermission('chat.manage')) return;
        $role = DB::table('chat_conversation_members')->where(['conversation_id' => $conversation->id, 'member_id' => $actor->id])->value('role') ?: 'member';
        if ($policy === 'admins') abort_unless(in_array($role, ['owner', 'admin'], true), 403);
        if ($policy === 'moderators') abort_unless(in_array($role, ['owner', 'admin', 'moderator'], true), 403);
    }

    /** Converts exported message rows to a spreadsheet-friendly CSV representation. */
    private function csv(array $messages): string
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['id', 'parent_id', 'sender_member_id', 'body', 'created_at', 'edited_at', 'deleted_at', 'attachment_count']);
        foreach ($messages as $message) fputcsv($stream, [
            $message['id'], $message['parent_id'], $message['sender_member_id'], $message['body'], $message['created_at'],
            $message['edited_at'], $message['deleted_at'], count($message['attachments'] ?? []),
        ]);
        rewind($stream); $contents = stream_get_contents($stream); fclose($stream);
        return (string) $contents;
    }
}
