<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Represents a poll attached to exactly one chat message. */
class ChatPoll extends Model
{
    protected $fillable = ['message_id', 'allows_multiple', 'closes_at'];

    /** Casts poll settings and close timestamps to their runtime types. */
    protected function casts(): array
    {
        return ['allows_multiple' => 'boolean', 'closes_at' => 'datetime'];
    }

    /** Returns the chat message that presents this poll. */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class);
    }

    /** Returns ordered answer choices for the poll. */
    public function options(): HasMany
    {
        return $this->hasMany(ChatPollOption::class, 'poll_id')->orderBy('position')->orderBy('id');
    }

    /** Returns all recorded votes for the poll. */
    public function votes(): HasMany
    {
        return $this->hasMany(ChatPollVote::class, 'poll_id');
    }
}
