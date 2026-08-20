<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\HasMany;
/** Provides company policy behavior within the WorkIntel application. */ class CompanyPolicy extends Model { protected $fillable=['uuid','workspace_id','policy_key','version','title','content','status','acknowledgement_required','published_at','created_by']; /** Defines attribute casting rules for the model. */ protected function casts():array{return ['acknowledgement_required'=>'boolean','published_at'=>'datetime'];} /** Handles the acknowledgements operation for the current WorkIntel workflow. */ public function acknowledgements():HasMany{return $this->hasMany(PolicyAcknowledgement::class,'policy_id');} }
