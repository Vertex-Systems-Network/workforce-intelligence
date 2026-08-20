<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Records DLP detections, blocks and quarantines without exposing message or file contents. */
class ChatDlpEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workspace_id', 'conversation_id', 'message_id', 'attachment_id', 'policy_id',
        'actor_member_id', 'action', 'matched_rules', 'created_at',
    ];

    /** Defines DLP audit metadata casts. */
    protected function casts(): array
    {
        return ['matched_rules' => 'array', 'created_at' => 'datetime'];
    }
}
