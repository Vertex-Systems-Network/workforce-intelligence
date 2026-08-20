<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/** Provides role data scope behavior within the WorkIntel application. */ class RoleDataScope extends Model
{
    protected $fillable=['role_id','resource','scope_type','scope_ids'];
    /** Defines attribute casting rules for the model. */ protected function casts():array{return ['scope_ids'=>'array'];}
    /** Handles the role operation for the current WorkIntel workflow. */ public function role():BelongsTo{return $this->belongsTo(Role::class);}
}
