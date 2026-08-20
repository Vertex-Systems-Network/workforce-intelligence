<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tracks linked reusable-section instances so global component updates can propagate into editor drafts safely. */
class WebsiteReusableSectionLink extends Model
{
    protected $fillable = ['workspace_id','website_page_id','website_reusable_section_id','instance_id'];

    /** Returns the page containing this linked component instance. */
    public function page(): BelongsTo { return $this->belongsTo(WebsitePage::class, 'website_page_id'); }

    /** Returns the reusable component supplying this instance. */
    public function reusableSection(): BelongsTo { return $this->belongsTo(WebsiteReusableSection::class, 'website_reusable_section_id'); }
}
