<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stores one immutable Website Studio page schema revision. */
class WebsitePageVersion extends Model
{
    public $timestamps = false;

    protected $fillable = ['website_page_id','version','schema','change_note','created_by_member_id','published_at','created_at'];

    /** Defines typed schema/version metadata for this immutable revision. */
    protected function casts(): array
    {
        return ['schema' => 'array', 'version' => 'integer', 'published_at' => 'datetime', 'created_at' => 'datetime'];
    }

    /** Returns the page owned by this version. */
    public function page(): BelongsTo { return $this->belongsTo(WebsitePage::class, 'website_page_id'); }
}
