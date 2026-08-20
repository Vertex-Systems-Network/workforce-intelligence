<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stores one generated, private rendition derived from the current binary of a media asset. */
class MediaRendition extends Model
{
    protected $fillable = ['workspace_id','media_asset_id','media_asset_version_id','spec_hash','fit','width','height','format','quality','disk','path','mime_type','size_bytes','checksum_sha256','status','metadata','created_by'];
    /** Cast rendition dimensions and metadata into stable API types. */
    protected function casts(): array { return ['width'=>'integer','height'=>'integer','quality'=>'integer','size_bytes'=>'integer','metadata'=>'array']; }
    /** Return the source media asset. */
    public function asset(): BelongsTo { return $this->belongsTo(MediaAsset::class, 'media_asset_id')->withTrashed(); }
    /** Return the immutable media version used to generate this rendition when available. */
    public function version(): BelongsTo { return $this->belongsTo(MediaAssetVersion::class, 'media_asset_version_id'); }
}
