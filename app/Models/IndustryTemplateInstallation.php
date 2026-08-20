<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides industry template installation behavior within the WorkIntel application. */ class IndustryTemplateInstallation extends Model{public $timestamps=false;protected $fillable=['uuid','workspace_id','industry_template_id','template_version','installed_by','installed_at','summary'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['installed_at'=>'datetime','summary'=>'array'];}/** Handles the template operation for the current WorkIntel workflow. */ public function template():BelongsTo{return $this->belongsTo(IndustryTemplate::class,'industry_template_id');}}
