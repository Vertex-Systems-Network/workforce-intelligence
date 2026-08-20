<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Represents the singleton platform backup and retention policy. */
class SystemBackupPolicy extends Model
{
    protected $fillable = ['enabled','frequency','run_time','retention_days','minimum_verified_copies','include_private_storage','disk','included_paths','excluded_paths','updated_by'];

    /** Define typed policy attributes used by scheduling and backup execution. */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'retention_days' => 'integer',
            'minimum_verified_copies' => 'integer',
            'include_private_storage' => 'boolean',
            'included_paths' => 'array',
            'excluded_paths' => 'array',
        ];
    }

    /** Return the operator that most recently changed the policy. */
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
