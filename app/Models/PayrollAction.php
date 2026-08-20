<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides payroll action behavior within the WorkIntel application. */ class PayrollAction extends Model
{
    protected $fillable = ['payroll_run_id', 'payroll_item_id', 'workspace_id', 'user_id', 'action', 'from_status', 'to_status', 'note', 'metadata', 'occurred_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['metadata' => 'array', 'occurred_at' => 'datetime']; }
    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): BelongsTo { return $this->belongsTo(PayrollRun::class, 'payroll_run_id'); }
    /** Handles the item operation for the current WorkIntel workflow. */ public function item(): BelongsTo { return $this->belongsTo(PayrollItem::class, 'payroll_item_id'); }
    /** Handles the user operation for the current WorkIntel workflow. */ public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
