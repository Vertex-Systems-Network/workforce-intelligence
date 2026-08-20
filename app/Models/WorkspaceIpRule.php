<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides workspace ip rule behavior within the WorkIntel application. */ class WorkspaceIpRule extends Model{protected $fillable=['workspace_id','name','cidr','action','priority','active'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['active'=>'boolean'];}}
