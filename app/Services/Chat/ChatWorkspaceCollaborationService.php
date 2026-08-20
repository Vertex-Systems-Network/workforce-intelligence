<?php

namespace App\Services\Chat;

use App\Events\ChatMessageChanged;
use App\Models\ChatBot;
use App\Models\ChatChannelResource;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatModerationEvent;
use App\Models\GeneratedDocument;
use App\Models\Project;
use App\Models\SafetyIncident;
use App\Models\Task;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Access\WorkScopeService;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Tasks\TaskActivityService;
use App\Services\Tasks\TaskWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Owns Chat V2.3 channel governance, workspace actions, bots, resources and slash-command workflows. */
class ChatWorkspaceCollaborationService
{
    /** Injects the existing chat, task, approval and scope services used by workspace collaboration actions. */
    public function __construct(
        private readonly ChatService $chat,
        private readonly WorkScopeService $workScope,
        private readonly TaskWorkflowService $taskWorkflow,
        private readonly TaskActivityService $taskActivity,
        private readonly ApprovalEngine $approvals,
    ) {}

    /** Returns public channels that the member can discover and join. */
    public function discoverPublicChannels(Workspace $workspace, WorkspaceMember $member): array
    {
        if ($member->isExternal()) return [];
        $joined = DB::table('chat_conversation_members')->where('member_id', $member->id)->pluck('conversation_id');
        return ChatConversation::query()
            ->where('workspace_id', $workspace->id)
            ->where('type', 'channel')
            ->where('visibility', 'public')
            ->whereNull('archived_at')
            ->whereNotIn('id', $joined)
            ->withCount('members')
            ->orderBy('name')
            ->get()
            ->map(fn (ChatConversation $conversation) => [
                'id' => $conversation->id,
                'name' => $conversation->name ?: 'Channel',
                'description' => $conversation->description,
                'channel_mode' => $conversation->channel_mode,
                'member_count' => $conversation->members_count,
            ])->all();
    }

    /** Joins an active public channel as a normal member. */
    public function joinPublicChannel(ChatConversation $conversation, WorkspaceMember $member): ChatConversation
    {
        abort_if($member->isExternal(), 403, 'External collaborators cannot discover or join additional public channels.');
        abort_unless($conversation->workspace_id === $member->workspace_id && $conversation->type === 'channel' && $conversation->visibility === 'public' && ! $conversation->archived_at, 404);
        $conversation->members()->syncWithoutDetaching([$member->id => ['role' => 'member', 'joined_at' => now(), 'notification_mode' => 'all']]);
        return $conversation->fresh(['members.user', 'resources']);
    }

    /** Leaves a channel while preventing the final owner from abandoning governance. */
    public function leaveChannel(ChatConversation $conversation, WorkspaceMember $member): void
    {
        $this->chat->assertMember($conversation, $member);
        abort_unless($conversation->type === 'channel', 422, 'Only channels can be left.');
        $role = $this->role($conversation, $member);
        if ($role === 'owner') {
            $owners = DB::table('chat_conversation_members')->where('conversation_id', $conversation->id)->where('role', 'owner')->count();
            abort_if($owners <= 1, 422, 'Assign another channel owner before leaving.');
        }
        $conversation->members()->detach($member->id);
    }

    /** Updates channel metadata, visibility, announcement mode, posting policy, lock state or archive state. */
    public function updateChannel(ChatConversation $conversation, WorkspaceMember $actor, array $data): ChatConversation
    {
        $this->assertChannelAdmin($conversation, $actor);
        abort_unless(in_array($conversation->type, ['channel', 'project', 'task'], true), 422, 'This conversation type does not support channel administration.');
        $updates = [];
        foreach (['name', 'description', 'visibility', 'channel_mode', 'posting_policy', 'is_locked'] as $field) {
            if (array_key_exists($field, $data)) $updates[$field] = $data[$field];
        }
        if (array_key_exists('archived', $data)) $updates['archived_at'] = $data['archived'] ? now() : null;
        $conversation->update($updates);
        $this->audit($conversation, $actor, 'channel.updated', null, ['changes' => $updates]);
        return $conversation->fresh(['members.user', 'project', 'task', 'resources']);
    }

