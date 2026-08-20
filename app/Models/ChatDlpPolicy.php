<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Defines workspace-scoped DLP rules for chat text and attachment metadata. */
class ChatDlpPolicy extends Model
{
    protected $fillable = [
        'uuid', 'workspace_id', 'name', 'mode', 'keywords', 'file_extensions',
        'max_file_bytes', 'active', 'created_by_member_id',
    ];

    /** Defines structured DLP rule casts. */
    protected function casts(): array
    {
        return ['keywords' => 'array', 'file_extensions' => 'array', 'active' => 'boolean'];
    }
}
