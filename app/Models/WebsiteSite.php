<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Represents the single public website owned by one workspace. */
class WebsiteSite extends Model
{
    protected $fillable = [
        'uuid','workspace_id','name','status','default_language','supported_languages','theme','header_config','footer_config','seo_defaults',
        'custom_domain_id','created_by_member_id','updated_by_member_id','published_at',
    ];

    /** Defines typed JSON and date attributes for the site. */
    protected function casts(): array
    {
        return [
            'supported_languages' => 'array',
            'theme' => 'array',
            'header_config' => 'array',
            'footer_config' => 'array',
            'seo_defaults' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /** Returns the workspace that owns the website. */
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }

    /** Returns pages belonging to the website. */
    public function pages(): HasMany { return $this->hasMany(WebsitePage::class); }

    /** Returns reusable sections available to the website workspace. */
    public function reusableSections(): HasMany { return $this->hasMany(WebsiteReusableSection::class, 'workspace_id', 'workspace_id'); }

    /** Returns forms configured for this website. */
    public function forms(): HasMany { return $this->hasMany(WebsiteForm::class); }

    /** Returns the verified custom domain assigned to the public website. */
    public function customDomain(): BelongsTo { return $this->belongsTo(WorkspaceDomain::class, 'custom_domain_id'); }
}
