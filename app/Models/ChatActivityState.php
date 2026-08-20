<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stores private collaboration-inbox triage state for one workspace member. */
class ChatActivityState extends Model
{
    protected $fillable=['workspace_id','member_id','activity_type','activity_key','status','snoozed_until','follow_up_at'];

    /** Casts collaboration triage timestamps into immutable date values. */
    protected function casts(): array { return ['snoozed_until'=>'immutable_datetime','follow_up_at'=>'immutable_datetime']; }
    /** Returns the member that owns this private triage state. */
    public function member(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'member_id'); }
}
