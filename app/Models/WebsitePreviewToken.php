<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Represents one revocable, expiring share link to an immutable Website Studio staging version. */
class WebsitePreviewToken extends Model
{
    public $timestamps = false;

    protected $fillable = ['uuid','workspace_id','website_site_id','website_page_id','token_hash','source','version','created_by_member_id','expires_at','revoked_at','last_viewed_at','created_at'];

    /** Casts preview lifecycle values to typed PHP values. */
    protected function casts(): array { return ['version' => 'integer', 'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'last_viewed_at' => 'datetime', 'created_at' => 'datetime']; }

    /** Returns the page this preview link renders. */
    public function page(): BelongsTo { return $this->belongsTo(WebsitePage::class, 'website_page_id'); }

    /** Returns the site this preview link belongs to. */
    public function site(): BelongsTo { return $this->belongsTo(WebsiteSite::class, 'website_site_id'); }
}
