<?php

namespace App\Services\Chat;

use App\Contracts\ChatDlpScanner;
use App\Events\ChatMessageChanged;
use App\Events\ChatTypingChanged;
use App\Models\ChatConversation;
use App\Models\ChatActivityState;
use App\Models\NotificationPreference;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\ChatMessageEditHistory;
use App\Models\ChatDraft;
use App\Models\ChatPoll;
use App\Models\ChatPollOption;
use App\Models\ChatPollVote;
use App\Models\ChatSavedMessage;
use App\Models\ChatThreadFollow;
use App\Models\GeneratedDocument;
use App\Models\ChatMessagePin;
use App\Models\ChatMessageReaction;
use App\Models\ChatPresence;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Access\WorkScopeService;
use App\Services\Notifications\WorkspaceNotificationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Owns workspace-scoped chat business rules, membership checks and payload shaping.
 */
class ChatService
{
    /**
     * Injects notification and work-scope collaborators used by chat workflows.
     */
    public function __construct(
        private readonly WorkspaceNotificationService $notifications,
        private readonly WorkScopeService $workScope,
        private readonly ChatDlpScanner $dlp,
    ) {}

    /**
     * Returns visible conversations with batched last-message, unread and draft lookups.
     */
    public function conversations(Workspace $workspace, WorkspaceMember $member): array
    {
        $rows = ChatConversation::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('archived_at')
            ->whereHas('members', fn ($query) => $query->where('workspace_members.id', $member->id))
            ->with(['members.user:id,first_name,last_name,email,status', 'project:id,name', 'task:id,title'])
            ->latest('updated_at')
            ->get();

        if ($rows->isEmpty()) return [];
        $conversationIds = $rows->pluck('id');
        $lastIds = ChatMessage::query()
            ->whereIn('conversation_id', $conversationIds)
            ->whereNull('parent_id')
            ->selectRaw('conversation_id, MAX(id) AS last_id')
            ->groupBy('conversation_id')
            ->pluck('last_id', 'conversation_id');
        $lastMessages = ChatMessage::query()->whereIn('id', $lastIds->values()->filter())->get()->keyBy('conversation_id');
        $unreads = DB::table('chat_messages as message')
            ->join('chat_conversation_members as membership', function ($join) use ($member) {
                $join->on('membership.conversation_id', '=', 'message.conversation_id')->where('membership.member_id', '=', $member->id);
            })
            ->whereIn('message.conversation_id', $conversationIds)
            ->whereNull('message.parent_id')
            ->whereRaw('message.id > COALESCE(membership.last_read_message_id, 0)')
            ->where(function ($query) use ($member) {
                $query->whereNull('message.sender_member_id')->orWhere('message.sender_member_id', '!=', $member->id);
            })
            ->groupBy('message.conversation_id')
            ->selectRaw('message.conversation_id, COUNT(*) AS unread_count')
            ->pluck('unread_count', 'message.conversation_id');
        $drafts = ChatDraft::query()
            ->where('member_id', $member->id)
            ->whereIn('conversation_id', $conversationIds)
            ->get()
            ->keyBy('conversation_id');

        return $rows->map(fn (ChatConversation $conversation) => $this->conversationPayload(
            $conversation,
            $lastMessages->get($conversation->id),
            (int) ($unreads[$conversation->id] ?? 0),
            $member,
            $drafts->get($conversation->id),
        ))->all();
    }

