<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Represents one immutable platform backup execution and its verification state. */
class SystemBackupRun extends Model
{
    protected $fillable = ['uuid','backup_type','status','database_driver','disk','backup_path','manifest_path','sha256','size_bytes','file_count','requested_by','failure_message','metadata','started_at','completed_at','verified_at','pruned_at'];
    protected $hidden = ['backup_path','manifest_path','metadata'];

    /** Define typed and encrypted backup attributes. */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'file_count' => 'integer',
            'metadata' => 'encrypted:array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'pruned_at' => 'datetime',
        ];
    }

    /** Return the operator that requested the backup. */
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }

    /** Return restore requests prepared from this backup. */
    public function restoreRequests(): HasMany { return $this->hasMany(SystemRestoreRequest::class, 'backup_run_id'); }
}
