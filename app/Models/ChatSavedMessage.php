<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Represents one member's private bookmark to a chat message. */
class ChatSavedMessage extends Model
{
    protected $fillable = ['workspace_id', 'member_id', 'message_id', 'note'];

    /** Returns the saved chat message. */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class);
    }

    /** Returns the member who owns the private bookmark. */
    public function member(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class);
    }
}
