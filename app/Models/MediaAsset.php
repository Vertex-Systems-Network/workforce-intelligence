<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Represents one private or public media-library asset owned by a workspace. */
class MediaAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'workspace_id', 'folder_id', 'uploaded_by', 'name', 'original_name', 'disk', 'path',
        'mime_type', 'extension', 'size_bytes', 'checksum_sha256', 'width', 'height', 'focal_x', 'focal_y', 'duration_ms',
        'alt_text', 'caption', 'copyright_owner', 'license_type', 'license_reference', 'license_expires_at', 'usage_restrictions', 'rights_review_at', 'visibility', 'status', 'metadata',
    ];

    /** Defines typed metadata and numeric dimensions for API consumers. */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'focal_x' => 'integer',
            'focal_y' => 'integer',
            'duration_ms' => 'integer',
            'license_expires_at' => 'date',
            'rights_review_at' => 'date',
        ];
    }

    /** Returns the workspace that owns this asset. */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** Returns the media-library folder containing this asset. */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id')->withTrashed();
    }

    /** Returns the user who uploaded the asset. */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Returns tags assigned to the asset. */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MediaTag::class, 'media_asset_tag');
    }

    /** Returns the places where the asset is actively referenced. */
    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class);
    }

    /** Returns reusable DAM collections containing this asset. */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(MediaCollection::class, 'media_asset_collection')->withTimestamps();
    }

    /** Returns member-specific favorite records for this asset. */
    public function favorites(): HasMany
    {
        return $this->hasMany(MediaFavorite::class);
    }

    /** Returns immutable metadata versions newest first. */
    public function versions(): HasMany
    {
        return $this->hasMany(MediaAssetVersion::class)->orderByDesc('version_number');
    }


    /** Returns generated private renditions for this asset. */
    public function renditions(): HasMany
    {
        return $this->hasMany(MediaRendition::class);
    }

    /** Return a derived rights-governance state without storing stale status flags. */
    public function rightsStatus(): string
    {
        $expiry = $this->license_expires_at;
        if ($expiry && $expiry->lt(today())) return 'expired';
        if ($expiry && $expiry->lte(today()->addDays(30))) return 'expiring';
        if ($this->rights_review_at && $this->rights_review_at->lte(today())) return 'review';
        if (! $this->copyright_owner && ! $this->license_type && ! $this->license_reference) return 'unclassified';
        return 'clear';
    }

    /** Returns a broad media category derived from the MIME type. */
    public function category(): string
    {
        $mime = strtolower((string) $this->mime_type);
        if (str_starts_with($mime, 'image/')) return 'image';
        if (str_starts_with($mime, 'video/')) return 'video';
        if (str_starts_with($mime, 'audio/')) return 'audio';
        if (str_contains($mime, 'pdf') || str_starts_with($mime, 'text/') || str_contains($mime, 'document') || str_contains($mime, 'sheet') || str_contains($mime, 'presentation')) return 'document';
        return 'other';
    }
}
