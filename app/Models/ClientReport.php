<?php
namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides client report behavior within the WorkIntel application. */ class ClientReport extends Model
{
    protected $fillable=['uuid','workspace_id','client_id','project_id','created_by','name','report_type','period_start','period_end','snapshot','note','published_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['period_start'=>DateOnly::class,'period_end'=>DateOnly::class,'snapshot'=>'array','published_at'=>'datetime']; }
    /** Handles the client operation for the current WorkIntel workflow. */ public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    /** Handles the project operation for the current WorkIntel workflow. */ public function project(): BelongsTo { return $this->belongsTo(Project::class); }
}
