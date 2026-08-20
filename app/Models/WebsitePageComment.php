<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stores one page- or section-level Website Studio review comment. */
class WebsitePageComment extends Model
{
    protected $fillable = ['uuid','workspace_id','website_page_id','section_id','message','status','created_by_member_id','resolved_by_member_id','resolved_at'];

    /** Casts resolution timestamps for review workflows. */
    protected function casts(): array { return ['resolved_at' => 'datetime']; }

    /** Returns the Website Studio page being reviewed. */
    public function page(): BelongsTo { return $this->belongsTo(WebsitePage::class, 'website_page_id'); }

    /** Returns the member who created the review comment. */
    public function createdBy(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'created_by_member_id'); }

    /** Returns the member who resolved the review comment. */
    public function resolvedBy(): BelongsTo { return $this->belongsTo(WorkspaceMember::class, 'resolved_by_member_id'); }
}
