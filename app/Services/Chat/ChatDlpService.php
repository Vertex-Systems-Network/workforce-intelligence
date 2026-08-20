<?php

namespace App\Services\Chat;

use App\Contracts\ChatDlpScanner;
use App\Models\ChatConversation;
use App\Models\ChatDlpEvent;
use App\Models\ChatDlpPolicy;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\WorkspaceMember;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Evaluates low-dependency DLP rules before chat content is persisted or downloaded. */
class ChatDlpService implements ChatDlpScanner
{
    /** Returns active workspace DLP policies unless the conversation explicitly disables inherited checks. */
    public function policies(ChatConversation $conversation): array
    {
        if (! Schema::hasTable('chat_dlp_policies') || ($conversation->dlp_mode ?? 'inherit') === 'off') return [];

        return ChatDlpPolicy::query()
            ->where('workspace_id', $conversation->workspace_id)
            ->where('active', true)
            ->orderBy('id')
            ->get()
            ->all();
    }

    /** Scans message text and attachment metadata and blocks the request when a blocking rule matches. */
    public function preflight(ChatConversation $conversation, WorkspaceMember $actor, ?string $body, array $files): array
    {
        $matches = [];
        $highest = 'clean';
        foreach ($this->policies($conversation) as $policy) {
            $rules = [];
            $bodyNeedle = Str::lower((string) $body);
            foreach ($policy->keywords ?? [] as $keyword) {
                $keyword = trim((string) $keyword);
                if ($keyword !== '' && Str::contains($bodyNeedle, Str::lower($keyword))) $rules[] = 'keyword:'.$keyword;
            }

            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) continue;
                $extension = Str::lower((string) $file->getClientOriginalExtension());
                $blockedExtensions = collect($policy->file_extensions ?? [])->map(fn ($value) => Str::lower(ltrim((string) $value, '.')));
                if ($extension !== '' && $blockedExtensions->contains($extension)) $rules[] = 'file_extension:.'.$extension;
                if ($policy->max_file_bytes && $file->getSize() > (int) $policy->max_file_bytes) $rules[] = 'file_size:over_limit';
            }

            if (! $rules) continue;
            $mode = in_array($policy->mode, ['monitor', 'quarantine', 'block'], true) ? $policy->mode : 'monitor';
            $matches[] = ['policy_id' => $policy->id, 'policy_name' => $policy->name, 'mode' => $mode, 'rules' => array_values(array_unique($rules))];
            if ($mode === 'block') $highest = 'block';
            elseif ($mode === 'quarantine' && $highest !== 'block') $highest = 'quarantine';
            elseif ($highest === 'clean') $highest = 'monitor';
        }

        if ($highest === 'block') {
            $this->record($conversation, $actor, null, null, 'blocked', $matches);
            throw ValidationException::withMessages(['message' => ['This message was blocked by the workspace DLP policy.']]);
        }

        return ['action' => $highest, 'matches' => $matches];
    }

    /** Records monitor/quarantine detections after a message or attachment has been persisted. */
    public function recordResult(ChatConversation $conversation, WorkspaceMember $actor, ChatMessage $message, ?ChatMessageAttachment $attachment, array $result): void
    {
        if (($result['action'] ?? 'clean') === 'clean' || empty($result['matches'])) return;
        $this->record($conversation, $actor, $message, $attachment, ($result['action'] ?? 'monitor') === 'quarantine' ? 'quarantined' : 'detected', $result['matches']);
    }

    /** Determines whether an attachment is quarantined and requires moderator access to download. */
    public function canDownloadAttachment(ChatMessageAttachment $attachment, WorkspaceMember $member): bool
    {
        if (($attachment->security_status ?? 'clean') !== 'quarantined') return true;
        return $member->hasPermission('chat.moderate') || $member->hasPermission('chat.dlp_manage');
    }

    /** Writes a content-minimal DLP audit event without storing message or attachment bodies. */
    private function record(ChatConversation $conversation, WorkspaceMember $actor, ?ChatMessage $message, ?ChatMessageAttachment $attachment, string $action, array $matches): void
    {
        if (! Schema::hasTable('chat_dlp_events')) return;
        foreach ($matches as $match) {
            ChatDlpEvent::create([
                'workspace_id' => $conversation->workspace_id,
                'conversation_id' => $conversation->id,
                'message_id' => $message?->id,
                'attachment_id' => $attachment?->id,
                'policy_id' => $match['policy_id'] ?? null,
                'actor_member_id' => $actor->id,
                'action' => $action,
                'matched_rules' => $match['rules'] ?? [],
                'created_at' => now(),
            ]);
        }
    }
}