    /** Adds active workspace members to a governed channel without weakening existing roles. */
    public function addMembers(ChatConversation $conversation, WorkspaceMember $actor, array $memberIds): array
    {
        $this->assertChannelAdmin($conversation, $actor);
        $requested = collect($memberIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $valid = WorkspaceMember::query()->where('workspace_id', $actor->workspace_id)->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->whereIn('id', $requested)->pluck('id');
        abort_if($valid->count() !== $requested->count(), 422, 'Every added participant must be an active workspace member.');
        $validMembers = WorkspaceMember::whereIn('id', $valid)->get()->keyBy('id');
        if (! $conversation->external_access && $validMembers->contains(fn (WorkspaceMember $candidate) => $candidate->isExternal())) abort(422, 'Enable external access before adding guest, client or vendor collaborators.');
        foreach ($valid as $id) {
            $candidate = $validMembers->get($id);
            $conversation->members()->syncWithoutDetaching([$id => ['role' => 'member', 'joined_at' => now(), 'notification_mode' => 'all', 'guest_expires_at' => $candidate?->isExternal() ? $candidate->external_expires_at : null]]);
        }
        $this->audit($conversation, $actor, 'channel.members_added', null, ['member_ids' => $valid->values()->all()]);
        return $conversation->fresh('members.user')->members->map(fn ($member) => $this->memberPayload($member))->all();
    }

    /** Removes a channel member while protecting the final owner and the acting owner's own governance. */
    public function removeMember(ChatConversation $conversation, WorkspaceMember $actor, WorkspaceMember $target): void
    {
        $this->assertChannelAdmin($conversation, $actor);
        abort_unless($target->workspace_id === $actor->workspace_id && $conversation->members()->where('workspace_members.id', $target->id)->exists(), 404);
        $targetRole = $this->role($conversation, $target);
        if ($targetRole === 'owner') {
            $owners = DB::table('chat_conversation_members')->where('conversation_id', $conversation->id)->where('role', 'owner')->count();
            abort_if($owners <= 1, 422, 'The final channel owner cannot be removed.');
            abort_unless($this->role($conversation, $actor) === 'owner' || $actor->hasPermission('chat.manage'), 403);
        }
        $conversation->members()->detach($target->id);
        $this->audit($conversation, $actor, 'channel.member_removed', $target);
    }

    /** Changes a channel member role using owner/admin governance boundaries. */
    public function updateMemberRole(ChatConversation $conversation, WorkspaceMember $actor, WorkspaceMember $target, string $role): void
    {
        $this->assertChannelAdmin($conversation, $actor);
        abort_unless(in_array($role, ['owner', 'admin', 'moderator', 'member', 'read_only'], true), 422, 'Invalid channel role.');
        abort_unless($conversation->members()->where('workspace_members.id', $target->id)->exists(), 404);
        $targetRole = $this->role($conversation, $target);
        if ($targetRole === 'owner' && $role !== 'owner') {
            $owners = DB::table('chat_conversation_members')->where('conversation_id', $conversation->id)->where('role', 'owner')->count();
            abort_if($owners <= 1, 422, 'Assign another channel owner before demoting the final owner.');
        }
        if ($role === 'owner' || $targetRole === 'owner') {
            abort_unless($this->role($conversation, $actor) === 'owner' || $actor->hasPermission('chat.manage'), 403);
        }
        DB::table('chat_conversation_members')->where(['conversation_id' => $conversation->id, 'member_id' => $target->id])->update(['role' => $role]);
        $this->audit($conversation, $actor, 'channel.member_role_updated', $target, ['role' => $role]);
    }

    /** Saves a member's per-conversation notification mode and optional snooze timestamp. */
    public function updateNotificationMode(ChatConversation $conversation, WorkspaceMember $member, string $mode, ?string $snoozedUntil): array
    {
        $this->chat->assertMember($conversation, $member);
        abort_unless(in_array($mode, ['all', 'mentions', 'nothing'], true), 422, 'Invalid notification mode.');
        DB::table('chat_conversation_members')->where(['conversation_id' => $conversation->id, 'member_id' => $member->id])->update([
            'notification_mode' => $mode,
            'is_muted' => $mode === 'nothing',
            'notifications_snoozed_until' => $snoozedUntil ?: null,
        ]);
        return ['mode' => $mode, 'snoozed_until' => $snoozedUntil, 'muted' => $mode === 'nothing'];
    }

    /** Lists resources pinned to a conversation with permission-safe entity-card metadata. */
    public function resources(ChatConversation $conversation, WorkspaceMember $member): array
    {
        $this->chat->assertMember($conversation, $member);
        return $conversation->resources()->orderBy('sort_order')->orderBy('id')->get()->map(fn (ChatChannelResource $resource) => $this->resourcePayload($resource, $member))->all();
    }

    /** Pins a link or internal WorkIntel resource to a governed channel. */
    public function addResource(ChatConversation $conversation, WorkspaceMember $actor, array $data): ChatChannelResource
    {
        $this->assertChannelModerator($conversation, $actor);
        $kind = (string) ($data['kind'] ?? 'link');
        $resourceId = isset($data['resource_id']) ? (int) $data['resource_id'] : null;
        $resourceType = $data['resource_type'] ?? null;
        if ($kind === 'link') abort_unless(Str::startsWith((string) ($data['url'] ?? ''), ['https://', 'http://']), 422, 'Resource links must use HTTP or HTTPS.');
        if ($kind === 'project') {
            $project = Project::where('workspace_id', $actor->workspace_id)->find($resourceId);
            abort_unless($project && $this->workScope->canViewProject($actor, $project), 422, 'Choose a visible project.');
            $resourceType = 'project';
        }
        if ($kind === 'task') {
            $task = Task::where('workspace_id', $actor->workspace_id)->find($resourceId);
            abort_unless($task && $this->workScope->canViewTask($actor, $task), 422, 'Choose a visible task.');
            $resourceType = 'task';
        }
        if ($kind === 'document') {
            abort_unless($actor->hasPermission('documents.view'), 403, 'You cannot link generated documents.');
            abort_unless(GeneratedDocument::where('workspace_id', $actor->workspace_id)->whereKey($resourceId)->exists(), 422, 'Choose an available workspace document.');
            $resourceType = 'generated_document';
        }
        $resource = ChatChannelResource::create([
            'workspace_id' => $actor->workspace_id, 'conversation_id' => $conversation->id,
            'kind' => $kind, 'label' => trim($data['label']), 'url' => $data['url'] ?? null,
            'resource_type' => $resourceType, 'resource_id' => $resourceId,
            'metadata' => $data['metadata'] ?? null, 'sort_order' => $data['sort_order'] ?? 1000, 'created_by_member_id' => $actor->id,
        ]);
        $this->audit($conversation, $actor, 'channel.resource_added', null, ['resource_id' => $resource->id, 'kind' => $resource->kind]);
        return $resource;
    }

    /** Removes a pinned channel resource for channel moderators and administrators. */
    public function deleteResource(ChatChannelResource $resource, WorkspaceMember $actor): void
    {
        $conversation = $resource->conversation;
        $this->assertChannelModerator($conversation, $actor);
        abort_unless($resource->workspace_id === $actor->workspace_id, 404);
        $conversation = $resource->conversation;
        $resourceId = $resource->id;
        $resource->delete();
        if ($conversation) $this->audit($conversation, $actor, 'channel.resource_removed', null, ['resource_id' => $resourceId]);
    }

    /** Resolves one linked WorkIntel resource into a concise card without leaking newly restricted entities. */
    private function resourcePayload(ChatChannelResource $resource, WorkspaceMember $member): array
    {
        $payload=$resource->only(['id','kind','label','url','resource_type','resource_id','metadata','sort_order']);$entity=null;
        if($resource->kind==='project'&&$resource->resource_id){$row=Project::where('workspace_id',$member->workspace_id)->find($resource->resource_id);if($row&&$this->workScope->canViewProject($member,$row))$entity=['type'=>'project','id'=>$row->id,'title'=>$row->name,'status'=>$row->status??null,'due_at'=>$row->due_date??null];}
        if($resource->kind==='task'&&$resource->resource_id){$row=Task::where('workspace_id',$member->workspace_id)->find($resource->resource_id);if($row&&$this->workScope->canViewTask($member,$row))$entity=['type'=>'task','id'=>$row->id,'title'=>$row->title,'status'=>$row->status??null,'priority'=>$row->priority??null,'due_at'=>$row->due_date??null];}
        if($resource->kind==='document'&&$resource->resource_id&&$member->hasPermission('documents.view')){$row=GeneratedDocument::where('workspace_id',$member->workspace_id)->find($resource->resource_id);if($row)$entity=['type'=>'document','id'=>$row->id,'title'=>$row->filename,'status'=>$row->workflow_status??null,'generated_at'=>$row->generated_at??null];}
        $payload['entity']=$entity;$payload['available']=$resource->kind==='link'||$entity!==null;return $payload;
    }

    /** Creates a real WorkIntel task linked back to a source chat message. */
    public function createTaskFromMessage(ChatMessage $message, WorkspaceMember $actor, array $data, bool $postCard = true): Task
    {
        $conversation = $message->conversation;
        $this->chat->assertMember($conversation, $actor);
        abort_unless($actor->hasPermission('tasks.manage') || $actor->hasPermission('tasks.manage_team'), 403, 'You cannot create tasks from chat.');
        $projectId = (int) ($data['project_id'] ?? $conversation->project_id ?? $conversation->task?->project_id ?? 0);
        $project = Project::where('workspace_id', $actor->workspace_id)->find($projectId);
        abort_unless($project && $this->workScope->canViewProject($actor, $project), 422, 'Choose a visible project for the task.');
        $workspace = $conversation->workspace;
        $this->taskWorkflow->ensureDefaults($workspace);
        $status = $this->taskWorkflow->defaultStatus($workspace->id);
        $task = Task::create([
            'workspace_id' => $workspace->id, 'project_id' => $project->id, 'task_status_id' => $status->id,
            'owner_member_id' => $actor->id, 'title' => trim($data['title'] ?? Str::limit((string) $message->body, 180)),
            'description' => trim($data['description'] ?? (string) $message->body), 'status' => $status->slug,
            'priority' => $data['priority'] ?? 'medium', 'position' => $this->taskWorkflow->nextPosition($workspace->id, $status->id),
            'billable' => false, 'client_visible' => false, 'created_by' => $actor->user_id,
        ]);
        $task->assignees()->sync([$actor->id]);
        $this->taskActivity->log($task, $actor, 'created_from_chat', ['message_id' => $message->id, 'conversation_id' => $conversation->id]);
        if ($postCard) {
            $this->postActionCard($conversation, 'task_created', "Task created: {$task->title}", ['task_id' => $task->id, 'project_id' => $project->id, 'source_message_id' => $message->id]);
        }
        return $task;
    }

    /** Submits the source chat message into the unified approval engine using a default chat approval workflow. */
    public function createApprovalFromMessage(ChatMessage $message, WorkspaceMember $actor, array $data): ?\App\Models\ApprovalRequest
    {
        $conversation = $message->conversation;
        $this->chat->assertMember($conversation, $actor);
        abort_unless($actor->hasPermission('approvals.view_own') || $actor->hasPermission('approvals.review'), 403, 'You cannot submit approvals from chat.');
        $approval = $this->approvals->submitFor(
            $conversation->workspace, $actor, 'chat.approval.submitted', 'chat_message', $message,
            ['conversation_id' => $conversation->id, 'message_id' => $message->id],
            trim($data['title'] ?? 'Chat approval request'), trim($data['summary'] ?? (string) $message->body),
        );
        abort_if(! $approval, 422, 'No active approval workflow is available for chat requests.');
        $this->postActionCard($conversation, 'approval_created', "Approval submitted: {$approval->title}", ['approval_request_id' => $approval->id, 'source_message_id' => $message->id]);
        return $approval;
    }

    /** Creates a real safety incident from a chat message when the Field Workforce module schema is installed. */
    public function createIncidentFromMessage(ChatMessage $message, WorkspaceMember $actor, array $data): SafetyIncident
    {
        $conversation = $message->conversation;
        $this->chat->assertMember($conversation, $actor);
        abort_unless($actor->hasPermission('field.incidents.manage') || $actor->hasPermission('field.manage'), 403, 'You cannot create incidents from chat.');
        abort_unless(Schema::hasTable('safety_incidents'), 422, 'Safety incident module is not installed.');
        $incident = SafetyIncident::create([
            'uuid' => (string) Str::uuid(), 'workspace_id' => $actor->workspace_id, 'reporter_member_id' => $actor->id,
            'incident_number' => 'CHAT-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'type' => $data['type'] ?? 'other', 'severity' => $data['severity'] ?? 'medium', 'status' => 'open',
            'title' => trim($data['title'] ?? Str::limit((string) $message->body, 180)),
            'description' => trim($data['description'] ?? (string) $message->body), 'occurred_at' => now(),
            'immediate_action' => $data['immediate_action'] ?? null,
        ]);
        $this->postActionCard($conversation, 'incident_created', "Incident created: {$incident->title}", ['incident_id' => $incident->id, 'source_message_id' => $message->id]);
        return $incident;
    }

    /** Executes supported slash commands and returns a resulting message, or null for normal chat text. */
    public function executeSlashCommand(ChatConversation $conversation, WorkspaceMember $actor, string $body): ?ChatMessage
    {
        if (! Str::startsWith(trim($body), '/')) return null;
        [$command, $arguments] = array_pad(preg_split('/\s+/', trim($body), 2), 2, '');
        return match (strtolower($command)) {
            '/help' => $this->postBotMessage($conversation, 'system', 'Commands: /help, /task <title>, /assign @[member:ID], /poll <question> | <option> | <option>, /status'),
            '/status' => $this->postBotMessage($conversation, 'system', $this->channelStatusText($conversation)),
            '/task' => $this->commandTask($conversation, $actor, $arguments),
            '/assign' => $this->commandAssign($conversation, $actor, $arguments),
            '/poll' => $this->commandPoll($conversation, $actor, $arguments),
            default => throw ValidationException::withMessages(['body' => ["Unknown chat command {$command}. Use /help for available commands."]]),
        };
    }

    /** Seeds or returns the built-in system and automation bot identities for a workspace. */
    public function ensureBots(Workspace $workspace): array
    {
        $definitions = [['system', 'WorkIntel System', 'system'], ['automation', 'WorkIntel Automation', 'automation']];
        return collect($definitions)->map(fn ($definition) => ChatBot::firstOrCreate(
            ['workspace_id' => $workspace->id, 'slug' => $definition[0]],
            ['name' => $definition[1], 'kind' => $definition[2], 'is_active' => true],
        ))->all();
    }

    /** Posts a structured message from a built-in bot identity. */
    public function postBotMessage(ChatConversation $conversation, string $botSlug, string $body, string $type = 'bot', array $metadata = []): ChatMessage
    {
        $this->ensureBots($conversation->workspace);
        $bot = ChatBot::where(['workspace_id' => $conversation->workspace_id, 'slug' => $botSlug, 'is_active' => true])->firstOrFail();
        $message = ChatMessage::create([
            'uuid' => (string) Str::uuid(), 'workspace_id' => $conversation->workspace_id, 'conversation_id' => $conversation->id,
            'sender_bot_id' => $bot->id, 'message_type' => $type, 'body' => $body, 'metadata' => $metadata, 'mentions' => [],
        ]);
        $conversation->touch();
        $message->load(['senderBot', 'attachments', 'reactions']);
        try { broadcast(new ChatMessageChanged($message, 'created'))->toOthers(); } catch (\Throwable $exception) { report($exception); }
        return $message;
    }

    /** Enforces channel posting rules before a member sends content or a poll. */
    public function assertCanPost(ChatConversation $conversation, WorkspaceMember $member): void
    {
        $this->chat->assertMember($conversation, $member);
        if ($member->isExternal()) abort_unless((bool) $conversation->external_access, 403, 'External access is disabled for this conversation.');
        $role = $this->role($conversation, $member);
        abort_if($role === 'read_only', 403, 'This channel is read-only for your role.');
        if ($conversation->is_locked) abort_unless(in_array($role, ['owner', 'admin', 'moderator'], true) || $member->hasPermission('chat.moderate'), 403, 'This channel is locked.');
        if ($conversation->channel_mode === 'announcement' || $conversation->posting_policy === 'admins') {
            abort_unless(in_array($role, ['owner', 'admin', 'moderator'], true) || $member->hasPermission('chat.moderate'), 403, 'Only channel moderators can post here.');
        }
    }

    /** Returns the current member role from the conversation pivot. */
    public function role(ChatConversation $conversation, WorkspaceMember $member): string
    {
        return (string) (DB::table('chat_conversation_members')->where(['conversation_id' => $conversation->id, 'member_id' => $member->id])->value('role') ?: 'member');
    }

    /** Enforces owner/admin channel management rights with workspace chat-manage override. */
    private function assertChannelAdmin(ChatConversation $conversation, WorkspaceMember $actor): void
    {
        $this->chat->assertMember($conversation, $actor);
        abort_unless(in_array($this->role($conversation, $actor), ['owner', 'admin'], true) || $actor->hasPermission('chat.manage'), 403, 'Channel administrator access is required.');
    }

    /** Enforces channel moderation rights with workspace chat-moderate/manage override. */
    private function assertChannelModerator(ChatConversation $conversation, WorkspaceMember $actor): void
    {
        $this->chat->assertMember($conversation, $actor);
        abort_unless(in_array($this->role($conversation, $actor), ['owner', 'admin', 'moderator'], true) || $actor->hasPermission('chat.moderate') || $actor->hasPermission('chat.manage'), 403, 'Channel moderator access is required.');
    }

    /** Shapes a channel member including its governance role. */
    private function memberPayload(WorkspaceMember $member): array
    {
        return ['id' => $member->id, 'name' => trim(($member->user?->first_name ?? '').' '.($member->user?->last_name ?? '')), 'role' => $member->pivot?->role ?? 'member', 'collaboration_type' => $member->collaboration_type ?? 'internal', 'external_company' => $member->external_company, 'external_expires_at' => $member->external_expires_at?->toIso8601String()];
    }

    /** Writes a lightweight channel-governance audit event when Chat V2.4 tables are installed. */
    private function audit(ChatConversation $conversation, WorkspaceMember $actor, string $action, ?WorkspaceMember $target = null, ?array $metadata = null): void
    {
        if (! Schema::hasTable('chat_moderation_events')) return;
        ChatModerationEvent::create([
            'workspace_id' => $conversation->workspace_id, 'conversation_id' => $conversation->id,
            'actor_member_id' => $actor->id, 'target_member_id' => $target?->id, 'action' => $action,
            'metadata' => $metadata, 'created_at' => now(),
        ]);
    }

    /** Creates a task from the slash command using the linked project context. */
    private function commandTask(ChatConversation $conversation, WorkspaceMember $actor, string $title): ChatMessage
    {
        $this->assertCanPost($conversation, $actor);
        abort_if(trim($title) === '', 422, 'Usage: /task <title>');
        $source = $this->chat->send($conversation, $actor, trim($title), null, []);
        $task = $this->createTaskFromMessage($source, $actor, ['title' => trim($title)], false);
        return $this->postBotMessage($conversation, 'system', "Created task #{$task->id}: {$task->title}", 'action', ['action_type' => 'task_created', 'task_id' => $task->id, 'project_id' => $task->project_id, 'source_message_id' => $source->id]);
    }

    /** Assigns the task linked to the current task conversation to an active mentioned member. */
    private function commandAssign(ChatConversation $conversation, WorkspaceMember $actor, string $arguments): ChatMessage
    {
        $this->assertCanPost($conversation, $actor);
        abort_unless($actor->hasPermission('tasks.manage') || $actor->hasPermission('tasks.manage_team'), 403, 'You cannot assign tasks from chat.');
        $task = $conversation->task;
        abort_unless($task && $task->workspace_id === $actor->workspace_id, 422, 'The /assign command is available in task-linked conversations.');

        preg_match('/@\[member:(\d+)\]|^(\d+)$/', trim($arguments), $matches);
        $memberId = (int) ($matches[1] ?? $matches[2] ?? 0);
        abort_if($memberId <= 0, 422, 'Usage: /assign @[member:ID]');
        $target = WorkspaceMember::with('user')
            ->where('workspace_id', $actor->workspace_id)
            ->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->find($memberId);
        abort_unless($target, 422, 'Choose an active workspace member.');
        abort_unless($conversation->members()->where('workspace_members.id', $target->id)->exists(), 422, 'The assignee must be a member of this task conversation.');

        $task->assignees()->syncWithoutDetaching([$target->id]);
        $this->taskActivity->log($task, $actor, 'assigned_from_chat', ['member_id' => $target->id, 'conversation_id' => $conversation->id]);
        $name = trim(($target->user?->first_name ?? '').' '.($target->user?->last_name ?? '')) ?: 'member #'.$target->id;
        return $this->postBotMessage($conversation, 'automation', "Assigned task #{$task->id} to {$name}.", 'action', ['action_type' => 'task_assigned', 'task_id' => $task->id, 'member_id' => $target->id]);
    }

    /** Creates a poll from a pipe-delimited slash command. */
    private function commandPoll(ChatConversation $conversation, WorkspaceMember $actor, string $arguments): ChatMessage
    {
        $this->assertCanPost($conversation, $actor);
        $parts = collect(explode('|', $arguments))->map(fn ($part) => trim($part))->filter()->values();
        abort_if($parts->count() < 3, 422, 'Usage: /poll Question | Option one | Option two');
        return $this->chat->createPoll($conversation, $actor, (string) $parts->shift(), $parts->all(), false, null);
    }

    /** Returns a concise channel status string for the /status command. */
    private function channelStatusText(ChatConversation $conversation): string
    {
        return sprintf('%s · %s · %s · %s', ucfirst($conversation->visibility ?? 'private'), ucfirst($conversation->channel_mode ?? 'standard'), $conversation->is_locked ? 'Locked' : 'Open', $conversation->members()->count().' members');
    }

    /** Posts a system action card after creating a task, approval or incident. */
    private function postActionCard(ChatConversation $conversation, string $type, string $body, array $metadata): void
    {
        $this->postBotMessage($conversation, 'automation', $body, 'action', ['action_type' => $type] + $metadata);
    }
}
