<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

/** Represents a workspace-scoped direct message, group, channel, project thread or task thread. */
class ChatConversation extends Model
{
    protected $fillable = [
        'uuid', 'workspace_id', 'type', 'visibility', 'channel_mode', 'posting_policy', 'is_locked', 'external_access', 'retention_days', 'legal_hold', 'export_policy', 'dlp_mode',
        'name', 'description', 'direct_key', 'project_id', 'task_id', 'created_by_member_id', 'archived_at',
    ];

    /** Defines attribute casting rules for channel state. */
    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'is_locked' => 'boolean', 'external_access' => 'boolean', 'legal_hold' => 'boolean'];
    }

    /** Returns the workspace that owns the conversation. */
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }

    /** Returns members together with channel role and notification preferences. */
    public function members(): BelongsToMany
    {
        $pivotColumns = ['role', 'is_muted', 'notification_mode', 'notifications_snoozed_until', 'guest_expires_at', 'last_read_message_id', 'joined_at'];
        if (Schema::hasColumn('chat_conversation_members', 'last_delivered_message_id')) $pivotColumns[] = 'last_delivered_message_id';
        return $this->belongsToMany(WorkspaceMember::class, 'chat_conversation_members', 'conversation_id', 'member_id')->withPivot($pivotColumns);
    }

    /** Returns messages in the conversation. */
    public function messages(): HasMany { return $this->hasMany(ChatMessage::class, 'conversation_id'); }

    /** Returns the linked project when this is a project-scoped conversation. */
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }

    /** Returns the linked task when this is a task-scoped conversation. */
    public function task(): BelongsTo { return $this->belongsTo(Task::class); }

    /** Returns resources pinned to the channel details panel. */
    public function resources(): HasMany { return $this->hasMany(ChatChannelResource::class, 'conversation_id')->orderBy('sort_order')->orderBy('id'); }
}
