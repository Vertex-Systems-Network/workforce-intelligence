<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stores the latest mutable Document Studio V6 autosave for one template. */
class DocumentTemplateDraft extends Model
{
    protected $fillable = ['uuid','workspace_id','document_template_id','content_schema','settings','metadata','revision','updated_by_member_id'];

    /** Defines JSON and numeric casts for the mutable draft payload. */
    protected function casts(): array
    {
        return ['content_schema'=>'array','settings'=>'array','metadata'=>'array','revision'=>'integer'];
    }

    /** Returns the immutable-versioned template this draft belongs to. */
    public function template(): BelongsTo { return $this->belongsTo(DocumentTemplate::class, 'document_template_id'); }

    /** Returns the workspace member who most recently autosaved the draft. */
    public function updatedBy(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'updated_by_member_id'); }
}
