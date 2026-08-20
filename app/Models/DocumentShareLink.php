<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Represents a revocable, expiring public share link for one generated document. */
class DocumentShareLink extends Model
{
    protected $hidden = ['token_hash'];
    protected $fillable = ['uuid', 'workspace_id', 'generated_document_id', 'token_hash', 'access_mode', 'max_views', 'view_count', 'expires_at', 'last_viewed_at', 'revoked_at', 'created_by_member_id'];

    /** Defines date and numeric casts used by link-governance rules. */
    protected function casts(): array
    {
        return ['max_views' => 'integer', 'view_count' => 'integer', 'expires_at' => 'datetime', 'last_viewed_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** Returns the generated document exposed by this link. */
    public function document(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }
}
