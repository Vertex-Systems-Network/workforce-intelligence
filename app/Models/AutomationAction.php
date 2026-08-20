<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides automation action behavior within the WorkIntel application. */ class AutomationAction extends Model
{
    protected $fillable=['automation_workflow_id','position','name','action_type','action_key','integration_connection_id','config','continue_on_error','retry_max','timeout_seconds'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['config'=>'array','continue_on_error'=>'boolean']; }
    /** Handles the workflow operation for the current WorkIntel workflow. */ public function workflow(): BelongsTo { return $this->belongsTo(AutomationWorkflow::class,'automation_workflow_id'); }
    /** Handles the integration operation for the current WorkIntel workflow. */ public function integration(): BelongsTo { return $this->belongsTo(IntegrationConnection::class,'integration_connection_id'); }
}
