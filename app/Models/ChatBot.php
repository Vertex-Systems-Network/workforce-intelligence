<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Represents a workspace-scoped system or automation identity that can publish structured chat messages. */
class ChatBot extends Model
{
    protected $fillable = ['workspace_id', 'slug', 'name', 'kind', 'avatar_key', 'is_active', 'metadata'];

    /** Defines casting for bot state and metadata. */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'metadata' => 'array'];
    }

    /** Returns the workspace that owns the bot identity. */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** Returns messages published by the bot identity. */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'sender_bot_id');
    }
}
