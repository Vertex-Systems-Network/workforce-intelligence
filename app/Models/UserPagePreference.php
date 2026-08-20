<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stores personal UI preferences for one page inside one workspace. */
class UserPagePreference extends Model
{
    protected $fillable = ['user_id', 'workspace_id', 'page_key', 'settings'];

    /** Define JSON casting for the saved page customization payload. */
    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    /** Return the user who owns this page preference. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Return the workspace where this preference applies. */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
