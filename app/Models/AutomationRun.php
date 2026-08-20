<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/** Provides automation run behavior within the WorkIntel application. */ class AutomationRun extends Model
{
    public $timestamps=false;
    protected $fillable=['uuid','workspace_id','automation_workflow_id','automation_event_id','trigger_event','status','trigger_payload','context','attempts','next_attempt_at','started_at','completed_at','error','created_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['trigger_payload'=>'array','context'=>'array','next_attempt_at'=>'datetime','started_at'=>'datetime','completed_at'=>'datetime','created_at'=>'datetime']; }
    /** Handles the workflow operation for the current WorkIntel workflow. */ public function workflow(): BelongsTo { return $this->belongsTo(AutomationWorkflow::class,'automation_workflow_id'); }
    /** Handles the event operation for the current WorkIntel workflow. */ public function event(): BelongsTo { return $this->belongsTo(AutomationEvent::class,'automation_event_id'); }
    /** Handles the steps operation for the current WorkIntel workflow. */ public function steps(): HasMany { return $this->hasMany(AutomationRunStep::class)->orderBy('position'); }
    /** Handles the dead letter operation for the current WorkIntel workflow. */ public function deadLetter() { return $this->hasOne(AutomationDeadLetter::class); }
}
