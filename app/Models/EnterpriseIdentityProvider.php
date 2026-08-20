<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides enterprise identity provider behavior within the WorkIntel application. */ class EnterpriseIdentityProvider extends Model{protected $fillable=['uuid','workspace_id','name','type','status','domains','config_encrypted','enforce_login','jit_provisioning','default_role_slug','created_by'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['domains'=>'array','enforce_login'=>'boolean','jit_provisioning'=>'boolean'];}}
