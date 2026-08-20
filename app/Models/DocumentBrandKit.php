<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Represents a reusable workspace-owned visual brand system for Document Studio. */
class DocumentBrandKit extends Model
{
    protected $fillable = ['uuid','workspace_id','name','primary_color','secondary_color','accent_color','font_family','heading_font_family','logo_media_asset_id','settings','is_default','created_by_member_id','updated_by_member_id'];

    /** Casts structured brand settings and flags to stable application values. */
    protected function casts(): array
    {
        return ['settings'=>'array','is_default'=>'boolean'];
    }

    /** Returns the workspace that owns this brand kit. */
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Returns the optional Media DAM logo used by the kit. */
    public function logo(): BelongsTo { return $this->belongsTo(MediaAsset::class, 'logo_media_asset_id'); }
}
