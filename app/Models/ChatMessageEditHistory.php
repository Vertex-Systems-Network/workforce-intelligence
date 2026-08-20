<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stores immutable pre-edit snapshots for auditable professional chat messages. */
class ChatMessageEditHistory extends Model
{
    public $timestamps = false;

    protected $table = 'chat_message_edit_history';

    protected $fillable = ['message_id', 'edited_by_member_id', 'body', 'mentions', 'edited_at'];

    /** Casts edit-history metadata to application-safe values. */
    protected function casts(): array
    {
        return ['mentions' => 'array', 'edited_at' => 'datetime'];
    }

    /** Returns the message whose prior version this row captures. */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class);
    }

    /** Returns the member who performed the edit when that member still exists. */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class, 'edited_by_member_id');
    }
}
