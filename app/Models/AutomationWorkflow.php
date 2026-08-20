<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
/** Provides automation workflow behavior within the WorkIntel application. */ class AutomationWorkflow extends Model
{
    protected $fillable=['uuid','workspace_id','name','description','status','trigger_type','trigger_event','trigger_config','conditions','condition_mode','failure_policy','max_run_seconds','next_run_at','last_run_at','created_by','updated_by'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['trigger_config'=>'array','conditions'=>'array','next_run_at'=>'datetime','last_run_at'=>'datetime']; }
    /** Handles the actions operation for the current WorkIntel workflow. */ public function actions(): HasMany { return $this->hasMany(AutomationAction::class)->orderBy('position'); }
    /** Handles the runs operation for the current WorkIntel workflow. */ public function runs(): HasMany { return $this->hasMany(AutomationRun::class); }
}
