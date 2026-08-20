<?php

namespace App\Models;

use App\Casts\DateOnly;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provides project expense behavior within the WorkIntel application. */ class ProjectExpense extends Model
{
    protected $fillable = ['workspace_id', 'project_id', 'name', 'category', 'amount', 'currency', 'incurred_on', 'note', 'approval_status', 'reviewed_by', 'reviewed_at', 'created_by'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['amount' => 'decimal:2', 'incurred_on' => DateOnly::class, 'reviewed_at' => 'datetime']; }
    /** Handles the project operation for the current WorkIntel workflow. */ public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    /** Handles the creator operation for the current WorkIntel workflow. */ public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
