<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Stores immutable moderation and enterprise-governance audit events for chat. */
class ChatModerationEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workspace_id', 'conversation_id', 'message_id', 'actor_member_id', 'target_member_id',
        'action', 'reason', 'metadata', 'created_at',
    ];

    /** Defines JSON and timestamp casts for moderation audit entries. */
    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }
}
