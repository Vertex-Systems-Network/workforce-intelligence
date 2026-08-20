<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides installation guide progress behavior within the WorkIntel application. */ class InstallationGuideProgress extends Model
{
    protected $table='installation_guide_progress';
    protected $fillable=['workspace_id','member_id','guide_key','completed_steps','current_step','completed_at'];
    /** Defines attribute casting rules for the model. */ protected function casts():array{return ['completed_steps'=>'array','completed_at'=>'datetime'];}
    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace():BelongsTo{return $this->belongsTo(Workspace::class);}
    /** Handles the member operation for the current WorkIntel workflow. */ public function member():BelongsTo{return $this->belongsTo(WorkspaceMember::class);}
}
