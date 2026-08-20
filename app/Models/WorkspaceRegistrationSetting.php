<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides workspace registration setting behavior within the WorkIntel application. */ class WorkspaceRegistrationSetting extends Model
{
    protected $fillable=['workspace_id','mode','default_role_slug','allowed_domains','require_email_verification','invite_expires_hours','allow_existing_users'];
    /** Defines attribute casting rules for the model. */ protected function casts():array{return ['allowed_domains'=>'array','require_email_verification'=>'boolean','allow_existing_users'=>'boolean'];}
    /** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace():BelongsTo{return $this->belongsTo(Workspace::class);}
}
