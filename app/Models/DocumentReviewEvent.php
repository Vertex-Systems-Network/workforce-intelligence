<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Records immutable review, approval, lock and signature workflow events. */
class DocumentReviewEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['workspace_id', 'generated_document_id', 'actor_member_id', 'event', 'note', 'metadata', 'created_at'];

    /** Defines typed workflow metadata and event timestamps. */
    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    /** Returns the generated document associated with this workflow event. */
    public function document(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }
}
