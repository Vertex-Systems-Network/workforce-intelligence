<?php

namespace App\Contracts;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\WorkspaceMember;

/** Defines the replaceable DLP scanning boundary used by chat message and attachment workflows. */
interface ChatDlpScanner
{
    /** Evaluates message text and uploaded-file metadata before persistence. */
    public function preflight(ChatConversation $conversation, WorkspaceMember $actor, ?string $body, array $files): array;

    /** Records monitor or quarantine results after governed content has been persisted. */
    public function recordResult(ChatConversation $conversation, WorkspaceMember $actor, ChatMessage $message, ?ChatMessageAttachment $attachment, array $result): void;

    /** Determines whether a workspace member may download one governed attachment. */
    public function canDownloadAttachment(ChatMessageAttachment $attachment, WorkspaceMember $member): bool;
}
