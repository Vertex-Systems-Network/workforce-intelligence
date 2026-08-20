<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Represents a short-lived, hash-only CLI restore authorization prepared by a platform operator. */
class SystemRestoreRequest extends Model
{
    protected $fillable = ['uuid','backup_run_id','requested_by','token_hash','status','restore_scope','notes','expires_at','executed_at','revoked_at'];
    protected $hidden = ['token_hash'];

    /** Define restore lifecycle date attributes. */
    protected function casts(): array
    {
        return ['expires_at'=>'datetime','executed_at'=>'datetime','revoked_at'=>'datetime'];
    }

    /** Return the backup selected for restore. */
    public function backup(): BelongsTo { return $this->belongsTo(SystemBackupRun::class, 'backup_run_id'); }

    /** Return the platform operator that prepared the request. */
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
}
