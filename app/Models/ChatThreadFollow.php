<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tracks whether a member follows a message thread and its read cursor. */
class ChatThreadFollow extends Model
{
    protected $fillable = ['workspace_id', 'root_message_id', 'member_id', 'last_read_reply_id', 'is_following'];

    /** Casts the thread follow preference to a boolean. */
    protected function casts(): array
    {
        return ['is_following' => 'boolean'];
    }

    /** Returns the root message for the followed thread. */
    public function rootMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'root_message_id');
    }

    /** Returns the member who owns the thread preference. */
    public function member(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class);
    }
}
