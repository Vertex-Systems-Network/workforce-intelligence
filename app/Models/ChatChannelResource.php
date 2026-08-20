<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Represents a pinned link or WorkIntel resource surfaced in a channel resources panel. */
class ChatChannelResource extends Model
{
    protected $fillable = ['workspace_id', 'conversation_id', 'kind', 'label', 'url', 'resource_type', 'resource_id', 'metadata', 'sort_order', 'created_by_member_id'];

    /** Defines casting for resource metadata and ordering. */
    protected function casts(): array
    {
        return ['metadata' => 'array', 'sort_order' => 'integer'];
    }

    /** Returns the channel or conversation containing the resource. */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    /** Returns the member who pinned the resource. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class, 'created_by_member_id');
    }
}
