<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides payroll item project behavior within the WorkIntel application. */ class PayrollItemProject extends Model
{
    protected $fillable = ['payroll_item_id', 'workspace_id', 'member_id', 'project_id', 'amount'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['amount' => 'decimal:2']; }
    /** Handles the item operation for the current WorkIntel workflow. */ public function item(): BelongsTo { return $this->belongsTo(PayrollItem::class, 'payroll_item_id'); }
    /** Handles the project operation for the current WorkIntel workflow. */ public function project(): BelongsTo { return $this->belongsTo(Project::class); }
}
