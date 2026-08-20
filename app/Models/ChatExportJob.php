<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Represents a private, expiring eDiscovery export generated from authorized chat data. */
class ChatExportJob extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid', 'workspace_id', 'conversation_id', 'requested_by_member_id', 'format', 'status',
        'disk', 'path', 'checksum_sha256', 'size_bytes', 'filters', 'created_at', 'completed_at',
        'expires_at', 'error',
    ];

    protected $hidden = ['disk', 'path', 'checksum_sha256'];

    /** Defines export lifecycle and filter casts. */
    protected function casts(): array
    {
        return [
            'filters' => 'array', 'created_at' => 'datetime', 'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
