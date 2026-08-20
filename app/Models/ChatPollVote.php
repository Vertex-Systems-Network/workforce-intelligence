<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Records one member's vote for one poll option. */
class ChatPollVote extends Model
{
    public $timestamps = false;

    protected $fillable = ['poll_id', 'option_id', 'member_id', 'created_at'];

    /** Returns the poll associated with the vote. */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(ChatPoll::class);
    }

    /** Returns the selected poll option. */
    public function option(): BelongsTo
    {
        return $this->belongsTo(ChatPollOption::class, 'option_id');
    }

    /** Returns the member who cast the vote. */
    public function member(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class);
    }
}
