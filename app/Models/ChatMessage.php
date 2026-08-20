<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Represents a workspace-scoped chat message and its professional collaboration metadata. */
class ChatMessage extends Model
{
    protected $fillable = [
        'uuid',
        'client_message_id',
        'client_sent_at',
        'workspace_id',
        'conversation_id',
        'sender_member_id',
        'sender_bot_id',
        'parent_id',
        'forwarded_from_message_id',
        'message_type',
        'body',
        'mentions',
        'metadata',
        'edited_at',
        'deleted_at',
    ];

    /** Defines attribute casting rules for chat message metadata. */
    protected function casts(): array
    {
        return ['mentions' => 'array', 'metadata' => 'array', 'client_sent_at' => 'datetime', 'edited_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    /** Returns the conversation containing the message. */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class);
    }

    /** Returns the workspace member who sent the message. */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class, 'sender_member_id');
    }


    /** Returns the bot identity when the message was published by a system or automation bot. */
    public function senderBot(): BelongsTo
    {
        return $this->belongsTo(ChatBot::class, 'sender_bot_id');
    }

    /** Returns the root message when this message is a threaded reply. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Returns direct replies that belong to this message thread. */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Returns the original source message when this message was forwarded. */
    public function forwardedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'forwarded_from_message_id');
    }

    /** Returns private files attached to the message. */
    public function attachments(): HasMany
    {
        return $this->hasMany(ChatMessageAttachment::class, 'message_id');
    }

    /** Returns emoji reactions attached to the message. */
    public function reactions(): HasMany
    {
        return $this->hasMany(ChatMessageReaction::class, 'message_id');
    }

    /** Returns pins that reference the message. */
    public function pins(): HasMany
    {
        return $this->hasMany(ChatMessagePin::class, 'message_id');
    }

    /** Returns immutable edit snapshots for this message. */
    public function editHistory(): HasMany
    {
        return $this->hasMany(ChatMessageEditHistory::class, 'message_id')->latest('id');
    }

    /** Returns the optional poll presented by this message. */
    public function poll(): HasOne
    {
        return $this->hasOne(ChatPoll::class, 'message_id');
    }
}
