<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides automation incoming hook behavior within the WorkIntel application. */ class AutomationIncomingHook extends Model
{
    public $timestamps=false;
    protected $fillable=['uuid','workspace_id','automation_workflow_id','name','event_name','token_prefix','token_hash','status','rate_limit_per_minute','last_used_at','last_used_ip','created_by','created_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['last_used_at'=>'datetime','created_at'=>'datetime']; }
    /** Handles the workflow operation for the current WorkIntel workflow. */ public function workflow(): BelongsTo { return $this->belongsTo(AutomationWorkflow::class,'automation_workflow_id'); }
}
