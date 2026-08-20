<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides payroll adjustment behavior within the WorkIntel application. */ class PayrollAdjustment extends Model
{
    protected $fillable = ['payroll_item_id', 'workspace_id', 'category', 'direction', 'label', 'amount', 'note', 'source', 'created_by'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['amount' => 'decimal:2']; }
    /** Handles the item operation for the current WorkIntel workflow. */ public function item(): BelongsTo { return $this->belongsTo(PayrollItem::class, 'payroll_item_id'); }
}
