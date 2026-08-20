<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Represents one lead-capture form rendered by a public workspace website. */
class WebsiteForm extends Model
{
    protected $fillable = ['uuid','workspace_id','website_site_id','website_page_id','name','slug','status','fields','settings','success_message','notification_emails','created_by_member_id'];

    /** Defines JSON casts for field and delivery configuration. */
    protected function casts(): array { return ['fields' => 'array', 'settings' => 'array', 'notification_emails' => 'array']; }

    /** Returns the workspace that owns this public lead form. */
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }

    /** Returns the website containing the form. */
    public function site(): BelongsTo { return $this->belongsTo(WebsiteSite::class, 'website_site_id'); }

    /** Returns the optional page primarily associated with this form. */
    public function page(): BelongsTo { return $this->belongsTo(WebsitePage::class, 'website_page_id'); }

    /** Returns lead submissions captured by this form. */
    public function submissions(): HasMany { return $this->hasMany(WebsiteFormSubmission::class); }
}
