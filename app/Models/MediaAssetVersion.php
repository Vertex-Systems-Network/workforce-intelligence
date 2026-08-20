<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stores one immutable metadata snapshot in a media asset's DAM version history. */
class MediaAssetVersion extends Model
{
    protected $fillable = ['workspace_id','media_asset_id','version_number','binary_disk','binary_path','original_name','mime_type','extension','size_bytes','checksum_sha256','width','height','duration_ms','binary_available','binary_status','name','folder_id','alt_text','caption','copyright_owner','license_type','license_reference','license_expires_at','usage_restrictions','rights_review_at','focal_x','focal_y','tags','metadata','created_by'];
    /** Cast serialized DAM metadata into typed values. */
    protected function casts(): array { return ['version_number'=>'integer','size_bytes'=>'integer','width'=>'integer','height'=>'integer','duration_ms'=>'integer','binary_available'=>'boolean','license_expires_at'=>'date','rights_review_at'=>'date','focal_x'=>'integer','focal_y'=>'integer','tags'=>'array','metadata'=>'array']; }
    /** Returns the owning asset, including trashed records for audit continuity. */
    public function asset(): BelongsTo { return $this->belongsTo(MediaAsset::class, 'media_asset_id')->withTrashed(); }
    /** Returns the folder captured by this version when it still exists. */
    public function folder(): BelongsTo { return $this->belongsTo(MediaFolder::class, 'folder_id')->withTrashed(); }
    /** Returns the user who created this version snapshot. */
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
