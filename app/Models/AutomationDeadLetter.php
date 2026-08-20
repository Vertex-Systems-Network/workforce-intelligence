<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides automation dead letter behavior within the WorkIntel application. */ class AutomationDeadLetter extends Model
{
    public $timestamps=false;
    protected $fillable=['uuid','workspace_id','automation_run_id','reason','payload','retry_count','resolved_at','resolved_by','created_at'];
    /** Defines attribute casting rules for the model. */ protected function casts(): array { return ['payload'=>'array','resolved_at'=>'datetime','created_at'=>'datetime']; }
    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): BelongsTo { return $this->belongsTo(AutomationRun::class,'automation_run_id'); }
}
