<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides workspace invitation behavior within the WorkIntel application. */ class WorkspaceInvitation extends Model
{
    public $timestamps=false;
    protected $fillable=['uuid','workspace_id','email','token_hash','token_prefix','role_slug','department_id','job_title_id','manager_id','employment_type','collaboration_type','external_company','external_expires_at','chat_conversation_id','expires_at','accepted_at','accepted_by_user_id','created_by','created_at'];
    /** Defines attribute casting rules for the model. */ protected function casts():array{return ['expires_at'=>'datetime','external_expires_at'=>'datetime','accepted_at'=>'datetime','created_at'=>'datetime'];}
    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace():BelongsTo{return $this->belongsTo(Workspace::class);}
    /** Handles the creator operation for the current WorkIntel workflow. */ public function creator():BelongsTo{return $this->belongsTo(User::class,'created_by');}
}
