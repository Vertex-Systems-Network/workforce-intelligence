<?php

namespace App\Models;

use App\Casts\DateOnly;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Provides payroll run behavior within the WorkIntel application. */ class PayrollRun extends Model
{
    protected $fillable = [
        'uuid', 'workspace_id', 'name', 'period_start', 'period_end', 'pay_date', 'currency', 'run_type', 'compliance_pack_id', 'status',
        'calculated_at', 'submitted_at', 'submitted_by', 'approved_at', 'approved_by', 'paid_at', 'paid_by',
        'locked_at', 'locked_by', 'note',
    ];

    /** Defines attribute casting rules for the model. */ protected function casts(): array
    {
        return [
            'period_start' => DateOnly::class, 'period_end' => DateOnly::class, 'pay_date' => DateOnly::class, 'calculated_at' => 'datetime',
            'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'paid_at' => 'datetime', 'locked_at' => 'datetime',
        ];
    }

    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    /** Handles the items operation for the current WorkIntel workflow. */ public function items(): HasMany { return $this->hasMany(PayrollItem::class); }
    /** Handles the actions operation for the current WorkIntel workflow. */ public function actions(): HasMany { return $this->hasMany(PayrollAction::class); }
    /** Handles the compliance pack operation for the current WorkIntel workflow. */ public function compliancePack(): BelongsTo { return $this->belongsTo(PayrollCompliancePack::class, 'compliance_pack_id'); }
    /** Handles the selected members operation for the current WorkIntel workflow. */ public function selectedMembers(): HasMany { return $this->hasMany(PayrollRunMember::class); }
    /** Handles the exports operation for the current WorkIntel workflow. */ public function exports(): HasMany { return $this->hasMany(PayrollExport::class); }
}
