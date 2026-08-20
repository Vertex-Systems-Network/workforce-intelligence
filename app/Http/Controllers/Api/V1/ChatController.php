<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\ChatChannelResource;
use App\Models\ChatPoll;
use App\Models\ChatPresence;
use App\Models\WorkspaceMember;
use App\Services\Chat\ChatService;
use App\Services\Chat\ChatWorkspaceCollaborationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Exposes workspace-scoped chat APIs while delegating membership rules to ChatService.
 */
class ChatController extends Controller
{
    /**
     * Lists conversations and presence visible to the current member only.
     */
    public function index(Request $request, ChatService $service): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');
        $conversationIds = DB::table('chat_conversation_members')->where('member_id', $member->id)->pluck('conversation_id');
        $conversationMemberIds = DB::table('chat_conversation_members')
            ->whereIn('conversation_id', $conversationIds)
            ->pluck('member_id')
            ->unique();
        $visibleMemberIds = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->whereIn('id', $conversationMemberIds)
            ->pluck('id');
        $presence = ChatPresence::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('member_id', $visibleMemberIds)
            ->where('last_seen_at', '>=', now()->subSeconds(75))
            ->get(['member_id', 'conversation_id', 'is_typing', 'last_seen_at']);

        return response()->json([
            'data' => $service->conversations($workspace, $member),
            'presence' => $presence,
            'viewer_member_id' => $member->id,
        ]);
    }

    /** Returns the current member's collaboration inbox across mentions, followed threads and unread direct messages. */
    public function inbox(Request $request, ChatService $service): JsonResponse
    {
        return response()->json(['data' => $service->collaborationInbox($request->attributes->get('workspace'), $request->attributes->get('workspaceMember'))]);
    }

    /** Applies one private collaboration-inbox triage action or marks the current inbox complete. */
    public function triageInbox(Request $request, ChatService $service): JsonResponse
    {
        $data=$request->validate(['action'=>'required|in:done,reopen,snooze,follow_up,read_all','activity_key'=>'nullable|string|max:100','until'=>'nullable|date']);
        return response()->json(['data'=>$service->triageInbox($request->attributes->get('workspace'),$request->attributes->get('workspaceMember'),$data['action'],$data['activity_key']??null,$data['until']??null)]);
    }

    /** Returns the dedicated mentions, threads, DMs and channel notification matrix. */
    public function notificationPreferences(Request $request, ChatService $service): JsonResponse
    {
        return response()->json(['data'=>$service->chatNotificationPreferences($request->attributes->get('workspace'),$request->attributes->get('workspaceMember'))]);
    }

    /** Updates only allowlisted chat notification preferences. */
    public function updateNotificationPreferences(Request $request, ChatService $service): JsonResponse
    {
        $data=$request->validate(['preferences'=>'required|array|size:4','preferences.*.category'=>'required|in:chat_mentions,chat_threads,chat_direct,chat_channels','preferences.*.in_app'=>'required|boolean','preferences.*.email'=>'required|boolean','preferences.*.digest'=>'required|in:immediate,daily,weekly']);
        return response()->json(['data'=>$service->updateChatNotificationPreferences($request->attributes->get('workspace'),$request->attributes->get('workspaceMember'),$data['preferences'])]);
    }

    /** Returns cursor-paged pins, private bookmarks and recent files for one conversation context panel. */
    public function context(Request $request, ChatConversation $conversation, ChatService $service): JsonResponse
    {
        $data=$request->validate(['limit'=>'nullable|integer|min:5|max:50','pin_before'=>'nullable|integer|min:0','bookmark_before'=>'nullable|integer|min:0','file_before'=>'nullable|integer|min:0']);
        return response()->json(['data' => $service->conversationContext($conversation, $request->attributes->get('workspaceMember'), (int)($data['limit']??20), $data['pin_before']??null, $data['bookmark_before']??null, $data['file_before']??null)]);
    }

    /** Performs a bounded bulk unpin or private-bookmark cleanup operation. */
    public function bulkContext(Request $request, ChatConversation $conversation, ChatService $service): JsonResponse
    {
        $data=$request->validate(['action'=>'required|in:unpin,delete_bookmarks','ids'=>'required|array|min:1|max:100','ids.*'=>'integer|min:1']);
        return response()->json(['data'=>$service->bulkContext($conversation,$request->attributes->get('workspaceMember'),$data['action'],$data['ids'])]);
    }

    /**
     * Returns active member, project and task choices for conversation creation.
     */
    public function options(Request $request, ChatService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->creationOptions(
                $request->attributes->get('workspace'),
                $request->attributes->get('workspaceMember'),
            ),
        ]);
    }

    /**
     * Creates a new conversation or returns an existing canonical direct conversation.
     */
    public function storeConversation(Request $request, ChatService $service): JsonResponse
    {
        $member = $request->attributes->get('workspaceMember');
        $data = $request->validate([
            'type' => 'required|in:direct,group,channel,project,task',
            'name' => 'nullable|string|max:160',
            'description' => 'nullable|string|max:2000',
            'member_ids' => 'array|max:100',
            'member_ids.*' => 'integer',
            'project_id' => 'nullable|integer',
            'task_id' => 'nullable|integer',
            'visibility' => 'nullable|in:public,private',
            'channel_mode' => 'nullable|in:standard,announcement',
            'posting_policy' => 'nullable|in:members,admins',
        ]);

        if ($data['type'] === 'channel' && trim((string) ($data['name'] ?? '')) === '') {
            return response()->json(['message' => 'Channel name is required.', 'errors' => ['name' => ['Channel name is required.']]], 422);
        }
        if (in_array($data['type'], ['channel', 'project', 'task'], true)) {
            abort_unless($member->hasPermission('chat.manage'), 403);
        }

        $conversation = $service->createConversation($request->attributes->get('workspace'), $member, $data);
        $status = $conversation->wasRecentlyCreated ? 201 : 200;

        return response()->json([
            'data' => $conversation,
            'message' => $status === 201 ? 'Conversation created.' : 'Existing direct conversation opened.',
            'created' => $status === 201,
        ], $status);
    }

    /**
     * Returns a cursor slice of messages for a conversation member.
     */
    public function messages(Request $request, ChatConversation $conversation, ChatService $service): JsonResponse
    {
        $page = $service->messagePage(
            $conversation,
            $request->attributes->get('workspaceMember'),
            $request->integer('before') ?: null,
            $request->integer('after') ?: null,
            $request->integer('around') ?: null,
            $request->integer('limit', (int) config('workintel.chat.page_size', 60)),
        );

        return response()->json(['data' => $page['items'], 'meta' => $page['meta']]);
    }

    /**
     * Sends a message with optional reply metadata and private attachments.
     */
    public function send(Request $request, ChatConversation $conversation, ChatService $service, ChatWorkspaceCollaborationService $collaboration): JsonResponse
    {
        $attachmentCountMax = max(1, (int) config('workintel.chat.attachment_count_max', 8));
        $attachmentSizeKb = max(1, (int) config('workintel.chat.attachment_size_kb', 20480));
        $data = $request->validate([
            'body' => 'nullable|string|max:10000',
            'parent_id' => 'nullable|integer',
            'client_message_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'client_sent_at' => 'nullable|date',
            'attachments' => 'array|max:'.$attachmentCountMax,
            'attachments.*' => 'file|max:'.$attachmentSizeKb,
        ]);
        $member = $request->attributes->get('workspaceMember');
        $collaboration->assertCanPost($conversation, $member);
        if (trim((string) ($data['body'] ?? '')) !== '' && str_starts_with(trim((string) $data['body']), '/')) {
            abort_if(count($request->file('attachments', [])) > 0, 422, 'Slash commands cannot include file attachments.');
            $message = $collaboration->executeSlashCommand($conversation, $member, (string) $data['body']);
            return response()->json(['data' => $service->messagePayload($message, $member)], 201);
        }
        $message = $service->send(
            $conversation,
            $member,
            $data['body'] ?? null,
            $data['parent_id'] ?? null,
            $request->file('attachments', []),
            $data['client_message_id'] ?? null,
            $data['client_sent_at'] ?? null,
        );

        $duplicate = ! $message->wasRecentlyCreated && ! empty($data['client_message_id']);
        return response()->json([
            'data' => $service->messagePayload($message, $member),
            'idempotent_replay' => $duplicate,
        ], $duplicate ? 200 : 201);
    }

    /**
     * Edits a message owned by the current member.
     */
    public function edit(Request $request, ChatMessage $message, ChatService $service): JsonResponse
    {
        $data = $request->validate(['body' => 'required|string|max:10000']);
        $member = $request->attributes->get('workspaceMember');
        $updated = $service->edit($message, $member, $data['body']);
        return response()->json(['data' => $service->messagePayload($updated, $member)]);
    }

    /**
     * Soft-deletes a message when the actor is the sender or a moderator.
     */
    public function destroy(Request $request, ChatMessage $message, ChatService $service): JsonResponse
    {
        $member = $request->attributes->get('workspaceMember');
        $service->delete($message, $member, $member->hasPermission('chat.moderate'));
        return response()->json(['message' => 'Message deleted.']);
    }

    /**
     * Toggles a reaction for the current member.
     */
    public function react(Request $request, ChatMessage $message, ChatService $service): JsonResponse
    {
        $data = $request->validate(['emoji' => 'required|string|max:32']);
        return response()->json([
            'active' => $service->react($message, $request->attributes->get('workspaceMember'), $data['emoji']),
        ]);
    }

    /**
     * Toggles a pin for a message in the current member's conversation.
     */
    public function pin(Request $request, ChatMessage $message, ChatService $service): JsonResponse
    {
        return response()->json([
            'pinned' => $service->pin($message, $request->attributes->get('workspaceMember')),
        ]);
    }

    /**
     * Toggles the current member's mute state for a conversation.
     */
    public function mute(Request $request, ChatConversation $conversation, ChatService $service): JsonResponse
    {
        return response()->json([
            'muted' => $service->mute($conversation, $request->attributes->get('workspaceMember')),
        ]);
    }

    /**
     * Advances the current member's read cursor for a conversation.
     */
    public function read(Request $request, ChatConversation $conversation, ChatService $service): JsonResponse
    {
        $service->markRead(
            $conversation,
            $request->attributes->get('workspaceMember'),
            $request->integer('message_id') ?: null,
        );
        return response()->json(['ok' => true]);
    }

    /**
     * Refreshes member presence and optional typing state.
     */
    public function presence(Request $request, ChatService $service): JsonResponse
    {
        $data = $request->validate(['conversation_id' => 'nullable|integer', 'typing' => 'nullable|boolean']);
        $conversation = ! empty($data['conversation_id']) ? ChatConversation::findOrFail($data['conversation_id']) : null;
        $service->presence(
            $request->attributes->get('workspace'),
            $request->attributes->get('workspaceMember'),
            $conversation,
            (bool) ($data['typing'] ?? false),
        );
        return response()->json(['ok' => true]);
    }

    /**
     * Returns edit-history snapshots for a message to its sender or a chat moderator.
     */
    public function history(Request $request, ChatMessage $message, ChatService $service): JsonResponse
    {
        return response()->json(['data' => $service->editHistory($message, $request->attributes->get('workspaceMember'))]);
    }

    /**
     * Toggles a private saved-message bookmark for the current member.
     */
    public function saveMessage(Request $request, ChatMessage $message, ChatService $service): JsonResponse
    {
        return response()->json(['saved' => $service->toggleSaved($message, $request->attributes->get('workspaceMember'))]);
    }

    /** Updates the private note on one existing saved-message bookmark. */
    public function updateSavedNote(Request $request, ChatMessage $message, ChatService $service): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:500']);
        return response()->json(['data' => $service->updateSavedNote($message, $request->attributes->get('workspaceMember'), $data['note'] ?? null)]);
    }

    /**
     * Lists the current member's private saved messages.
     */
    public function saved(Request $request, ChatService $service): JsonResponse
    {
        return response()->json(['data' => $service->savedMessages($request->attributes->get('workspaceMember'))]);
    }

    /**
     * Returns the current member's cross-device draft for a conversation.
     */
    public function draft(Request $request, ChatConversation $conversation, ChatService $service): JsonResponse
    {
        return response()->json(['data' => $service->draft($conversation, $request->attributes->get('workspaceMember'))]);
    }

    /**
     * Saves a text draft and optional thread target for the current member.
     */
    public function saveDraft(Request $request, ChatConversation $conversation, ChatService $service): JsonResponse
    {
        $data = $request->validate(['body' => 'nullable|string|max:10000', 'parent_id' => 'nullable|integer']);
        return response()->json(['data' => $service->saveDraft($conversation, $request->attributes->get('workspaceMember'), $data['body'] ?? null, $data['parent_id'] ?? null)]);
    }

    /**
     * Deletes the current member's saved draft for a conversation.
     */
    public function deleteDraft(Request $request, ChatConversation $conversation, ChatService $service): JsonResponse
    {
        $service->deleteDraft($conversation, $request->attributes->get('workspaceMember'));
        return response()->json(['ok' => true]);
    }

    /**
     * Returns a root message and all direct replies in its professional thread.
     */
    public function thread(Request $request, ChatMessage $message, ChatService $service): JsonResponse
    {
        return response()->json(['data' => $service->thread($message, $request->attributes->get('workspaceMember'))]);
    }

    /**
     * Updates whether the current member follows a message thread.
     */
    public function followThread(Request $request, ChatMessage $message, ChatService $service): JsonResponse
    {
        $data = $request->validate(['following' => 'required|boolean']);
        return response()->json(['following' => $service->followThread($message, $request->attributes->get('workspaceMember'), (bool) $data['following'])]);
    }

    /**
     * Forwards a visible message to another conversation that the actor can access.
     */
    public function forward(Request $request, ChatMessage $message, ChatService $service): JsonResponse
    {
        $data = $request->validate(['conversation_id' => 'required|integer', 'note' => 'nullable|string|max:2000']);
        $target = ChatConversation::findOrFail((int) $data['conversation_id']);
        $member = $request->attributes->get('workspaceMember');
        $forwarded = $service->forward($message, $member, $target, $data['note'] ?? null);
        return response()->json(['data' => $service->messagePayload($forwarded, $member)], 201);
    }

    /**
     * Creates a poll as a first-class chat message.
     */
    public function createPoll(Request $request, ChatConversation $conversation, ChatService $service, ChatWorkspaceCollaborationService $collaboration): JsonResponse
    {
        $data = $request->validate([
            'question' => 'required|string|max:1000',
            'options' => 'required|array|min:2|max:10',
            'options.*' => 'required|string|max:255',
            'allows_multiple' => 'nullable|boolean',
            'closes_at' => 'nullable|date|after:now',
        ]);
        $member = $request->attributes->get('workspaceMember');
        $collaboration->assertCanPost($conversation, $member);
        $message = $service->createPoll($conversation, $member, $data['question'], $data['options'], (bool) ($data['allows_multiple'] ?? false), $data['closes_at'] ?? null);
        return response()->json(['data' => $service->messagePayload($message, $member)], 201);
    }

    /**
     * Replaces the current member's selection for a chat poll.
     */
    public function votePoll(Request $request, ChatPoll $poll, ChatService $service): JsonResponse
    {
        $data = $request->validate(['option_ids' => 'present|array|max:10', 'option_ids.*' => 'integer']);
        return response()->json(['data' => $service->vote($poll, $request->attributes->get('workspaceMember'), $data['option_ids'])]);
    }

    /**
     * Searches messages inside conversations visible to the current member.
     */
    public function search(Request $request, ChatService $service): JsonResponse
    {
        $data = $request->validate(['q' => 'required|string|min:1|max:240']);
        return response()->json([
            'data' => $service->search(
                $request->attributes->get('workspace'),
                $request->attributes->get('workspaceMember'),
                $data['q'],
            ),
        ]);
    }

    /** Returns public channels that the current member may discover and join. */
    public function publicChannels(Request $request, ChatWorkspaceCollaborationService $collaboration): JsonResponse
    {
        return response()->json(['data' => $collaboration->discoverPublicChannels($request->attributes->get('workspace'), $request->attributes->get('workspaceMember'))]);
    }

    /** Joins the current member to a public channel. */
    public function joinChannel(Request $request, ChatConversation $conversation, ChatWorkspaceCollaborationService $collaboration): JsonResponse
    {
        $channel = $collaboration->joinPublicChannel($conversation, $request->attributes->get('workspaceMember'));
        return response()->json(['data' => array_merge($channel->toArray(), ['viewer_role' => 'member']), 'message' => 'Channel joined.']);
    }

    /** Leaves a channel after protecting the final channel owner. */
    public function leaveChannel(Request $request, ChatConversation $conversation, ChatWorkspaceCollaborationService $collaboration): JsonResponse
    {
        $collaboration->leaveChannel($conversation, $request->attributes->get('workspaceMember'));
        return response()->json(['message' => 'Channel left.']);
    }

    /** Updates governed channel metadata, visibility, posting policy, lock or archive state. */
    public function updateChannel(Request $request, ChatConversation $conversation, ChatWorkspaceCollaborationService $collaboration): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|nullable|string|max:160', 'description' => 'sometimes|nullable|string|max:2000',
            'visibility' => 'sometimes|in:public,private', 'channel_mode' => 'sometimes|in:standard,announcement',
            'posting_policy' => 'sometimes|in:members,admins', 'is_locked' => 'sometimes|boolean', 'archived' => 'sometimes|boolean',
        ]);
        return response()->json(['data' => $collaboration->updateChannel($conversation, $request->attributes->get('workspaceMember'), $data)]);
    }

    /** Adds active members to a governed channel. */
    public function addChannelMembers(Request $request, ChatConversation $conversation, ChatWorkspaceCollaborationService $collaboration): JsonResponse
    {
        $data = $request->validate(['member_ids' => 'required|array|min:1|max:100', 'member_ids.*' => 'integer']);
        return response()->json(['data' => $collaboration->addMembers($conversation, $request->attributes->get('workspaceMember'), $data['member_ids'])]);
    }

    /** Removes a member from a governed channel. */
    public function removeChannelMember(Request $request, ChatConversation $conversation, WorkspaceMember $member, ChatWorkspaceCollaborationService $collaboration): JsonResponse
    {
        $collaboration->removeMember($conversation, $request->attributes->get('workspaceMember'), $member);
        return response()->json(['message' => 'Channel member removed.']);
    }

    /** Changes a member's role inside a governed channel. */
    public function updateChannelMemberRole(Request $request, ChatConversation $conversation, WorkspaceMember $member, ChatWorkspaceCollaborationService $collaboration): JsonResponse
    {
        $data = $request->validate(['role' => 'required|in:owner,admin,moderator,member,read_only']);
        $collaboration->updateMemberRole($conversation, $request->attributes->get('workspaceMember'), $member, $data['role']);
        return response()->json(['message' => 'Channel role updated.']);
    }

    /** Saves all, mentions-only or no-notification delivery for the current conversation. */
    public function notificationMode(Request $request, ChatConversation $conversation, ChatWorkspaceCollaborationService $collaboration): JsonResponse
    {
        $data = $request->validate(['mode' => 'required|in:all,mentions,nothing', 'snoozed_until' => 'nullable|date']);
        return response()->json(['data' => $collaboration->updateNotificationMode($conversation, $request->attributes->get('workspaceMember'), $data['mode'], $data['snoozed_until'] ?? null)]);
    }

    /** Lists resources pinned to the active conversation. */
    public function resources(Request $request, ChatConversation $conversation, ChatWorkspaceCollaborationService $collaboration): JsonResponse
    {
        return response()->json(['data' => $collaboration->resources($conversation, $request->attributes->get('workspaceMember'))]);
    }

    /** Pins a link or WorkIntel entity to a channel resources panel. */
    public function addResource(Request $request, ChatConversation $conversation, ChatWorkspaceCollaborationService $collaboration): JsonResponse
    {
        $data = $request->validate([
            'kind' => 'nullable|in:link,project,task,document,report,other', 'label' => 'required|string|max:160', 'url' => 'nullable|string|max:1000',
            'resource_type' => 'nullable|string|max:60', 'resource_id' => 'nullable|integer', 'metadata' => 'nullable|array', 'sort_order' => 'nullable|integer|min:0|max:65000',
        ]);
        return response()->json(['data' => $collaboration->addResource($conversation, $request->attributes->get('workspaceMember'), $data)], 201);
    }

    /** Removes a pinned channel resource. */
    public function deleteResource(Request $request, ChatChannelResource $resource, ChatWorkspaceCollaborationService $collaboration): JsonResponse
    {
        $collaboration->deleteResource($resource, $request->attributes->get('workspaceMember'));
        return response()->json(['message' => 'Resource removed.']);
    }

    /** Creates a task, approval or incident directly from a source chat message. */
    public function messageAction(Request $request, ChatMessage $message, ChatWorkspaceCollaborationService $collaboration): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|in:task,approval,incident', 'title' => 'nullable|string|max:180', 'description' => 'nullable|string|max:5000',
            'project_id' => 'nullable|integer', 'priority' => 'nullable|in:low,medium,high,critical', 'summary' => 'nullable|string|max:5000',
            'type' => 'nullable|string|max:50', 'severity' => 'nullable|in:low,medium,high,critical', 'immediate_action' => 'nullable|string|max:5000',
        ]);
        $actor = $request->attributes->get('workspaceMember');
        $result = match ($data['action']) {
            'task' => $collaboration->createTaskFromMessage($message, $actor, $data),
            'approval' => $collaboration->createApprovalFromMessage($message, $actor, $data),
            'incident' => $collaboration->createIncidentFromMessage($message, $actor, $data),
        };
        return response()->json(['data' => $result], 201);
    }

    /**
     * Downloads a private attachment after conversation membership is revalidated.
     */
    public function attachment(Request $request, ChatMessageAttachment $attachment, ChatService $service)
    {
        $attachment->load('message.conversation');
        return $service->attachment($attachment, $request->attributes->get('workspaceMember'));
    }
}
