<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tracks one resumable browser upload without storing any chunk payload in the database. */
class MediaUploadSession extends Model
{
    protected $fillable = ['uuid','workspace_id','user_id','folder_id','original_name','mime_type','extension','size_bytes','chunk_size_bytes','total_chunks','received_chunks','status','expires_at','metadata'];
    /** Cast upload progress and expiry into typed values. */
    protected function casts(): array { return ['size_bytes'=>'integer','chunk_size_bytes'=>'integer','total_chunks'=>'integer','received_chunks'=>'array','expires_at'=>'datetime','metadata'=>'array']; }
    /** Bind resumable upload routes by unguessable UUID instead of sequential database ID. */
    public function getRouteKeyName(): string { return 'uuid'; }

    /** Return the workspace that owns this upload. */
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Return the initiating user. */
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    /** Return the destination folder when one was selected. */
    public function folder(): BelongsTo { return $this->belongsTo(MediaFolder::class, 'folder_id')->withTrashed(); }
}
