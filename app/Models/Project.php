<?php

namespace App\Models;

use App\Casts\DateOnly;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Provides project behavior within the WorkIntel application. */ class Project extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'workspace_id', 'client_id', 'legal_entity_id', 'business_unit_id', 'name', 'code', 'description', 'status', 'priority', 'start_date', 'due_date', 'completed_at',
        'budget_type', 'budget_amount', 'estimated_minutes', 'billable', 'client_visible', 'currency', 'created_by',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return [
            'start_date' => DateOnly::class, 'due_date' => DateOnly::class, 'completed_at' => 'datetime', 'budget_amount' => 'decimal:2', 'billable' => 'boolean', 'client_visible' => 'boolean',
        ];
    }

    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the client operation for the current WorkIntel workflow. */ public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    /** Handles the legal entity operation for the current WorkIntel workflow. */ public function legalEntity(): BelongsTo { return $this->belongsTo(LegalEntity::class); }
    /** Handles the business unit operation for the current WorkIntel workflow. */ public function businessUnit(): BelongsTo { return $this->belongsTo(BusinessUnit::class); }
    /** Handles the tasks operation for the current WorkIntel workflow. */ public function tasks(): HasMany { return $this->hasMany(Task::class); }
    /** Handles the expenses operation for the current WorkIntel workflow. */ public function expenses(): HasMany { return $this->hasMany(ProjectExpense::class); }
    /** Handles the time entries operation for the current WorkIntel workflow. */ public function timeEntries(): HasMany { return $this->hasMany(TimeEntry::class); }
    /** Handles the members operation for the current WorkIntel workflow. */ public function members(): BelongsToMany
    {
        return $this->belongsToMany(WorkspaceMember::class, 'project_members', 'project_id', 'member_id')
            ->withPivot(['role', 'hourly_cost', 'billing_rate'])->withTimestamps();
    }
}
