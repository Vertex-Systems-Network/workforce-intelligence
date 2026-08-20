<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stores the latest mutable Website Studio autosave without creating an immutable page version. */
class WebsitePageDraft extends Model
{
    protected $fillable = ['uuid','workspace_id','website_page_id','schema','metadata','revision','updated_by_member_id'];

    /** Casts editor JSON and revision counters to stable PHP values. */
    protected function casts(): array
    {
        return ['schema' => 'array', 'metadata' => 'array', 'revision' => 'integer'];
    }

    /** Returns the page this autosave belongs to. */
    public function page(): BelongsTo { return $this->belongsTo(WebsitePage::class, 'website_page_id'); }

    /** Returns the workspace that owns this editor draft. */
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }

    /** Returns the member who last updated the autosave. */
    public function updatedBy(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'updated_by_member_id'); }
}
