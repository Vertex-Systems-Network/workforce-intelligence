<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Represents a block-scoped or document-scoped collaboration comment. */
class DocumentComment extends Model
{
    protected $fillable = ['workspace_id', 'document_template_id', 'generated_document_id', 'block_id', 'author_member_id', 'body', 'resolved_at', 'resolved_by_member_id'];

    /** Defines collaboration comment date casts. */
    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /** Returns the template associated with this comment when present. */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    /** Returns the generated document associated with this comment when present. */
    public function document(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }

    /** Returns the member who authored the comment. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class, 'author_member_id');
    }
}
