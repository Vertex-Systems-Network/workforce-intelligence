<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Represents one localized versioned public website page. */
class WebsitePage extends Model
{
    protected $fillable = [
        'uuid','workspace_id','website_site_id','page_type','language','title','slug','status','is_home','navigation_visible','navigation_label',
        'sort_order','current_version','published_version','staged_version','seo_title','seo_description','og_media_id','created_by_member_id','updated_by_member_id','published_at','staged_at',
    ];

    /** Defines boolean, integer and date casts used by the website editor. */
    protected function casts(): array
    {
        return [
            'is_home' => 'boolean',
            'navigation_visible' => 'boolean',
            'sort_order' => 'integer',
            'current_version' => 'integer',
            'published_version' => 'integer',
            'staged_version' => 'integer',
            'published_at' => 'datetime',
            'staged_at' => 'datetime',
        ];
    }

    /** Returns the workspace that owns this public website page. */
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }

    /** Returns the website containing this page. */
    public function site(): BelongsTo { return $this->belongsTo(WebsiteSite::class, 'website_site_id'); }

    /** Returns immutable versions created for this page. */
    public function versions(): HasMany { return $this->hasMany(WebsitePageVersion::class); }

    /** Returns the latest mutable Website Studio autosave for this page. */
    public function draft(): HasOne { return $this->hasOne(WebsitePageDraft::class); }


    /** Returns review comments attached to this Website Studio page. */
    public function comments(): HasMany { return $this->hasMany(WebsitePageComment::class); }

    /** Returns linked reusable-component instances owned by this page. */
    public function reusableSectionLinks(): HasMany { return $this->hasMany(WebsiteReusableSectionLink::class); }

    /** Returns the OpenGraph image selected from Media Library. */
    public function ogMedia(): BelongsTo { return $this->belongsTo(MediaAsset::class, 'og_media_id')->withTrashed(); }
}
