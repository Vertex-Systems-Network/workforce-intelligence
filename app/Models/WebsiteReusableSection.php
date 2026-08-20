<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Represents a reusable Website Studio section saved for later insertion. */
class WebsiteReusableSection extends Model
{
    protected $fillable = ['uuid','workspace_id','name','section_type','schema','is_global','created_by_member_id','updated_by_member_id'];

    /** Defines JSON/boolean casts for reusable section data. */
    protected function casts(): array { return ['schema' => 'array', 'is_global' => 'boolean']; }

    /** Returns the workspace owning this reusable section. */
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
}
