<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatDlpPolicy;
use App\Models\ChatExportJob;
use App\Models\ChatLegalHold;
use App\Models\ChatMessage;
use App\Models\WorkspaceMember;
use App\Services\Chat\ChatEnterpriseCollaborationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Exposes Chat V2.4 enterprise collaboration administration through workspace-scoped APIs. */
class ChatEnterpriseController extends Controller
{
    /** Returns guests, holds, exports, DLP and moderation audit state for the workspace. */
    public function overview(Request $request, ChatEnterpriseCollaborationService $service): JsonResponse
    {
        return response()->json(['data' => $service->overview($request->attributes->get('workspace'), $request->attributes->get('workspaceMember'))]);
    }

    /** Creates a one-time external collaborator invitation scoped to one externally enabled conversation. */
    public function inviteExternal(Request $request, ChatConversation $conversation, ChatEnterpriseCollaborationService $service): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'collaboration_type' => ['required', Rule::in(['guest', 'client', 'vendor'])],
            'external_company' => 'nullable|string|max:180',
            'external_expires_at' => 'required|date|after:now',
        ]);
        $result = $service->inviteExternal($request->attributes->get('workspace'), $request->attributes->get('workspaceMember'), $conversation, $data);
        return response()->json([
            'data' => $result['invitation'], 'invite_url' => $result['invite_url'], 'token' => $result['token'],
            'warning' => 'Copy the invitation link now. The raw token is never stored.',
        ], 201);
    }

    /** Extends, revokes or restores an existing external collaborator membership. */
    public function updateExternalMember(Request $request, WorkspaceMember $member, ChatEnterpriseCollaborationService $service): JsonResponse
    {
        $data = $request->validate([
            'external_company' => 'sometimes|nullable|string|max:180',
            'external_expires_at' => 'sometimes|nullable|date|after:now',
            'action' => ['sometimes', Rule::in(['update', 'revoke', 'restore'])],
        ]);
        return response()->json(['data' => $service->updateExternalMember($request->attributes->get('workspace'), $request->attributes->get('workspaceMember'), $member, $data)]);
    }

    /** Updates external-access, retention, export and DLP policy for one conversation. */
    public function updateConversationPolicy(Request $request, ChatConversation $conversation, ChatEnterpriseCollaborationService $service): JsonResponse
    {
        $data = $request->validate([
            'external_access' => 'sometimes|boolean',
            'retention_days' => 'sometimes|nullable|integer|min:1|max:3650',
            'export_policy' => ['sometimes', Rule::in(['admins', 'moderators', 'members', 'disabled'])],
            'dlp_mode' => ['sometimes', Rule::in(['inherit', 'off'])],
        ]);
        return response()->json(['data' => $service->updateConversationPolicy($conversation, $request->attributes->get('workspaceMember'), $data)]);
    }

    /** Places chat data under a workspace-wide or conversation-specific legal hold. */
    public function createLegalHold(Request $request, ChatEnterpriseCollaborationService $service): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:180', 'reason' => 'nullable|string|max:2000', 'conversation_id' => 'nullable|integer']);
        return response()->json(['data' => $service->createLegalHold($request->attributes->get('workspace'), $request->attributes->get('workspaceMember'), $data)], 201);
    }

    /** Releases an active legal hold without deleting its audit history. */
    public function releaseLegalHold(Request $request, ChatLegalHold $hold, ChatEnterpriseCollaborationService $service): JsonResponse
    {
        return response()->json(['data' => $service->releaseLegalHold($request->attributes->get('workspace'), $request->attributes->get('workspaceMember'), $hold)]);
    }

    /** Generates a private expiring eDiscovery export for one conversation. */
    public function createExport(Request $request, ChatConversation $conversation, ChatEnterpriseCollaborationService $service): JsonResponse
    {
        $data = $request->validate(['format' => ['nullable', Rule::in(['json', 'csv'])]]);
        return response()->json(['data' => $service->exportConversation($conversation, $request->attributes->get('workspaceMember'), $data['format'] ?? 'json')], 201);
    }

    /** Downloads an eDiscovery export owned by the requesting workspace member. */
    public function downloadExport(Request $request, ChatExportJob $export, ChatEnterpriseCollaborationService $service)
    {
        return $service->downloadExport($export, $request->attributes->get('workspaceMember'));
    }

    /** Creates an active workspace DLP policy. */
    public function createDlpPolicy(Request $request, ChatEnterpriseCollaborationService $service): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160', 'mode' => ['required', Rule::in(['monitor', 'quarantine', 'block'])],
            'keywords' => 'nullable|array|max:50', 'keywords.*' => 'string|max:200',
            'file_extensions' => 'nullable|array|max:50', 'file_extensions.*' => 'string|max:20',
            'max_file_bytes' => 'nullable|integer|min:1024|max:1073741824', 'active' => 'sometimes|boolean',
        ]);
        return response()->json(['data' => $service->createDlpPolicy($request->attributes->get('workspace'), $request->attributes->get('workspaceMember'), $data)], 201);
    }

    /** Updates one workspace DLP policy. */
    public function updateDlpPolicy(Request $request, ChatDlpPolicy $policy, ChatEnterpriseCollaborationService $service): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:160', 'mode' => ['sometimes', Rule::in(['monitor', 'quarantine', 'block'])],
            'keywords' => 'sometimes|array|max:50', 'keywords.*' => 'string|max:200',
            'file_extensions' => 'sometimes|array|max:50', 'file_extensions.*' => 'string|max:20',
            'max_file_bytes' => 'sometimes|nullable|integer|min:1024|max:1073741824', 'active' => 'sometimes|boolean',
        ]);
        return response()->json(['data' => $service->updateDlpPolicy($request->attributes->get('workspace'), $request->attributes->get('workspaceMember'), $policy, $data)]);
    }

    /** Flags or redacts a message and writes an immutable moderation audit event. */
    public function moderateMessage(Request $request, ChatMessage $message, ChatEnterpriseCollaborationService $service): JsonResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['flag', 'redact'])], 'reason' => 'nullable|string|max:500']);
        return response()->json(['data' => $service->moderateMessage($message, $request->attributes->get('workspaceMember'), $data['action'], $data['reason'] ?? null)]);
    }
}
