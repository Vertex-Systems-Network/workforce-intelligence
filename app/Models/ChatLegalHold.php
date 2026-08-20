<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Represents an auditable workspace or conversation-level legal hold for chat data. */
class ChatLegalHold extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid', 'workspace_id', 'conversation_id', 'name', 'reason', 'status',
        'created_by_member_id', 'released_by_member_id', 'created_at', 'released_at', 'metadata',
    ];

    /** Defines date and metadata casts for legal-hold lifecycle state. */
    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'released_at' => 'datetime', 'metadata' => 'array'];
    }

    /** Returns the workspace governed by this legal hold. */
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }

    /** Returns the optional conversation governed by this legal hold. */
    public function conversation(): BelongsTo { return $this->belongsTo(ChatConversation::class); }
}
