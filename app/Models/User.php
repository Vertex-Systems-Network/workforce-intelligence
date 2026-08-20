<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Translation\HasLocalePreference;
use App\Support\LocaleCatalog;
use App\Notifications\WorkIntelPasswordResetNotification;
use Laravel\Sanctum\HasApiTokens;

/** Provides user behavior within the WorkIntel application. */ class User extends Authenticatable implements HasLocalePreference
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'avatar_url',
        'avatar_media_id',
        'password',
        'timezone',
        'locale',
        'use_workspace_locale',
        'status',
        'force_password_change',
        'password_changed_at',
        'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'force_password_change' => 'boolean',
            'use_workspace_locale' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /** Returns the media-library asset currently used as this user's avatar. */
    public function avatarMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'avatar_media_id')->withTrashed();
    }

    /** Handles the memberships operation for the current WorkIntel workflow. */ public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /** Handles the mfa method operation for the current WorkIntel workflow. */ public function mfaMethod(): HasOne
    {
        return $this->hasOne(UserMfaMethod::class)->where('type', 'totp');
    }


    /** Handles the preferred locale operation for the current WorkIntel workflow. */ public function preferredLocale(): string
    {
        $followsWorkspace = ! array_key_exists('use_workspace_locale',$this->getAttributes()) || ($this->use_workspace_locale ?? true);
        if (! $followsWorkspace) return LocaleCatalog::normalize($this->locale);
        $membership = $this->memberships()->where('status','active')->with('workspace.preferences')->first();
        return LocaleCatalog::normalize($membership?->workspace?->preferences?->default_language ?: $this->locale);
    }

    /** Sends send password reset notification information to the configured recipient. */ public function sendPasswordResetNotification($token): void
    {
        $this->notify(new WorkIntelPasswordResetNotification($token));
    }

    /** Handles the owned workspaces operation for the current WorkIntel workflow. */ public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }
}
