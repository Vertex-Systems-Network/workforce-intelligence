<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides workspace branding behavior within the WorkIntel application. */ class WorkspaceBranding extends Model{protected $fillable=['uuid','workspace_id','product_name','support_email','support_url','accent_color','logo_path','logo_mime','favicon_path','favicon_mime','hide_powered_by','email_branding'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['hide_powered_by'=>'boolean','email_branding'=>'array'];}/** Handles the workspace operation for the current WorkIntel workflow. */ public function workspace():BelongsTo{return $this->belongsTo(Workspace::class);}}
