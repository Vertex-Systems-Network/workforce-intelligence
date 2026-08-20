<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Represents one selectable answer in a chat poll. */
class ChatPollOption extends Model
{
    public $timestamps = false;

    protected $fillable = ['poll_id', 'label', 'position', 'created_at'];

    /** Returns the poll that owns the option. */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(ChatPoll::class);
    }

    /** Returns votes recorded for this option. */
    public function votes(): HasMany
    {
        return $this->hasMany(ChatPollVote::class, 'option_id');
    }
}
