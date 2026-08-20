<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides automation run step behavior within the WorkIntel application. */ class AutomationRunStep extends Model
{
    public $timestamps=false;
    protected $fillable=['automation_run_id','automation_action_id','position','name','status','input','output','attempts','started_at','completed_at','error'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['input'=>'array','output'=>'array','started_at'=>'datetime','completed_at'=>'datetime']; }
    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): BelongsTo { return $this->belongsTo(AutomationRun::class,'automation_run_id'); }
    /** Handles the action operation for the current WorkIntel workflow. */ public function action(): BelongsTo { return $this->belongsTo(AutomationAction::class,'automation_action_id'); }
}
