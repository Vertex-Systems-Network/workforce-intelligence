<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Represents reusable page, header, footer and watermark defaults for Document Studio V6. */
class DocumentPageMaster extends Model
{
    protected $fillable = ['uuid','workspace_id','name','page_settings','header_settings','footer_settings','watermark_settings','is_default','created_by_member_id','updated_by_member_id'];

    /** Casts reusable page-region configuration to structured arrays. */
    protected function casts(): array
    {
        return ['page_settings'=>'array','header_settings'=>'array','footer_settings'=>'array','watermark_settings'=>'array','is_default'=>'boolean'];
    }

    /** Returns the workspace that owns this page master. */
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
}