    /**
     * Creates a conversation or returns the existing canonical direct-message pair.
     */
    public function createConversation(Workspace $workspace, WorkspaceMember $actor, array $data): ChatConversation
    {
        return DB::transaction(function () use ($workspace, $actor, $data) {
            abort_if($actor->isExternal(), 403, 'External collaborators cannot create new conversations.');
            $type = $data['type'];
            $requestedIds = collect($data['member_ids'] ?? [])->map(fn ($value) => (int) $value)->unique()->values();

            if ($type === 'direct' && $requestedIds->contains($actor->id)) {
                throw new HttpResponseException(response()->json([
                    'message' => 'You cannot start a direct conversation with yourself.',
                    'code' => 'SELF_CONVERSATION_NOT_ALLOWED',
                    'errors' => ['member_ids' => ['Choose another active workspace member.']],
                ], 422));
            }

            $ids = $requestedIds->push($actor->id)->unique()->values();
            $valid = WorkspaceMember::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', 'active')
                ->whereHas('user', fn ($query) => $query->where('status', 'active'))
                ->whereIn('id', $ids)
                ->pluck('id');

            if ($valid->count() !== $ids->count()) {
                throw ValidationException::withMessages([
                    'member_ids' => ['Every participant must be an active member with an active user account in this workspace.'],
                ]);
            }

            if ($type === 'direct' && $valid->count() !== 2) {
                throw ValidationException::withMessages([
                    'member_ids' => ['Direct conversations require exactly one other active member.'],
                ]);
            }

            if ($type === 'group' && $valid->count() < 2) {
                throw ValidationException::withMessages([
                    'member_ids' => ['A group conversation needs at least one other active member.'],
                ]);
            }

            if ($type === 'project' && empty($data['project_id'])) {
                throw ValidationException::withMessages(['project_id' => ['Choose a project for a project thread.']]);
            }

            if ($type === 'task' && empty($data['task_id'])) {
                throw ValidationException::withMessages(['task_id' => ['Choose a task for a task thread.']]);
            }

            if (! empty($data['project_id'])) {
                $project = \App\Models\Project::where('workspace_id', $workspace->id)->find((int) $data['project_id']);
                abort_unless($project && $this->workScope->canViewProject($actor, $project), 403, 'Project is outside your visible scope.');
            }

            if (! empty($data['task_id'])) {
                $task = \App\Models\Task::where('workspace_id', $workspace->id)->find((int) $data['task_id']);
                abort_unless($task && $this->workScope->canViewTask($actor, $task), 403, 'Task is outside your visible scope.');
            }

            $directKey = null;
            if ($type === 'direct') {
                $directKey = hash('sha256', $valid->sort()->implode(':'));
                $existing = ChatConversation::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('direct_key', $directKey)
                    ->first();

                if ($existing) {
                    if ($existing->archived_at) {
                        $existing->update(['archived_at' => null]);
                    }

                    return $existing->fresh(['members.user', 'project', 'task']);
                }
            }

            $conversation = ChatConversation::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'type' => $type,
                'visibility' => $type === 'channel' ? ($data['visibility'] ?? 'private') : 'private',
                'channel_mode' => $type === 'channel' ? ($data['channel_mode'] ?? 'standard') : 'standard',
                'posting_policy' => $type === 'channel' ? ($data['posting_policy'] ?? (($data['channel_mode'] ?? 'standard') === 'announcement' ? 'admins' : 'members')) : 'members',
                'is_locked' => false,
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'direct_key' => $directKey,
                'project_id' => $data['project_id'] ?? null,
                'task_id' => $data['task_id'] ?? null,
                'created_by_member_id' => $actor->id,
            ]);

            $conversation->members()->attach($valid->mapWithKeys(fn ($id) => [
                $id => [
                    'role' => $id === $actor->id ? 'owner' : 'member',
                    'joined_at' => now(),
                ],
            ])->all());

            $conversation->load(['members.user', 'project', 'task']);
            return $conversation;
        });
    }

    /**
     * Returns active conversation candidates and actor-scoped project/task/document options.
     */
    public function creationOptions(Workspace $workspace, WorkspaceMember $member): array
    {
        $peopleQuery = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('id', '!=', $member->id)
            ->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->where('status', 'active'));
        if ($member->isExternal()) {
            $conversationIds = DB::table('chat_conversation_members')->where('member_id', $member->id)->pluck('conversation_id');
            $visibleIds = DB::table('chat_conversation_members')->whereIn('conversation_id', $conversationIds)->pluck('member_id')->unique();
            $peopleQuery->whereIn('id', $visibleIds);
        }
        $people = $peopleQuery
            ->with('user:id,first_name,last_name,email,status')
            ->orderBy('id')
            ->get()
            ->map(fn (WorkspaceMember $candidate) => [
                'id' => $candidate->id,
                'name' => trim(($candidate->user?->first_name ?? '').' '.($candidate->user?->last_name ?? '')),
                'email' => $candidate->user?->email,
                'job_title' => $candidate->job_title,
                'collaboration_type' => $candidate->collaboration_type ?? 'internal',
                'external_company' => $candidate->external_company,
                'external_expires_at' => $candidate->external_expires_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $projects = $member->isExternal() ? [] : $this->workScope
            ->scopeProjects(\App\Models\Project::query()->where('workspace_id', $workspace->id), $member)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($project) => ['id' => $project->id, 'name' => $project->name])
            ->all();

        $tasks = $member->isExternal() ? [] : $this->workScope
            ->scopeTasks(\App\Models\Task::query()->where('workspace_id', $workspace->id), $member)
            ->orderByDesc('updated_at')
            ->limit(250)
            ->get(['id', 'title', 'project_id'])
            ->map(fn ($task) => ['id' => $task->id, 'title' => $task->title, 'project_id' => $task->project_id])
            ->all();

        $documents = $member->isExternal() || ! $member->hasPermission('documents.view') ? [] : GeneratedDocument::query()
            ->where('workspace_id', $workspace->id)
            ->latest('generated_at')
            ->limit(100)
            ->get(['id', 'filename', 'document_type', 'source_type', 'source_id', 'generated_at'])
            ->map(fn (GeneratedDocument $document) => [
                'id' => $document->id,
                'filename' => $document->filename,
                'document_type' => $document->document_type,
                'source_type' => $document->source_type,
                'source_id' => $document->source_id,
                'generated_at' => $document->generated_at?->toIso8601String(),
            ])->all();

        return [
            'current_member_id' => $member->id,
            'is_external' => $member->isExternal(),
            'people' => $people,
            'projects' => $projects,
            'tasks' => $tasks,
            'documents' => $documents,
        ];
    }

    /**
     * Rejects access when the member does not belong to the supplied conversation.
     */
    public function assertMember(ChatConversation $conversation, WorkspaceMember $member): void
    {
        $pivot = DB::table('chat_conversation_members')->where(['conversation_id' => $conversation->id, 'member_id' => $member->id])->first();
        abort_unless($conversation->workspace_id === $member->workspace_id && $pivot, 403, 'You are not a member of this conversation.');
        abort_if($member->externalExpired(), 403, 'Your external collaboration access has expired.');
        abort_if($pivot?->guest_expires_at && now()->gte($pivot->guest_expires_at), 403, 'Your access to this conversation has expired.');
    }

    /**
     * Returns a cursor-paginated message page with explicit older/newer cursor metadata.
     */
    public function messagePage(ChatConversation $conversation, WorkspaceMember $member, ?int $before = null, ?int $after = null, ?int $around = null, int $limit = 60): array
    {
        $this->assertMember($conversation, $member);
        if (count(array_filter([$before, $after, $around])) > 1) {
            throw ValidationException::withMessages(['cursor' => ['Use only one of before, after or around.']]);
        }

        $limit = max(1, min((int) config('workintel.chat.page_size_max', 100), $limit));
        $query = $conversation->messages()
            ->whereNull('parent_id')
            ->with(['senderBot', 'sender.user:id,first_name,last_name,email', 'attachments', 'reactions.member.user:id,first_name,last_name', 'parent.sender.user:id,first_name,last_name', 'forwardedFrom.sender.user:id,first_name,last_name', 'forwardedFrom.attachments', 'poll.options.votes', 'poll.votes'])
            ->withCount(['replies as thread_reply_count' => fn ($replyQuery) => $replyQuery->whereNull('deleted_at')]);

        if ($around) {
            $olderLimit = max(1, intdiv($limit, 2));
            $newerLimit = max(1, $limit - $olderLimit);
            $older = (clone $query)->where('id', '<=', $around)->latest('id')->limit($olderLimit + 1)->get();
            $hasMore = $older->count() > $olderLimit;
            $older = $older->take($olderLimit)->reverse()->values();
            $newer = (clone $query)->where('id', '>', $around)->oldest('id')->limit($newerLimit)->get()->values();
            $rows = $older->concat($newer)->values();
        } elseif ($after) {
            $rows = $query->where('id', '>', $after)->oldest('id')->limit($limit + 1)->get();
            $hasMore = $rows->count() > $limit;
            $rows = $rows->take($limit)->values();
        } else {
            if ($before) $query->where('id', '<', $before);
            $rows = $query->latest('id')->limit($limit + 1)->get();
            $hasMore = $rows->count() > $limit;
            $rows = $rows->take($limit)->reverse()->values();
        }

        $newestId = (int) ($rows->last()?->id ?? 0);
        if ($newestId > 0) $this->markDelivered($conversation, $member, $newestId);
        $payloadState = $this->messagePayloadState($conversation, $member, $rows);

        return [
            'items' => $rows->map(fn ($message) => $this->messagePayload($message, $member, $payloadState))->all(),
            'meta' => [
                'before' => $before,
                'after' => $after,
                'around' => $around,
                'has_more' => $hasMore,
                'next_before' => $rows->isNotEmpty() && ($before || $around || (! $after && ! $around)) && $hasMore ? (int) $rows->first()->id : null,
                'next_after' => $rows->isNotEmpty() && $after && $hasMore ? (int) $rows->last()->id : null,
                'oldest_id' => $rows->isNotEmpty() ? (int) $rows->first()->id : null,
                'newest_id' => $rows->isNotEmpty() ? (int) $rows->last()->id : null,
                'limit' => $limit,
            ],
        ];
    }

    /**
     * Preserves the legacy service contract by returning only one latest/older message slice.
     */
    public function messages(ChatConversation $conversation, WorkspaceMember $member, ?int $before = null, int $limit = 60): array
    {
        return $this->messagePage($conversation, $member, $before, null, null, $limit)['items'];
    }

    /**
     * Advances a delivery cursor without marking messages as read.
     */
    public function markDelivered(ChatConversation $conversation, WorkspaceMember $member, int $messageId): void
    {
        $this->assertMember($conversation, $member);
        if (! Schema::hasColumn('chat_conversation_members', 'last_delivered_message_id')) return;
        $pivot = DB::table('chat_conversation_members')->where(['conversation_id' => $conversation->id, 'member_id' => $member->id]);
        $current = (int) ($pivot->value('last_delivered_message_id') ?? 0);
        if ($messageId > $current) $pivot->update(['last_delivered_message_id' => $messageId]);
    }

    /**
     * Persists a message, secure attachments and mention notifications.
     */
    public function send(ChatConversation $conversation, WorkspaceMember $member, ?string $body, ?int $parentId, array $files = [], ?string $clientMessageId = null, ?string $clientSentAt = null): ChatMessage
    {
        $this->assertMember($conversation, $member);
        $body = trim((string) $body);
        $clientMessageId = $clientMessageId && Schema::hasColumn('chat_messages', 'client_message_id') ? trim($clientMessageId) : null;

        if ($clientMessageId) {
            $existing = ChatMessage::query()
                ->where('conversation_id', $conversation->id)
                ->where('sender_member_id', $member->id)
                ->where('client_message_id', $clientMessageId)
                ->first();
            if ($existing) {
                return $existing->load(['senderBot', 'sender.user', 'attachments', 'reactions.member.user', 'parent.sender.user', 'forwardedFrom.sender.user', 'forwardedFrom.attachments', 'poll.options.votes', 'poll.votes']);
            }
        }

        if ($body === '' && ! $files) {
            throw ValidationException::withMessages(['body' => ['Write a message or attach a file.']]);
        }

        if (mb_strlen($body) > 10000) {
            throw ValidationException::withMessages(['body' => ['Messages are limited to 10,000 characters.']]);
        }

        $attachmentCountMax = max(1, (int) config('workintel.chat.attachment_count_max', 8));
        if (count($files) > $attachmentCountMax) {
            throw ValidationException::withMessages(['attachments' => ["A message can include at most {$attachmentCountMax} attachments."]]);
        }
        $attachmentTotalMax = max(1, (int) config('workintel.chat.attachment_total_mb', 60)) * 1024 * 1024;
        $attachmentTotal = array_sum(array_map(fn ($file) => $file instanceof UploadedFile ? (int) $file->getSize() : 0, $files));
        if ($attachmentTotal > $attachmentTotalMax) {
            throw ValidationException::withMessages(['attachments' => ['Combined attachment size exceeds the workspace safety limit.']]);
        }
        $dlpResult = $this->dlp->preflight($conversation, $member, $body, $files);
        $blockedExtensions = array_map('strtolower', (array) config('workintel.chat.blocked_extensions', []));
        foreach ($files as $file) {
            if ($file instanceof UploadedFile && in_array(strtolower((string) $file->getClientOriginalExtension()), $blockedExtensions, true) && ($dlpResult['action'] ?? 'clean') !== 'quarantine') {
                throw ValidationException::withMessages(['attachments' => ['Executable or high-risk attachment types are not allowed in chat unless an enterprise DLP policy explicitly quarantines them.']]);
            }
        }

        $parent = $parentId ? ChatMessage::where('conversation_id', $conversation->id)->findOrFail($parentId) : null;
        if ($parent?->parent_id) {
            $parent = ChatMessage::where('conversation_id', $conversation->id)->findOrFail($parent->parent_id);
        }

        try {
            return DB::transaction(function () use ($conversation, $member, $body, $parent, $files, $dlpResult, $clientMessageId, $clientSentAt) {
            if ($clientMessageId) {
                $existing = ChatMessage::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('sender_member_id', $member->id)
                    ->where('client_message_id', $clientMessageId)
                    ->lockForUpdate()
                    ->first();
                if ($existing) return $existing->load(['senderBot', 'sender.user', 'attachments', 'reactions.member.user', 'parent.sender.user', 'forwardedFrom.sender.user', 'forwardedFrom.attachments', 'poll.options.votes', 'poll.votes']);
            }

            $mentions = $this->mentions($body, $conversation);
            $attributes = [
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $conversation->workspace_id,
                'conversation_id' => $conversation->id,
                'sender_member_id' => $member->id,
                'parent_id' => $parent?->id,
                'body' => $body ?: null,
                'mentions' => $mentions,
            ];
            if ($clientMessageId !== null) {
                $attributes['client_message_id'] = $clientMessageId;
                $attributes['client_sent_at'] = $clientSentAt ? CarbonImmutable::parse($clientSentAt) : null;
            }
            $message = ChatMessage::create($attributes);

            foreach (array_slice($files, 0, 8) as $file) {
                $attachment = $this->storeAttachment($message, $file, $dlpResult);
                $this->dlp->recordResult($conversation, $member, $message, $attachment, $dlpResult);
            }

            $this->dlp->recordResult($conversation, $member, $message, null, $dlpResult);

            if ($parent) {
                $this->followThread($parent, $member, true);
                if ($parent->sender_member_id && $parent->sender_member_id !== $member->id) {
                    $rootSender = WorkspaceMember::where('workspace_id', $conversation->workspace_id)->where('status', 'active')->find($parent->sender_member_id);
                    if ($rootSender && $conversation->members()->where('workspace_members.id', $rootSender->id)->exists()) {
                        $this->followThread($parent, $rootSender, true);
                    }
                }
                $this->notifyThreadFollowers($parent, $member, $message);
            }

            ChatDraft::where(['conversation_id' => $conversation->id, 'member_id' => $member->id])->delete();
            $conversation->touch();
            $this->notifyMentions($conversation, $member, $message, $mentions);
            if (! $parent) {
                $this->notifyConversationMembers($conversation, $member, $message, $mentions);
            }
            $message->load(['senderBot', 'sender.user', 'attachments', 'reactions.member.user', 'parent.sender.user']);

            try {
                broadcast(new ChatMessageChanged($message, 'created'))->toOthers();
            } catch (\Throwable $exception) {
                report($exception);
            }

            return $message;
            });
        } catch (QueryException $exception) {
            if ($clientMessageId) {
                $existing = ChatMessage::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('sender_member_id', $member->id)
                    ->where('client_message_id', $clientMessageId)
                    ->first();
                if ($existing) return $existing->load(['senderBot', 'sender.user', 'attachments', 'reactions.member.user', 'parent.sender.user', 'forwardedFrom.sender.user', 'forwardedFrom.attachments', 'poll.options.votes', 'poll.votes']);
            }
            throw $exception;
        }
    }

    /**
     * Edits a message owned by the requesting member.
     */
    public function edit(ChatMessage $message, WorkspaceMember $member, string $body): ChatMessage
    {
        abort_unless($message->sender_member_id === $member->id, 403);
        abort_if($message->deleted_at, 422, 'Deleted messages cannot be edited.');
        $this->assertMember($message->conversation, $member);
        $body = trim($body);
        abort_if($body === '', 422, 'Message cannot be empty.');
        abort_if($body === (string) $message->body, 422, 'No message changes were detected.');
        ChatMessageEditHistory::create([
            'message_id' => $message->id,
            'edited_by_member_id' => $member->id,
            'body' => $message->body,
            'mentions' => $message->mentions ?? [],
            'edited_at' => now(),
        ]);
        $message->update(['body' => $body, 'mentions' => $this->mentions($body, $message->conversation), 'edited_at' => now()]);
        $message->load(['senderBot', 'sender.user', 'attachments', 'reactions.member.user', 'parent.sender.user']);

        try {
            broadcast(new ChatMessageChanged($message, 'updated'))->toOthers();
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $message;
    }

    /** Returns true when workspace permission or the conversation role grants moderation authority. */
    public function canModerateConversation(ChatConversation $conversation, WorkspaceMember $member): bool
    {
        if ($member->hasPermission('chat.manage') || $member->hasPermission('chat.moderate')) return true;
        $role = DB::table('chat_conversation_members')->where(['conversation_id' => $conversation->id, 'member_id' => $member->id])->value('role');
        return in_array($role, ['owner', 'admin', 'moderator'], true);
    }

    /**
     * Soft-deletes a message when the actor owns it or has moderation rights.
     */
    public function delete(ChatMessage $message, WorkspaceMember $member, bool $moderate = false): void
    {
        $this->assertMember($message->conversation, $member);
        abort_unless($message->sender_member_id === $member->id || $moderate || $this->canModerateConversation($message->conversation, $member), 403);
        abort_if($message->deleted_at, 422, 'Message is already deleted.');
        ChatMessageEditHistory::create([
            'message_id' => $message->id,
            'edited_by_member_id' => $member->id,
            'body' => $message->body,
            'mentions' => $message->mentions ?? [],
            'edited_at' => now(),
        ]);
        $message->update(['body' => null, 'deleted_at' => now(), 'mentions' => []]);

        try {
            broadcast(new ChatMessageChanged($message, 'deleted'))->toOthers();
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Toggles a reaction owned by the current member.
     */
    public function react(ChatMessage $message, WorkspaceMember $member, string $emoji): bool
    {
        $this->assertMember($message->conversation, $member);
        $existing = ChatMessageReaction::where(['message_id' => $message->id, 'member_id' => $member->id, 'emoji' => $emoji])->first();

        if ($existing) {
            $existing->delete();
            $active = false;
        } else {
            ChatMessageReaction::create(['message_id' => $message->id, 'member_id' => $member->id, 'emoji' => $emoji, 'created_at' => now()]);
            $active = true;
        }

        try {
            broadcast(new ChatMessageChanged($message, 'reaction'))->toOthers();
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $active;
    }

    /**
     * Toggles the pinned state of a message inside its conversation.
     */
    public function pin(ChatMessage $message, WorkspaceMember $member): bool
    {
        $this->assertMember($message->conversation, $member);
        $existing = ChatMessagePin::where(['conversation_id' => $message->conversation_id, 'message_id' => $message->id])->first();

        if ($existing) {
            $existing->delete();
            return false;
        }

        ChatMessagePin::create([
            'conversation_id' => $message->conversation_id,
            'message_id' => $message->id,
            'pinned_by_member_id' => $member->id,
            'created_at' => now(),
        ]);

        return true;
    }

    /**
     * Toggles per-member mute state for a conversation.
     */
    public function mute(ChatConversation $conversation, WorkspaceMember $member): bool
    {
        $this->assertMember($conversation, $member);
        $pivot = DB::table('chat_conversation_members')->where(['conversation_id' => $conversation->id, 'member_id' => $member->id]);
        $muted = ! (bool) $pivot->value('is_muted');
        $pivot->update([
            'is_muted' => $muted,
            'notification_mode' => $muted ? 'nothing' : 'all',
            'notifications_snoozed_until' => null,
        ]);
        return $muted;
    }

    /**
     * Advances the member's read cursor to a message in the conversation.
     */
    public function markRead(ChatConversation $conversation, WorkspaceMember $member, ?int $messageId = null): void
    {
        $this->assertMember($conversation, $member);
        $messageId = $messageId ?: (int) $conversation->messages()->max('id');
        $pivot = DB::table('chat_conversation_members')->where(['conversation_id' => $conversation->id, 'member_id' => $member->id]);
        $current = (int) ($pivot->value('last_read_message_id') ?? 0);
        if ($messageId > $current) $pivot->update(['last_read_message_id' => $messageId]);
    }

    /**
     * Refreshes presence and optional typing state for the current member.
     */
    public function presence(Workspace $workspace, WorkspaceMember $member, ?ChatConversation $conversation, bool $typing): void
    {
        if ($conversation) {
            $this->assertMember($conversation, $member);
        }

        ChatPresence::updateOrCreate(
            ['member_id' => $member->id],
            ['workspace_id' => $workspace->id, 'conversation_id' => $conversation?->id, 'is_typing' => $typing, 'last_seen_at' => now()],
        );

        if ($conversation) {
            try {
                broadcast(new ChatTypingChanged($workspace->id, $conversation->id, $member->id, $typing))->toOthers();
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    /** Returns the member's collaboration inbox across unread mentions, followed threads and direct conversations. */
    public function collaborationInbox(Workspace $workspace, WorkspaceMember $member): array
    {
        $conversations = collect($this->conversations($workspace, $member));
        $conversationIds = $conversations->pluck('id')->map(fn ($id) => (int) $id)->values();
        if ($conversationIds->isEmpty()) return ['counts'=>['mentions'=>0,'threads'=>0,'direct'=>0,'total'=>0],'mentions'=>[],'threads'=>[],'direct'=>[]];

        $readCursors = DB::table('chat_conversation_members')
            ->where('member_id', $member->id)
            ->whereIn('conversation_id', $conversationIds)
            ->pluck('last_read_message_id', 'conversation_id');

        $mentionCandidates = ChatMessage::query()
            ->whereIn('conversation_id', $conversationIds)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($member) { $query->whereNull('sender_member_id')->orWhere('sender_member_id', '!=', $member->id); })
            ->whereNotNull('mentions')
            ->latest('id')
            ->limit(500)
            ->with(['conversation:id,uuid,type,name', 'senderBot', 'sender.user:id,first_name,last_name,email', 'attachments', 'reactions', 'parent.sender.user:id,first_name,last_name', 'forwardedFrom.sender.user:id,first_name,last_name', 'poll.options.votes', 'poll.votes'])
            ->withCount(['replies as thread_reply_count' => fn ($query) => $query->whereNull('deleted_at')])
            ->get()
            ->filter(function (ChatMessage $message) use ($member, $readCursors) {
                $mentions = array_map('intval', (array) ($message->mentions ?? []));
                return in_array($member->id, $mentions, true) && $message->id > (int) ($readCursors[$message->conversation_id] ?? 0);
            })
            ->take(40)
            ->values();

        $follows = ChatThreadFollow::query()
            ->where('workspace_id', $workspace->id)
            ->where('member_id', $member->id)
            ->where('is_following', true)
            ->whereHas('rootMessage.conversation.members', fn ($query) => $query->where('workspace_members.id', $member->id))
            ->with(['rootMessage.conversation:id,uuid,type,name', 'rootMessage.sender.user:id,first_name,last_name'])
            ->latest('updated_at')
            ->limit(100)
            ->get();
        $rootIds = $follows->pluck('root_message_id');
        $replyRows = $rootIds->isEmpty() ? collect() : ChatMessage::query()
            ->whereIn('parent_id', $rootIds)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($member) { $query->whereNull('sender_member_id')->orWhere('sender_member_id', '!=', $member->id); })
            ->with(['conversation:id,uuid,type,name', 'senderBot', 'sender.user:id,first_name,last_name,email', 'attachments', 'reactions', 'parent.sender.user:id,first_name,last_name', 'forwardedFrom.sender.user:id,first_name,last_name', 'poll.options.votes', 'poll.votes'])
            ->withCount(['replies as thread_reply_count' => fn ($query) => $query->whereNull('deleted_at')])
            ->orderByDesc('id')
            ->get()
            ->groupBy('parent_id');

        $threads = $follows->map(function (ChatThreadFollow $follow) use ($replyRows, $member) {
            $replies = $replyRows->get($follow->root_message_id, collect());
            $unread = $replies->filter(fn (ChatMessage $reply) => $reply->id > (int) ($follow->last_read_reply_id ?? 0));
            $latest = $unread->first();
            if (! $latest) return null;
            return [
                'root_message_id' => $follow->root_message_id,
                'conversation_id' => $follow->rootMessage?->conversation_id,
                'conversation_name' => $follow->rootMessage?->conversation?->name ?: ucfirst((string) ($follow->rootMessage?->conversation?->type ?? 'conversation')),
                'root_body' => Str::limit((string) ($follow->rootMessage?->body ?? ''), 180),
                'unread_count' => $unread->count(),
                'latest_reply' => $this->messagePayload($latest, $member),
            ];
        })->filter()->take(40)->values()->all();

        $direct = $conversations->filter(fn ($conversation) => ($conversation['type'] ?? null) === 'direct' && (int) ($conversation['unread_count'] ?? 0) > 0)->take(40)->map(fn($conversation)=>$conversation+['activity_key'=>'direct:'.$conversation['id']])->values()->all();
        $mentionPayloads = $mentionCandidates->map(fn (ChatMessage $message) => $this->messagePayload($message, $member)+['activity_key'=>'mention:'.$message->id])->all();
        $threads = array_map(fn($thread)=>$thread+['activity_key'=>'thread:'.$thread['root_message_id']], $threads);
        [$mentionPayloads,$threads,$direct]=$this->applyActivityStates($member,$mentionPayloads,$threads,$direct);
        $counts = ['mentions'=>count($mentionPayloads),'threads'=>count($threads),'direct'=>count($direct)];
        $counts['total'] = array_sum($counts);
        return ['counts'=>$counts,'mentions'=>$mentionPayloads,'threads'=>$threads,'direct'=>$direct];
    }

    /** Returns pinned messages, member bookmarks and recent files for the active conversation context panel. */
    public function conversationContext(ChatConversation $conversation, WorkspaceMember $member, int $limit=20, ?int $pinBefore=null, ?int $bookmarkBefore=null, ?int $fileBefore=null): array
    {
        $this->assertMember($conversation, $member);
        $limit=max(5,min(50,$limit));
        $pinQuery=ChatMessagePin::query()->where('conversation_id',$conversation->id);if($pinBefore!==null)$pinQuery->where('message_id','<',$pinBefore);$pinIds=$pinQuery->orderByDesc('message_id')->limit($limit)->pluck('message_id');
        $savedRows = ChatSavedMessage::query()->where('workspace_id', $member->workspace_id)->where('member_id', $member->id)
            ->whereHas('message', fn ($query) => $query->where('conversation_id', $conversation->id));if($bookmarkBefore!==null)$savedRows->where('message_id','<',$bookmarkBefore);$savedRows=$savedRows->orderByDesc('message_id')->limit($limit)->get();
        $messageIds = $pinIds->merge($savedRows->pluck('message_id'))->unique()->values();
        $messages = $messageIds->isEmpty() ? collect() : ChatMessage::query()->whereIn('id', $messageIds)
            ->with(['senderBot', 'sender.user:id,first_name,last_name,email', 'attachments', 'reactions', 'parent.sender.user:id,first_name,last_name', 'forwardedFrom.sender.user:id,first_name,last_name', 'poll.options.votes', 'poll.votes'])
            ->withCount(['replies as thread_reply_count' => fn ($query) => $query->whereNull('deleted_at')])->get()->keyBy('id');
        $pinned = $pinIds->map(fn ($id) => $messages->has($id) ? $this->messagePayload($messages[$id], $member) : null)->filter()->values()->all();
        $bookmarks = $savedRows->map(fn (ChatSavedMessage $saved) => $messages->has($saved->message_id) ? [
            'id'=>$saved->id,'note'=>$saved->note,'updated_at'=>$saved->updated_at?->toIso8601String(),'message'=>$this->messagePayload($messages[$saved->message_id], $member),
        ] : null)->filter()->values()->all();

        $fileQuery=ChatMessageAttachment::query()->whereHas('message', fn ($query) => $query->where('conversation_id', $conversation->id));if($fileBefore!==null)$fileQuery->where('id','<',$fileBefore);
        $files = $fileQuery->with(['message.sender.user:id,first_name,last_name'])
            ->latest('id')->limit($limit)->get()->map(fn (ChatMessageAttachment $attachment) => [
                'id'=>$attachment->id,'message_id'=>$attachment->message_id,'filename'=>$attachment->filename,'mime_type'=>$attachment->mime_type,'size_bytes'=>(int)$attachment->size_bytes,
                'security_status'=>$attachment->security_status ?? 'clear','security_reason'=>$attachment->security_reason,'url'=>'/api/v1/chat/attachments/'.$attachment->id,
                'sender'=>$attachment->message?->sender ? trim(($attachment->message->sender->user?->first_name ?? '').' '.($attachment->message->sender->user?->last_name ?? '')) : null,
                'created_at'=>$attachment->created_at ? CarbonImmutable::parse((string) $attachment->created_at)->toIso8601String() : null,
            ])->all();
        return ['pinned'=>$pinned,'bookmarks'=>$bookmarks,'files'=>$files,'meta'=>['pin_next'=>count($pinned)===$limit?(int)($pinned[array_key_last($pinned)]['id']??0):null,'bookmark_next'=>count($bookmarks)===$limit?(int)($bookmarks[array_key_last($bookmarks)]['message']['id']??0):null,'file_next'=>count($files)===$limit?(int)($files[array_key_last($files)]['id']??0):null]];
    }

    /** Updates the private note attached to an existing saved-message bookmark. */
    public function updateSavedNote(ChatMessage $message, WorkspaceMember $member, ?string $note): array
    {
        $this->assertMember($message->conversation, $member);
        $saved = ChatSavedMessage::query()->where(['workspace_id'=>$member->workspace_id,'member_id'=>$member->id,'message_id'=>$message->id])->first();
        abort_unless($saved, 404, 'Save this message before adding a bookmark note.');
        $saved->update(['note'=>trim((string)$note) ?: null]);
        return ['id'=>$saved->id,'message_id'=>$message->id,'note'=>$saved->note,'updated_at'=>$saved->updated_at?->toIso8601String()];
    }

    /** Returns the dedicated chat notification matrix using the shared notification-preference store. */
    public function chatNotificationPreferences(Workspace $workspace, WorkspaceMember $member): array
    {
        $categories=['chat_mentions','chat_threads','chat_direct','chat_channels'];
        return collect($categories)->map(function(string $category)use($workspace,$member){$row=NotificationPreference::firstOrCreate(['workspace_id'=>$workspace->id,'user_id'=>$member->user_id,'category'=>$category],['in_app'=>true,'email'=>false,'digest'=>'immediate']);return ['category'=>$category,'in_app'=>(bool)$row->in_app,'email'=>(bool)$row->email,'digest'=>$row->digest];})->values()->all();
    }

    /** Updates only the allowlisted chat-notification preference matrix for the current workspace member. */
    public function updateChatNotificationPreferences(Workspace $workspace, WorkspaceMember $member, array $preferences): array
    {
        $allowed=['chat_mentions','chat_threads','chat_direct','chat_channels'];
        foreach($preferences as $preference){abort_unless(in_array($preference['category']??'', $allowed, true),422,'Unsupported chat notification category.');NotificationPreference::updateOrCreate(['workspace_id'=>$workspace->id,'user_id'=>$member->user_id,'category'=>$preference['category']],['in_app'=>(bool)$preference['in_app'],'email'=>(bool)$preference['email'],'digest'=>$preference['digest']]);}
        return $this->chatNotificationPreferences($workspace,$member);
    }

    /** Persists one collaboration-inbox triage action without mutating shared message history. */
    public function triageInbox(Workspace $workspace, WorkspaceMember $member, string $action, ?string $activityKey=null, ?string $until=null): array
    {
        $allowed=['done','reopen','snooze','follow_up','read_all'];abort_unless(in_array($action,$allowed,true),422,'Unsupported inbox action.');
        if($action==='read_all'){$inbox=$this->collaborationInbox($workspace,$member);$keys=array_merge(array_column($inbox['mentions'],'activity_key'),array_column($inbox['threads'],'activity_key'),array_column($inbox['direct'],'activity_key'));foreach($keys as $key)$this->writeActivityState($member,$key,'done',null,null);return $this->collaborationInbox($workspace,$member);}
        abort_unless($activityKey&&preg_match('/^(mention|thread|direct):\\d+$/',$activityKey),422,'Invalid activity key.');
        $date=$until?CarbonImmutable::parse($until):null;if(in_array($action,['snooze','follow_up'],true))abort_unless($date&&$date->isFuture(),422,'Choose a future date.');
        $this->writeActivityState($member,$activityKey,$action==='reopen'?'open':$action,$action==='snooze'?$date:null,$action==='follow_up'?$date:null);
        return $this->collaborationInbox($workspace,$member);
    }

    /** Removes visible pins or private bookmarks in one bounded collaboration-context operation. */
    public function bulkContext(ChatConversation $conversation, WorkspaceMember $member, string $action, array $ids): array
    {
        $this->assertMember($conversation,$member);$ids=collect($ids)->map(fn($id)=>(int)$id)->filter()->unique()->take(100)->values();abort_if($ids->isEmpty(),422,'Choose at least one item.');
        if($action==='unpin'){abort_unless($this->canModerateConversation($conversation,$member),403);ChatMessagePin::query()->where('conversation_id',$conversation->id)->whereIn('message_id',$ids)->delete();}
        elseif($action==='delete_bookmarks'){ChatSavedMessage::query()->where('workspace_id',$member->workspace_id)->where('member_id',$member->id)->whereIn('id',$ids)->whereHas('message',fn($q)=>$q->where('conversation_id',$conversation->id))->delete();}
        else abort(422,'Unsupported context bulk action.');
        return $this->conversationContext($conversation,$member);
    }

    /** Applies private done/snooze/follow-up state to derived collaboration inbox rows. */
    private function applyActivityStates(WorkspaceMember $member, array $mentions, array $threads, array $direct): array
    {
        $states=ChatActivityState::query()->where('workspace_id',$member->workspace_id)->where('member_id',$member->id)->get()->keyBy('activity_key');
        $filter=function(array $items)use($states){return array_values(array_filter(array_map(function($item)use($states){$state=$states->get($item['activity_key']??'');if(!$state)return $item;if($state->status==='done')return null;if($state->status==='snooze'&&$state->snoozed_until?->isFuture())return null;return $item+['triage'=>['status'=>$state->status,'snoozed_until'=>$state->snoozed_until?->toIso8601String(),'follow_up_at'=>$state->follow_up_at?->toIso8601String()]];},$items)));};
        return [$filter($mentions),$filter($threads),$filter($direct)];
    }

    /** Upserts one private collaboration-activity state using the type encoded in its stable activity key. */
    private function writeActivityState(WorkspaceMember $member,string $key,string $status,?CarbonImmutable $snooze,?CarbonImmutable $followUp): void
    {
        [$type]=explode(':',$key,2);ChatActivityState::updateOrCreate(['workspace_id'=>$member->workspace_id,'member_id'=>$member->id,'activity_type'=>$type,'activity_key'=>$key],['status'=>$status,'snoozed_until'=>$snooze,'follow_up_at'=>$followUp]);
    }

    /**
     * Returns immutable edit history when the viewer owns the message or moderates chat.
     */
    public function editHistory(ChatMessage $message, WorkspaceMember $member): array
    {
        $this->assertMember($message->conversation, $member);
        abort_unless($message->sender_member_id === $member->id || $this->canModerateConversation($message->conversation, $member), 403);
        return $message->editHistory()->with('editor.user:id,first_name,last_name')->get()->map(fn ($row) => [
            'id' => $row->id,
            'body' => $row->body,
            'edited_at' => $row->edited_at?->toIso8601String(),
            'editor' => $row->editor ? trim(($row->editor->user?->first_name ?? '').' '.($row->editor->user?->last_name ?? '')) : null,
        ])->all();
    }

    /**
     * Toggles a private saved-message bookmark for the current member.
     */
    public function toggleSaved(ChatMessage $message, WorkspaceMember $member): bool
    {
        $this->assertMember($message->conversation, $member);
        $existing = ChatSavedMessage::where(['member_id' => $member->id, 'message_id' => $message->id])->first();
        if ($existing) { $existing->delete(); return false; }
        ChatSavedMessage::create(['workspace_id' => $member->workspace_id, 'member_id' => $member->id, 'message_id' => $message->id]);
        return true;
    }

    /**
     * Returns private saved messages that remain visible to the member.
     */
    public function savedMessages(WorkspaceMember $member): array
    {
        $messageIds = ChatSavedMessage::where('workspace_id', $member->workspace_id)->where('member_id', $member->id)->latest('id')->pluck('message_id');
        if ($messageIds->isEmpty()) return [];
        $messages = ChatMessage::query()->whereIn('id', $messageIds)->whereHas('conversation.members', fn ($query) => $query->where('workspace_members.id', $member->id))
            ->with(['conversation:id,uuid,type,name', 'senderBot', 'sender.user:id,first_name,last_name,email', 'attachments', 'reactions', 'parent.sender.user:id,first_name,last_name', 'forwardedFrom.sender.user:id,first_name,last_name', 'poll.options.votes', 'poll.votes'])
            ->withCount(['replies as thread_reply_count' => fn ($replyQuery) => $replyQuery->whereNull('deleted_at')])->get()->keyBy('id');
        return $messageIds->map(fn ($id) => $messages->has($id) ? $this->messagePayload($messages[$id], $member) : null)->filter()->values()->all();
    }

    /**
     * Returns the current member's saved text draft for a conversation.
     */
    public function draft(ChatConversation $conversation, WorkspaceMember $member): ?array
    {
        $this->assertMember($conversation, $member);
        $draft = ChatDraft::where(['conversation_id' => $conversation->id, 'member_id' => $member->id])->first();
        return $draft ? ['body' => $draft->body ?? '', 'parent_id' => $draft->parent_id, 'updated_at' => $draft->updated_at?->toIso8601String()] : null;
    }

    /**
     * Creates, updates or removes a cross-device conversation draft.
     */
    public function saveDraft(ChatConversation $conversation, WorkspaceMember $member, ?string $body, ?int $parentId): ?array
    {
        $this->assertMember($conversation, $member);
        $body = trim((string) $body);
        $parent = $parentId ? ChatMessage::where('conversation_id', $conversation->id)->findOrFail($parentId) : null;
        if ($parent?->parent_id) $parent = ChatMessage::where('conversation_id', $conversation->id)->findOrFail($parent->parent_id);
        if ($body === '' && ! $parent) {
            ChatDraft::where(['conversation_id' => $conversation->id, 'member_id' => $member->id])->delete();
            return null;
        }
        $draft = ChatDraft::updateOrCreate(
            ['conversation_id' => $conversation->id, 'member_id' => $member->id],
            ['workspace_id' => $member->workspace_id, 'body' => $body ?: null, 'parent_id' => $parent?->id],
        );
        return ['body' => $draft->body ?? '', 'parent_id' => $draft->parent_id, 'updated_at' => $draft->updated_at?->toIso8601String()];
    }

    /**
     * Removes the current member's saved draft for a conversation.
     */
    public function deleteDraft(ChatConversation $conversation, WorkspaceMember $member): void
    {
        $this->assertMember($conversation, $member);
        ChatDraft::where(['conversation_id' => $conversation->id, 'member_id' => $member->id])->delete();
    }

    /**
     * Returns a root message and its replies while tracking the viewer's thread read cursor.
     */
    public function thread(ChatMessage $message, WorkspaceMember $member): array
    {
        $root = $message->parent_id ? ChatMessage::findOrFail($message->parent_id) : $message;
        $this->assertMember($root->conversation, $member);
        $root->load(['senderBot', 'sender.user:id,first_name,last_name', 'attachments', 'reactions', 'poll.options.votes', 'poll.votes']);
        $replies = ChatMessage::query()->where('conversation_id', $root->conversation_id)->where('parent_id', $root->id)
            ->with(['senderBot', 'sender.user:id,first_name,last_name', 'attachments', 'reactions', 'forwardedFrom.sender.user:id,first_name,last_name', 'poll.options.votes', 'poll.votes'])
            ->orderBy('id')->get();
        $follow = ChatThreadFollow::where(['root_message_id' => $root->id, 'member_id' => $member->id])->first();
        $latestReplyId = (int) ($replies->max('id') ?? 0);
        if ($latestReplyId) {
            ChatThreadFollow::updateOrCreate(
                ['root_message_id' => $root->id, 'member_id' => $member->id],
                ['workspace_id' => $member->workspace_id, 'last_read_reply_id' => $latestReplyId, 'is_following' => $follow?->is_following ?? false],
            );
        }
        return [
            'root' => $this->messagePayload($root, $member),
            'replies' => $replies->map(fn ($reply) => $this->messagePayload($reply, $member))->all(),
            'following' => (bool) ($follow?->is_following ?? false),
        ];
    }

    /**
     * Enables or disables following for a message thread.
     */
    public function followThread(ChatMessage $message, WorkspaceMember $member, bool $following): bool
    {
        $root = $message->parent_id ? ChatMessage::findOrFail($message->parent_id) : $message;
        $this->assertMember($root->conversation, $member);
        ChatThreadFollow::updateOrCreate(
            ['root_message_id' => $root->id, 'member_id' => $member->id],
            ['workspace_id' => $member->workspace_id, 'is_following' => $following],
        );
        return $following;
    }

    /**
     * Forwards a visible message into another conversation without weakening either conversation's membership boundary.
     */
    public function forward(ChatMessage $message, WorkspaceMember $member, ChatConversation $target, ?string $note = null): ChatMessage
    {
        $this->assertMember($message->conversation, $member);
        $this->assertMember($target, $member);
        abort_if($message->deleted_at, 422, 'Deleted messages cannot be forwarded.');
        $note = trim((string) $note);
        abort_if(mb_strlen($note) > 2000, 422, 'Forward note is limited to 2,000 characters.');
        $forwarded = ChatMessage::create([
            'uuid' => (string) Str::uuid(), 'workspace_id' => $target->workspace_id, 'conversation_id' => $target->id,
            'sender_member_id' => $member->id, 'forwarded_from_message_id' => $message->id, 'body' => $note ?: null, 'mentions' => [],
        ]);
        $target->touch();
        $forwarded->load(['senderBot', 'sender.user', 'attachments', 'reactions', 'forwardedFrom.sender.user', 'forwardedFrom.attachments']);
        try { broadcast(new ChatMessageChanged($forwarded, 'created'))->toOthers(); } catch (\Throwable $exception) { report($exception); }
        return $forwarded;
    }

    /**
     * Creates a poll message with validated answer options.
     */
    public function createPoll(ChatConversation $conversation, WorkspaceMember $member, string $question, array $options, bool $multiple, ?string $closesAt): ChatMessage
    {
        $question = trim($question);
        $cleanOptions = collect($options)->map(fn ($option) => trim((string) $option))->filter()->unique()->values();
        if ($question === '') throw ValidationException::withMessages(['question' => ['Poll question is required.']]);
        if ($cleanOptions->count() < 2) throw ValidationException::withMessages(['options' => ['Provide at least two unique poll options.']]);
        $message = $this->send($conversation, $member, $question, null, []);
        $poll = ChatPoll::create(['message_id' => $message->id, 'allows_multiple' => $multiple, 'closes_at' => $closesAt ? CarbonImmutable::parse($closesAt) : null]);
        foreach ($cleanOptions->take(10) as $position => $label) ChatPollOption::create(['poll_id' => $poll->id, 'label' => $label, 'position' => $position, 'created_at' => now()]);
        return $message->fresh(['senderBot', 'sender.user', 'attachments', 'reactions', 'poll.options.votes', 'poll.votes']);
    }

    /**
     * Replaces the current member's poll vote selection after validating poll state and option ownership.
     */
    public function vote(ChatPoll $poll, WorkspaceMember $member, array $optionIds): array
    {
        $poll->load('message.conversation', 'options');
        $this->assertMember($poll->message->conversation, $member);
        abort_if($poll->closes_at?->isPast(), 422, 'This poll is closed.');
        $validIds = $poll->options->pluck('id');
        $selected = collect($optionIds)->map(fn ($id) => (int) $id)->unique()->filter(fn ($id) => $validIds->contains($id))->values();
        if (! $poll->allows_multiple && $selected->count() > 1) throw ValidationException::withMessages(['option_ids' => ['Choose only one option for this poll.']]);
        DB::transaction(function () use ($poll, $member, $selected) {
            ChatPollVote::where(['poll_id' => $poll->id, 'member_id' => $member->id])->delete();
            foreach ($selected as $optionId) ChatPollVote::create(['poll_id' => $poll->id, 'option_id' => $optionId, 'member_id' => $member->id, 'created_at' => now()]);
        });
        return $this->pollPayload($poll->fresh(['options.votes', 'votes']), $member);
    }

    /**
     * Shapes poll results while exposing only aggregate vote counts and the viewer's own selections.
     */
    private function pollPayload(ChatPoll $poll, WorkspaceMember $viewer): array
    {
        $votes = $poll->relationLoaded('votes') ? $poll->votes : $poll->votes()->get();
        return [
            'id' => $poll->id,
            'allows_multiple' => (bool) $poll->allows_multiple,
            'closes_at' => $poll->closes_at?->toIso8601String(),
            'closed' => (bool) $poll->closes_at?->isPast(),
            'total_voters' => $votes->pluck('member_id')->unique()->count(),
            'options' => $poll->options->map(fn ($option) => [
                'id' => $option->id, 'label' => $option->label, 'votes' => $option->votes->count(), 'mine' => $option->votes->contains('member_id', $viewer->id),
            ])->all(),
        ];
    }

    /**
     * Searches visible messages with professional query operators such as from:, in:, before:, after: and has:.
     */
    public function search(Workspace $workspace, WorkspaceMember $member, string $term): array
    {
        $parsed = $this->parseSearchQuery($workspace, $member, trim($term));
        $query = ChatMessage::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('deleted_at')
            ->whereHas('conversation.members', fn ($memberQuery) => $memberQuery->where('workspace_members.id', $member->id))
            ->with(['conversation:id,uuid,type,name', 'senderBot', 'sender.user:id,first_name,last_name,email', 'attachments', 'reactions', 'parent.sender.user:id,first_name,last_name', 'forwardedFrom.sender.user:id,first_name,last_name', 'poll.options.votes', 'poll.votes'])
            ->withCount(['replies as thread_reply_count' => fn ($replyQuery) => $replyQuery->whereNull('deleted_at')]);

        if ($parsed['text'] !== '') {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $parsed['text']);
            $query->where('body', 'like', '%'.$escaped.'%');
        }
        if ($parsed['from_member_id']) $query->where('sender_member_id', $parsed['from_member_id']);
        if ($parsed['conversation_id']) $query->where('conversation_id', $parsed['conversation_id']);
        if ($parsed['before']) $query->where('created_at', '<', $parsed['before']);
        if ($parsed['after']) $query->where('created_at', '>=', $parsed['after']);
        if ($parsed['has_file']) $query->whereHas('attachments');
        if ($parsed['has_link']) $query->where(fn ($linkQuery) => $linkQuery->where('body', 'like', '%https://%')->orWhere('body', 'like', '%http://%'));

        return $query->latest('id')->limit(75)->get()->map(fn ($message) => $this->messagePayload($message, $member))->all();
    }

    /**
     * Parses supported search operators without allowing arbitrary SQL fragments.
     */
    private function parseSearchQuery(Workspace $workspace, WorkspaceMember $member, string $term): array
    {
        $filters = ['from_member_id' => null, 'conversation_id' => null, 'before' => null, 'after' => null, 'has_file' => false, 'has_link' => false];
        $text = preg_replace_callback('/(?:^|\s)(from|in|before|after|has):(?:"([^"]+)"|(\S+))/i', function (array $matches) use (&$filters, $workspace, $member) {
            $operator = strtolower($matches[1]);
            $value = trim($matches[2] !== '' ? $matches[2] : $matches[3]);
            if ($operator === 'from') {
                $filters['from_member_id'] = $this->resolveSearchMember($workspace, $value);
            } elseif ($operator === 'in') {
                $filters['conversation_id'] = $this->resolveSearchConversation($workspace, $member, $value);
            } elseif ($operator === 'before') {
                try { $filters['before'] = CarbonImmutable::parse($value)->endOfDay(); } catch (\Throwable) {}
            } elseif ($operator === 'after') {
                try { $filters['after'] = CarbonImmutable::parse($value)->startOfDay(); } catch (\Throwable) {}
            } elseif ($operator === 'has' && strtolower($value) === 'file') {
                $filters['has_file'] = true;
            } elseif ($operator === 'has' && strtolower($value) === 'link') {
                $filters['has_link'] = true;
            }
            return ' ';
        }, $term) ?? $term;

        return ['text' => trim(preg_replace('/\s+/', ' ', $text) ?? '')] + $filters;
    }

    /**
     * Resolves a from: search value to one active workspace member ID.
     */
    private function resolveSearchMember(Workspace $workspace, string $value): ?int
    {
        $query = WorkspaceMember::query()->where('workspace_id', $workspace->id)->where('status', 'active');
        if (ctype_digit($value)) return $query->whereKey((int) $value)->value('id') ?: -1;
        $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $value).'%';
        return $query->whereHas('user', fn ($userQuery) => $userQuery->where('email', 'like', $needle)->orWhere('first_name', 'like', $needle)->orWhere('last_name', 'like', $needle))->value('id') ?: -1;
    }

    /**
     * Resolves an in: search value only among conversations visible to the current member.
     */
    private function resolveSearchConversation(Workspace $workspace, WorkspaceMember $member, string $value): ?int
    {
        $query = ChatConversation::query()->where('workspace_id', $workspace->id)->whereHas('members', fn ($memberQuery) => $memberQuery->where('workspace_members.id', $member->id));
        if (ctype_digit($value)) return $query->whereKey((int) $value)->value('id') ?: -1;
        return $query->where(fn ($conversationQuery) => $conversationQuery->where('uuid', $value)->orWhere('name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $value).'%'))->value('id') ?: -1;
    }

    /**
     * Streams a private attachment after re-validating conversation membership.
     */
    public function attachment(ChatMessageAttachment $attachment, WorkspaceMember $member)
    {
        $this->assertMember($attachment->message->conversation, $member);
        abort_unless($this->dlp->canDownloadAttachment($attachment, $member), 403, 'This attachment is quarantined by the workspace DLP policy.');
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);
        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->filename,
            ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream'],
        );
    }

    /**
     * Stores a validated private attachment and records its SHA-256 checksum.
     */
    private function storeAttachment(ChatMessage $message, UploadedFile $file, array $dlpResult): ChatMessageAttachment
    {
        abort_if(! $file->isValid(), 422, 'Invalid attachment.');
        abort_if($file->getSize() > 20 * 1024 * 1024, 422, 'Each attachment must be 20 MB or smaller.');
        $path = $file->store('chat/'.$message->workspace_id.'/'.$message->conversation_id, 'local');

        $quarantine = ($dlpResult['action'] ?? 'clean') === 'quarantine' && collect($dlpResult['matches'] ?? [])->contains(fn ($match) => collect($match['rules'] ?? [])->contains(fn ($rule) => str_starts_with((string) $rule, 'file_')));
        return ChatMessageAttachment::create([
            'message_id' => $message->id,
            'disk' => 'local',
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'checksum_sha256' => hash_file('sha256', Storage::disk('local')->path($path)),
            'security_status' => $quarantine ? 'quarantined' : (($dlpResult['action'] ?? 'clean') === 'monitor' ? 'review' : 'clean'),
            'security_reason' => $quarantine ? 'Quarantined by workspace DLP policy.' : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Notifies active thread followers about a new reply without notifying the sender twice.
     */
    private function notifyThreadFollowers(ChatMessage $root, WorkspaceMember $sender, ChatMessage $reply): void
    {
        $followerIds = ChatThreadFollow::query()
            ->where('root_message_id', $root->id)
            ->where('is_following', true)
            ->where('member_id', '!=', $sender->id)
            ->pluck('member_id');
        if ($followerIds->isEmpty()) return;
        foreach (WorkspaceMember::with('user')->where('workspace_id', $root->workspace_id)->where('status', 'active')->whereIn('id', $followerIds)->get() as $target) {
            $preference = DB::table('chat_conversation_members')->where(['conversation_id' => $root->conversation_id, 'member_id' => $target->id])->first(['notification_mode', 'notifications_snoozed_until']);
            if (($preference?->notification_mode ?? 'all') === 'nothing' || ($preference?->notifications_snoozed_until && now()->lt($preference->notifications_snoozed_until))) continue;
            if (! $target->user) continue;
            $this->notifications->notify(
                $root->conversation->workspace,
                $target->user,
                'chat_threads',
                'chat.thread_reply',
                'New reply in a followed chat thread',
                Str::limit((string) $reply->body, 180),
                'info',
                ['conversation_id' => $root->conversation_id, 'message_id' => $reply->id, 'root_message_id' => $root->id],
            );
        }
    }

    /**
     * Extracts mention tokens that reference members of the same conversation.
     */
    private function mentions(string $body, ChatConversation $conversation): array
    {
        preg_match_all('/@\[member:(\d+)\]/', $body, $matches);
        $ids = collect($matches[1] ?? [])->map(fn ($value) => (int) $value)->unique()->values();
        return $conversation->members()->whereIn('workspace_members.id', $ids)->pluck('workspace_members.id')->all();
    }

    /**
     * Sends workspace notifications to mentioned members other than the sender.
     */
    private function notifyMentions(ChatConversation $conversation, WorkspaceMember $sender, ChatMessage $message, array $mentions): void
    {
        if (! $mentions) {
            return;
        }

        $workspace = $conversation->workspace;
        foreach (WorkspaceMember::with('user')->whereIn('id', $mentions)->where('id', '!=', $sender->id)->get() as $target) {
            $preference = DB::table('chat_conversation_members')->where(['conversation_id' => $conversation->id, 'member_id' => $target->id])->first(['notification_mode', 'notifications_snoozed_until']);
            if (($preference?->notification_mode ?? 'all') === 'nothing' || ($preference?->notifications_snoozed_until && now()->lt($preference->notifications_snoozed_until))) continue;
            if ($target->user) {
                $this->notifications->notify(
                    $workspace,
                    $target->user,
                    'chat_mentions',
                    'chat.mention',
                    'You were mentioned in chat',
                    Str::limit((string) $message->body, 180),
                    'info',
                    ['conversation_id' => $conversation->id, 'message_id' => $message->id],
                );
            }
        }
    }

    /**
     * Sends normal-message notifications only to members who explicitly selected all-message delivery.
     */
    private function notifyConversationMembers(ChatConversation $conversation, WorkspaceMember $sender, ChatMessage $message, array $mentions): void
    {
        $targets = WorkspaceMember::with('user')
            ->where('workspace_id', $conversation->workspace_id)
            ->where('status', 'active')
            ->where('id', '!=', $sender->id)
            ->whereNotIn('id', $mentions ?: [0])
            ->whereIn('id', DB::table('chat_conversation_members')->where('conversation_id', $conversation->id)->pluck('member_id'))
            ->get();

        foreach ($targets as $target) {
            $preference = DB::table('chat_conversation_members')
                ->where(['conversation_id' => $conversation->id, 'member_id' => $target->id])
                ->first(['notification_mode', 'notifications_snoozed_until']);
            if (($preference?->notification_mode ?? 'all') !== 'all') continue;
            if ($preference?->notifications_snoozed_until && now()->lt($preference->notifications_snoozed_until)) continue;
            if (! $target->user) continue;

            $this->notifications->notify(
                $conversation->workspace,
                $target->user,
                $conversation->type === 'direct' ? 'chat_direct' : 'chat_channels',
                'chat.message',
                'New chat message',
                Str::limit((string) $message->body, 180),
                'info',
                ['conversation_id' => $conversation->id, 'message_id' => $message->id],
            );
        }
    }

    /**
     * Preloads viewer-specific message state in batches to avoid per-message query amplification.
     */
    private function messagePayloadState(ChatConversation $conversation, WorkspaceMember $viewer, $rows): array
    {
        $messageIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->values();
        if ($messageIds->isEmpty()) return [];
        $threadFollows = ChatThreadFollow::query()
            ->where('member_id', $viewer->id)
            ->whereIn('root_message_id', $messageIds)
            ->get()
            ->keyBy('root_message_id');
        $replyIds = ChatMessage::query()
            ->whereIn('parent_id', $messageIds)
            ->whereNull('deleted_at')
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');
        $threadUnread = [];
        foreach ($messageIds as $messageId) {
            $lastRead = (int) ($threadFollows->get($messageId)?->last_read_reply_id ?? 0);
            $threadUnread[$messageId] = $replyIds->get($messageId, collect())->where('id', '>', $lastRead)->count();
        }
        $pins = ChatMessagePin::query()->whereIn('message_id', $messageIds)->pluck('message_id')->map(fn ($id) => (int) $id)->flip();
        $saved = ChatSavedMessage::query()->where('member_id', $viewer->id)->whereIn('message_id', $messageIds)->pluck('message_id')->map(fn ($id) => (int) $id)->flip();
        $cursorColumns = ['member_id', 'last_read_message_id'];
        if (Schema::hasColumn('chat_conversation_members', 'last_delivered_message_id')) $cursorColumns[] = 'last_delivered_message_id';
        $cursors = DB::table('chat_conversation_members')
            ->where('conversation_id', $conversation->id)
            ->get($cursorColumns);

        return compact('threadFollows', 'threadUnread', 'pins', 'saved', 'cursors');
    }

    /**
     * Shapes a message for the requesting member without exposing storage paths.
     */
    public function messagePayload(ChatMessage $message, WorkspaceMember $viewer, array $state = []): array
    {
        $reactions = $message->relationLoaded('reactions') ? $message->reactions : collect();
        $rootMessageId = $message->parent_id ?: $message->id;
        $threadFollow = isset($state['threadFollows'])
            ? $state['threadFollows']->get($rootMessageId)
            : ChatThreadFollow::where(['root_message_id' => $rootMessageId, 'member_id' => $viewer->id])->first();
        $threadUnread = $message->parent_id ? 0 : (isset($state['threadUnread'])
            ? (int) ($state['threadUnread'][$rootMessageId] ?? 0)
            : $message->replies()->whereNull('deleted_at')->where('id', '>', (int) ($threadFollow?->last_read_reply_id ?? 0))->count());
        $cursorRows = $state['cursors'] ?? null;
        $readBy = $message->parent_id
            ? DB::table('chat_thread_follows')->where('root_message_id', $rootMessageId)->where('member_id', '!=', $message->sender_member_id)->where('last_read_reply_id', '>=', $message->id)->count()
            : ($cursorRows
                ? $cursorRows->filter(fn ($row) => (int) $row->member_id !== (int) $message->sender_member_id && (int) ($row->last_read_message_id ?? 0) >= $message->id)->count()
                : DB::table('chat_conversation_members')->where('conversation_id', $message->conversation_id)->where('member_id', '!=', $message->sender_member_id)->where('last_read_message_id', '>=', $message->id)->count());
        $deliveredTo = $message->parent_id || ! Schema::hasColumn('chat_conversation_members', 'last_delivered_message_id')
            ? 0
            : ($cursorRows
                ? $cursorRows->filter(fn ($row) => (int) $row->member_id !== (int) $message->sender_member_id && (int) ($row->last_delivered_message_id ?? 0) >= $message->id)->count()
                : DB::table('chat_conversation_members')->where('conversation_id', $message->conversation_id)->where('member_id', '!=', $message->sender_member_id)->where('last_delivered_message_id', '>=', $message->id)->count());

        return [
            'id' => $message->id,
            'uuid' => $message->uuid,
            'client_message_id' => $message->client_message_id,
            'client_sent_at' => $message->client_sent_at?->toIso8601String(),
            'delivery_state' => 'sent',
            'conversation_id' => $message->conversation_id,
            'body' => $message->deleted_at ? null : $message->body,
            'deleted_at' => $message->deleted_at?->toIso8601String(),
            'edited_at' => $message->edited_at?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
            'sender' => $message->sender ? [
                'id' => $message->sender->id,
                'name' => trim(($message->sender->user?->first_name ?? '').' '.($message->sender->user?->last_name ?? '')),
                'kind' => 'member',
                'collaboration_type' => $message->sender->collaboration_type ?? 'internal',
                'external_company' => $message->sender->external_company,
            ] : ($message->senderBot ? [
                'id' => null,
                'bot_id' => $message->senderBot->id,
                'name' => $message->senderBot->name,
                'kind' => 'bot',
                'bot_kind' => $message->senderBot->kind,
            ] : null),
            'message_type' => $message->message_type ?? 'message',
            'metadata' => $message->metadata ?? [],
            'parent' => $message->parent ? [
                'id' => $message->parent->id,
                'body' => Str::limit((string) $message->parent->body, 120),
                'sender' => trim(($message->parent->sender?->user?->first_name ?? '').' '.($message->parent->sender?->user?->last_name ?? '')),
            ] : null,
            'mentions' => $message->mentions ?? [],
            'attachments' => $message->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'filename' => $attachment->filename,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'security_status' => $attachment->security_status ?? 'clean',
                'security_reason' => $attachment->security_reason,
                'url' => '/api/v1/chat/attachments/'.$attachment->id,
            ])->all(),
            'reactions' => $reactions->groupBy('emoji')->map(fn ($rows, $emoji) => [
                'emoji' => $emoji,
                'count' => $rows->count(),
                'mine' => $rows->contains('member_id', $viewer->id),
            ])->values()->all(),
            'read_by' => $readBy,
            'delivered_to' => $deliveredTo,
            'mine' => $message->sender_member_id === $viewer->id,
            'pinned' => isset($state['pins']) ? $state['pins']->has($message->id) : ChatMessagePin::where('message_id', $message->id)->exists(),
            'saved' => isset($state['saved']) ? $state['saved']->has($message->id) : ChatSavedMessage::where(['message_id' => $message->id, 'member_id' => $viewer->id])->exists(),
            'thread_reply_count' => (int) ($message->thread_reply_count ?? $message->replies()->whereNull('deleted_at')->count()),
            'thread_following' => (bool) ($threadFollow?->is_following ?? false),
            'thread_unread_count' => $threadUnread,
            'forwarded' => $message->forwardedFrom ? [
                'id' => $message->forwardedFrom->id,
                'body' => $message->forwardedFrom->deleted_at ? null : Str::limit((string) $message->forwardedFrom->body, 1000),
                'deleted' => (bool) $message->forwardedFrom->deleted_at,
                'sender' => trim(($message->forwardedFrom->sender?->user?->first_name ?? '').' '.($message->forwardedFrom->sender?->user?->last_name ?? '')),
                'attachment_count' => $message->forwardedFrom->relationLoaded('attachments') ? $message->forwardedFrom->attachments->count() : $message->forwardedFrom->attachments()->count(),
            ] : null,
            'poll' => $message->poll ? $this->pollPayload($message->poll, $viewer) : null,
        ];
    }

    /**
     * Shapes a conversation and resolves a direct-message display name for the viewer.
     */
    private function conversationPayload(ChatConversation $conversation, ?ChatMessage $last, int $unread, WorkspaceMember $viewer, ?ChatDraft $draft = null): array
    {
        $name = $conversation->name;
        if (! $name && $conversation->type === 'direct') {
            $other = $conversation->members->first(fn ($member) => $member->id !== $viewer->id);
            $name = trim(($other?->user?->first_name ?? '').' '.($other?->user?->last_name ?? ''));
        }

        $viewerPivot = $conversation->members->firstWhere('id', $viewer->id)?->pivot;

        return [
            'id' => $conversation->id,
            'uuid' => $conversation->uuid,
            'type' => $conversation->type,
            'name' => $name ?: ucfirst($conversation->type),
            'description' => $conversation->description,
            'visibility' => $conversation->visibility ?? 'private',
            'channel_mode' => $conversation->channel_mode ?? 'standard',
            'posting_policy' => $conversation->posting_policy ?? 'members',
            'is_locked' => (bool) ($conversation->is_locked ?? false),
            'external_access' => (bool) ($conversation->external_access ?? false),
            'retention_days' => $conversation->retention_days ? (int) $conversation->retention_days : null,
            'legal_hold' => (bool) ($conversation->legal_hold ?? false),
            'export_policy' => $conversation->export_policy ?? 'admins',
            'dlp_mode' => $conversation->dlp_mode ?? 'inherit',
            'viewer_role' => $viewerPivot?->role ?? 'member',
            'notification_mode' => $viewerPivot?->notification_mode ?? ((bool) ($viewerPivot?->is_muted ?? false) ? 'nothing' : 'all'),
            'notifications_snoozed_until' => $viewerPivot?->notifications_snoozed_until,
            'unread_count' => $unread,
            'is_muted' => (bool) ($viewerPivot?->is_muted ?? false),
            'draft' => $draft ? ['body' => Str::limit((string) $draft->body, 100), 'updated_at' => $draft->updated_at?->toIso8601String()] : null,
            'project' => $conversation->project ? ['id' => $conversation->project->id, 'name' => $conversation->project->name] : null,
            'task' => $conversation->task ? ['id' => $conversation->task->id, 'title' => $conversation->task->title] : null,
            'members' => $conversation->members->map(fn ($member) => [
                'id' => $member->id,
                'name' => trim(($member->user?->first_name ?? '').' '.($member->user?->last_name ?? '')),
                'is_self' => $member->id === $viewer->id,
                'role' => $member->pivot?->role ?? 'member',
                'collaboration_type' => $member->collaboration_type ?? 'internal',
                'external_company' => $member->external_company,
                'external_expires_at' => $member->external_expires_at?->toIso8601String(),
                'guest_expires_at' => $member->pivot?->guest_expires_at,
            ])->all(),
            'last_message' => $last ? [
                'id' => $last->id,
                'body' => $this->messageSummary($last),
                'created_at' => $last->created_at?->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * Builds a short, non-sensitive conversation-list summary for special message types.
     */
    private function messageSummary(ChatMessage $message): string
    {
        if ($message->deleted_at) {
            return 'Message deleted';
        }

        if ($message->body) {
            return Str::limit($message->body, 100);
        }

        if ($message->forwarded_from_message_id) {
            return 'Forwarded message';
        }

        if ($message->poll()->exists()) {
            return 'Poll';
        }

        if ($message->attachments()->exists()) {
            return 'Attachment';
        }

        return 'Message';
    }
}
