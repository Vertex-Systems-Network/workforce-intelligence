<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Represents an immutable production-operations audit event. */
class SystemOperationEvent extends Model
{
    protected $hidden = ['metadata'];
    protected $fillable = ['uuid','event_type','severity','actor_user_id','subject_type','subject_id','message','metadata','occurred_at'];

    /** Define encrypted event metadata and event time. */
    protected function casts(): array { return ['metadata'=>'encrypted:array','occurred_at'=>'datetime']; }

    /** Return the operator associated with the event when one exists. */
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }
}
