<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Persists a member's text draft for one conversation across sessions and devices. */
class ChatDraft extends Model
{
    protected $fillable = ['workspace_id', 'conversation_id', 'member_id', 'parent_id', 'body'];

    /** Returns the conversation that owns the draft. */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class);
    }

    /** Returns the member who owns the draft. */
    public function member(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class);
    }

    /** Returns the optional thread root/reply target stored with the draft. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'parent_id');
    }
}
