<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Records audited trash, restore and permanent-delete lifecycle actions. */
class DataLifecycleEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['workspace_id', 'actor_member_id', 'resource_type', 'resource_id', 'action', 'snapshot', 'created_at'];

    /** Defines typed lifecycle snapshots and timestamps. */
    protected function casts(): array
    {
        return ['snapshot' => 'array', 'created_at' => 'datetime'];
    }

    /** Returns the workspace that owns the lifecycle event. */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** Returns the member who performed the lifecycle action. */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class, 'actor_member_id');
    }
}
