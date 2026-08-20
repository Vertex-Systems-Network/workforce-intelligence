<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Represents a persistent resumable batch-generation queue item for Document Studio. */
class DocumentBatchJob extends Model
{
    protected $fillable = ['uuid','client_request_id','workspace_id','document_template_id','source_type','source_ids','status','requested_count','processed_count','generated_count','failed_count','attempt_count','results','last_error','requested_by_member_id','started_at','heartbeat_at','completed_at'];

    /** Casts queue payload, results and lifecycle timestamps to stable values. */
    protected function casts(): array
    {
        return ['source_ids'=>'array','results'=>'array','started_at'=>'datetime','heartbeat_at'=>'datetime','completed_at'=>'datetime'];
    }

    /** Returns the template used by this batch job. */
    public function template(): BelongsTo { return $this->belongsTo(DocumentTemplate::class, 'document_template_id'); }
    /** Returns the workspace member who requested the batch. */
    public function requestedBy(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'requested_by_member_id'); }
}
