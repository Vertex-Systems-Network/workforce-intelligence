<?php

namespace App\Models;

use App\Casts\DateOnly;

use App\Enums\MemberStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Provides workspace member behavior within the WorkIntel application. */ class WorkspaceMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id', 'user_id', 'employee_code', 'job_title', 'department_id', 'manager_id',
        'employment_type', 'collaboration_type', 'external_company', 'external_expires_at', 'external_scope', 'employment_stage', 'legal_entity_id', 'business_unit_id', 'joining_date', 'probation_end_date', 'termination_date', 'status', 'timezone', 'job_title_id',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return [
            'joining_date' => DateOnly::class,
            'probation_end_date' => 'date',
            'termination_date' => 'date',
            'status' => MemberStatus::class,
            'external_expires_at' => 'datetime',
            'external_scope' => 'array',
        ];
    }

    /** Returns true when this membership is active regardless of enum/string hydration. */
    public function isActive(): bool
    {
        return $this->status instanceof MemberStatus ? $this->status === MemberStatus::Active : (string) $this->status === MemberStatus::Active->value;
    }

    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** Handles the user operation for the current WorkIntel workflow. */ public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Handles the legal entity operation for the current WorkIntel workflow. */ public function legalEntity(): BelongsTo { return $this->belongsTo(LegalEntity::class); }
    /** Handles the business unit operation for the current WorkIntel workflow. */ public function businessUnit(): BelongsTo { return $this->belongsTo(BusinessUnit::class); }

    /** Handles the department operation for the current WorkIntel workflow. */ public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Handles the job title operation for the current WorkIntel workflow. */ public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    /** Handles the teams operation for the current WorkIntel workflow. */ public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'member_id', 'team_id')
            ->withPivot('role')->withTimestamps();
    }

    /** Handles the manager operation for the current WorkIntel workflow. */ public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    /** Handles the reports operation for the current WorkIntel workflow. */ public function reports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    /** Handles the roles operation for the current WorkIntel workflow. */ public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'member_roles')->withPivot(['is_primary', 'assigned_by'])->withTimestamps();
    }

    /** Determines whether the has permission condition is satisfied. */ public function hasPermission(string $permission): bool
    {
        $roles = $this->relationLoaded('roles')
            ? $this->roles->where('status', 'active')
            : $this->roles()->where('roles.status', 'active')->with(['permissions', 'permissionDenies', 'moduleAccess'])->get();

        if ($roles->contains(fn (Role $role) => $role->is_system && in_array($role->slug, ['owner', 'admin'], true))) {
            return true;
        }

        // Explicit deny always wins across multiple roles.
        if ($roles->contains(fn (Role $role) => $role->permissionDenies->contains('slug', $permission))) {
            return false;
        }

        $grantingRole = $roles->first(fn (Role $role) => $role->permissions->contains('slug', $permission));
        if (! $grantingRole) return false;

        $permissionModel = $grantingRole->permissions->firstWhere('slug', $permission);
        if ($permissionModel) {
            $moduleKey = \App\Support\PermissionCatalog::moduleKeyForGroup($permissionModel->group);
            if ($roles->contains(fn (Role $role) => $role->moduleAccess->contains(fn ($rule) => $rule->module_key === $moduleKey && $rule->access === 'deny'))) {
                return false;
            }
        }

        return true;
    }


    /** Returns true when the membership represents a guest, client or vendor collaborator. */
    public function isExternal(): bool
    {
        return in_array((string) ($this->collaboration_type ?? 'internal'), ['guest', 'client', 'vendor'], true);
    }

    /** Returns true when an external collaborator has passed the configured access expiry. */
    public function externalExpired(): bool
    {
        return $this->isExternal() && $this->external_expires_at && $this->external_expires_at->isPast();
    }

    /** Handles the time sessions operation for the current WorkIntel workflow. */ public function timeSessions(): HasMany
    {
        return $this->hasMany(TimeSession::class, 'member_id');
    }


    /** Handles the devices operation for the current WorkIntel workflow. */ public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'member_id');
    }

    /** Handles the presence operation for the current WorkIntel workflow. */ public function presence(): HasOne
    {
        return $this->hasOne(WorkerPresence::class, 'member_id');
    }

    /** Handles the work events operation for the current WorkIntel workflow. */ public function workEvents(): HasMany
    {
        return $this->hasMany(WorkEvent::class, 'member_id');
    }

    /** Handles the compensation profiles operation for the current WorkIntel workflow. */ public function compensationProfiles(): HasMany
    {
        return $this->hasMany(CompensationProfile::class, 'member_id');
    }

    /** Handles the payroll items operation for the current WorkIntel workflow. */ public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class, 'member_id');
    }
}
