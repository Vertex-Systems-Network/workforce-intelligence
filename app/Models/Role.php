<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides role behavior within the WorkIntel application. */ class Role extends Model
{
    protected $fillable = [
        'workspace_id', 'name', 'description', 'slug', 'is_system', 'status', 'template_key', 'created_by', 'archived_at',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return ['is_system' => 'boolean', 'archived_at' => 'datetime'];
    }

    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the creator operation for the current WorkIntel workflow. */ public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    /** Handles the members operation for the current WorkIntel workflow. */ public function members(): BelongsToMany
    {
        return $this->belongsToMany(WorkspaceMember::class, 'member_roles')
            ->withPivot(['is_primary', 'assigned_by'])->withTimestamps();
    }

    /** Handles the permissions operation for the current WorkIntel workflow. */ public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')->withTimestamps();
    }

    /** Handles the permission denies operation for the current WorkIntel workflow. */ public function permissionDenies(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission_denies')->withTimestamps();
    }

    /** Handles the data scopes operation for the current WorkIntel workflow. */ public function dataScopes(): HasMany { return $this->hasMany(RoleDataScope::class); }
    /** Handles the module access operation for the current WorkIntel workflow. */ public function moduleAccess(): HasMany { return $this->hasMany(RoleModuleAccess::class); }

    /** Determines whether the is fixed condition is satisfied. */ public function isFixed(): bool
    {
        return $this->is_system && in_array($this->slug, ['owner', 'admin'], true);
    }
}
