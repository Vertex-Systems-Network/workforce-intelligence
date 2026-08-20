<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides workspace access policy behavior within the WorkIntel application. */ class WorkspaceAccessPolicy extends Model
{
    protected $fillable=['uuid','workspace_id','name','resource','action','effect','priority','conditions','active','created_by'];
    /** Defines attribute casting rules for the model. */ protected function casts():array{return ['conditions'=>'array','active'=>'boolean'];}
}
