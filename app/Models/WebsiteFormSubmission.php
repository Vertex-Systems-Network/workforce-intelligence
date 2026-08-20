<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stores one encrypted public lead-form submission. */
class WebsiteFormSubmission extends Model
{
    protected $fillable = ['uuid','workspace_id','website_form_id','website_page_id','payload','status','consent','source_url','ip_hash','user_agent_hash','internal_note','submitted_at'];

    /** Defines encrypted lead data and typed submission metadata. */
    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'consent' => 'boolean', 'submitted_at' => 'datetime'];
    }

    /** Returns the website form that captured this submission. */
    public function form(): BelongsTo { return $this->belongsTo(WebsiteForm::class, 'website_form_id'); }

    /** Returns the page where the submission was captured when known. */
    public function page(): BelongsTo { return $this->belongsTo(WebsitePage::class, 'website_page_id'); }
}
